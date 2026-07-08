<?php

declare(strict_types=1);

namespace Diff\Engine;

/**
 * Class used internally by Diff to actually compute the diffs.
 *
 * The algorithm used here is mostly lifted from the perl module
 * Algorithm::Diff (version 1.06) by Ned Konz.
 *
 * @author Geoffrey T. Dairiki
 */
class DiffEngine
{
	private const USE_ASSERTS = false;

	/** @var array<int, bool> */
	private array $xchanged;
	/** @var array<int, bool> */
	private array $ychanged;
	/** @var array<int, int> */
	private array $xind;
	/** @var array<int, int> */
	private array $yind;
	/** @var array<int, string> */
	private array $xv;
	/** @var array<int, string> */
	private array $yv;
	private int $lcs = 0;
	/** @var array<int, int> */
	private array $seq = [];
	/** @var array<int, bool> */
	private array $in_seq = [];

	/**
	 * @param array<int, string> $from_lines
	 * @param array<int, string> $to_lines
	 * @return array<int, DiffOp>
	 */
	public function diff(array $from_lines, array $to_lines): array
	{
		$n_from = count($from_lines);
		$n_to = count($to_lines);

		$this->xchanged = $this->ychanged = [];
		$this->xv = $this->yv = [];
		$this->xind = $this->yind = [];
		$this->seq = [];
		$this->in_seq = [];
		$this->lcs = 0;

		$xhash = [];
		$yhash = [];

		// Skip leading common lines.
		$skip = 0;
		while ($skip < $n_from && $skip < $n_to && $from_lines[$skip] === $to_lines[$skip])
		{
			$this->xchanged[$skip] = $this->ychanged[$skip] = false;
			$skip++;
		}

		// Skip trailing common lines.
		$xi = $n_from;
		$yi = $n_to;
		$endskip = 0;
		while (--$xi > $skip && --$yi > $skip && $from_lines[$xi] === $to_lines[$yi])
		{
			$this->xchanged[$xi] = $this->ychanged[$yi] = false;
			$endskip++;
		}

		// Ignore lines which do not exist in both files.
		for ($xi = $skip; $xi < $n_from - $endskip; $xi++)
		{
			$xhash[$from_lines[$xi]] = 1;
		}

		for ($yi = $skip; $yi < $n_to - $endskip; $yi++)
		{
			$line = $to_lines[$yi];
			$this->ychanged[$yi] = empty($xhash[$line]);
			if ($this->ychanged[$yi])
			{
				continue;
			}

			$yhash[$line] = 1;
			$this->yv[] = $line;
			$this->yind[] = $yi;
		}

		for ($xi = $skip; $xi < $n_from - $endskip; $xi++)
		{
			$line = $from_lines[$xi];
			$this->xchanged[$xi] = empty($yhash[$line]);
			if ($this->xchanged[$xi])
			{
				continue;
			}

			$this->xv[] = $line;
			$this->xind[] = $xi;
		}

		// Find the LCS.
		$this->_compareseq(0, count($this->xv), 0, count($this->yv));

		// Merge edits when possible.
		$this->_shift_boundaries($from_lines, $this->xchanged, $this->ychanged);
		$this->_shift_boundaries($to_lines, $this->ychanged, $this->xchanged);

		// Compute the edit operations.
		$edits = [];
		$xi = $yi = 0;

		while ($xi < $n_from || $yi < $n_to)
		{
			self::USE_ASSERTS && assert($yi < $n_to || $this->xchanged[$xi]);
			self::USE_ASSERTS && assert($xi < $n_from || $this->ychanged[$yi]);

			// Skip matching "snake".
			$copy = [];
			while ($xi < $n_from && $yi < $n_to
				&& empty($this->xchanged[$xi]) && empty($this->ychanged[$yi]))
			{
				$copy[] = $from_lines[$xi++];
				++$yi;
			}

			if ($copy)
			{
				$edits[] = new DiffOpCopy($copy);
			}

			// Find deletes & adds.
			$delete = [];
			while ($xi < $n_from && !empty($this->xchanged[$xi]))
			{
				$delete[] = $from_lines[$xi++];
			}

			$add = [];
			while ($yi < $n_to && !empty($this->ychanged[$yi]))
			{
				$add[] = $to_lines[$yi++];
			}

			if ($delete && $add)
			{
				$edits[] = new DiffOpChange($delete, $add);
			}
			elseif ($delete)
			{
				$edits[] = new DiffOpDelete($delete);
			}
			elseif ($add)
			{
				$edits[] = new DiffOpAdd($add);
			}
		}

		return $edits;
	}

	/**
	 * Divide the LCS of the sequences [XOFF, XLIM) and [YOFF, YLIM) into
	 * NCHUNKS approximately equally sized segments.
	 *
	 * @return array{0: int, 1: array<int, array{0: int, 1: int}>}
	 */
	private function _diag(int $xoff, int $xlim, int $yoff, int $ylim, int $nchunks): array
	{
		$flip = false;

		if ($xlim - $xoff > $ylim - $yoff)
		{
			// Things seem faster when the shortest sequence is in X.
			$flip = true;
			[$xoff, $xlim, $yoff, $ylim] = [$yoff, $ylim, $xoff, $xlim];
		}

		$ymatches = [];
		if ($flip)
		{
			for ($i = $ylim - 1; $i >= $yoff; $i--)
			{
				$ymatches[$this->xv[$i]][] = $i;
			}
		}
		else
		{
			for ($i = $ylim - 1; $i >= $yoff; $i--)
			{
				$ymatches[$this->yv[$i]][] = $i;
			}
		}

		$this->lcs = 0;
		$this->seq[0] = $yoff - 1;
		$this->in_seq = [];
		$ymids = [];
		$ymids[0] = [];

		$numer = $xlim - $xoff + $nchunks - 1;
		$x = $xoff;
		for ($chunk = 0; $chunk < $nchunks; $chunk++)
		{
			if ($chunk > 0)
			{
				for ($i = 0; $i <= $this->lcs; $i++)
				{
					$ymids[$i][$chunk - 1] = $this->seq[$i];
				}
			}

			$x1 = $xoff + (int) (($numer + ($xlim - $xoff) * $chunk) / $nchunks);
			for (; $x < $x1; $x++)
			{
				$line = $flip ? $this->yv[$x] : $this->xv[$x];
				if (empty($ymatches[$line]))
				{
					continue;
				}
				$matches = $ymatches[$line];
				$found_empty = false;
				foreach ($matches as $y)
				{
					if (!$found_empty)
					{
						if (empty($this->in_seq[$y]))
						{
							$k = $this->_lcs_pos($y);
							self::USE_ASSERTS && assert($k > 0);
							$ymids[$k] = $ymids[$k - 1];
							$found_empty = true;
						}
					}
					else
					{
						if ($y > $this->seq[$k - 1])
						{
							self::USE_ASSERTS && assert($y < $this->seq[$k]);
							// Optimization: next match replaces previous.
							$this->in_seq[$this->seq[$k]] = false;
							$this->seq[$k] = $y;
							$this->in_seq[$y] = 1;
						}
						elseif (empty($this->in_seq[$y]))
						{
							$k = $this->_lcs_pos($y);
							self::USE_ASSERTS && assert($k > 0);
							$ymids[$k] = $ymids[$k - 1];
						}
					}
				}
			}
		}

		$seps = [];
		$seps[] = $flip ? [$yoff, $xoff] : [$xoff, $yoff];
		$ymid = $ymids[$this->lcs];
		for ($n = 0; $n < $nchunks - 1; $n++)
		{
			$x1 = $xoff + (int) (($numer + ($xlim - $xoff) * $n) / $nchunks);
			$y1 = $ymid[$n] + 1;
			$seps[] = $flip ? [$y1, $x1] : [$x1, $y1];
		}

		$seps[] = $flip ? [$ylim, $xlim] : [$xlim, $ylim];

		return [$this->lcs, $seps];
	}

	private function _lcs_pos(int $ypos): int
	{
		$end = $this->lcs;

		if ($end === 0 || $ypos > $this->seq[$end])
		{
			$this->seq[++$this->lcs] = $ypos;
			$this->in_seq[$ypos] = 1;
			return $this->lcs;
		}

		$beg = 1;
		while ($beg < $end)
		{
			$mid = (int) (($beg + $end) / 2);
			if ($ypos > $this->seq[$mid])
			{
				$beg = $mid + 1;
			}
			else
			{
				$end = $mid;
			}
		}

		self::USE_ASSERTS && assert($ypos !== $this->seq[$end]);

		$this->in_seq[$this->seq[$end]] = false;
		$this->seq[$end] = $ypos;
		$this->in_seq[$ypos] = 1;

		return $end;
	}

	/**
	 * Find LCS of two sequences.
	 *
	 * The results are recorded in $this->{x,y}changed[].
	 */
	private function _compareseq(int $xoff, int $xlim, int $yoff, int $ylim): void
	{
		// Slide down the bottom initial diagonal.
		while ($xoff < $xlim && $yoff < $ylim
			&& $this->xv[$xoff] === $this->yv[$yoff])
		{
			++$xoff;
			++$yoff;
		}

		// Slide up the top initial diagonal.
		while ($xlim > $xoff && $ylim > $yoff
			&& $this->xv[$xlim - 1] === $this->yv[$ylim - 1])
		{
			--$xlim;
			--$ylim;
		}

		if ($xoff === $xlim || $yoff === $ylim)
		{
			$lcs = 0;
		}
		else
		{
			$nchunks = min(7, $xlim - $xoff, $ylim - $yoff) + 1;
			[$lcs, $seps] = $this->_diag($xoff, $xlim, $yoff, $ylim, $nchunks);
		}

		if ($lcs === 0)
		{
			// X and Y sequences have no common subsequence: mark all changed.
			while ($yoff < $ylim)
			{
				$this->ychanged[$this->yind[$yoff++]] = 1;
			}
			while ($xoff < $xlim)
			{
				$this->xchanged[$this->xind[$xoff++]] = 1;
			}
		}
		else
		{
			// Use the partitions to split this problem into subproblems.
			$pt1 = $seps[0];
			for ($i = 1, $n = count($seps); $i < $n; $i++)
			{
				$pt2 = $seps[$i];
				$this->_compareseq($pt1[0], $pt2[0], $pt1[1], $pt2[1]);
				$pt1 = $pt2;
			}
		}
	}

	/**
	 * Adjust inserts/deletes of identical lines to join changes.
	 *
	 * Extracted verbatim from analyze.c (GNU diffutils-2.7).
	 *
	 * @param array<int, string> $lines
	 * @param array<int, bool>   $changed
	 * @param array<int, bool>   $other_changed
	 */
	private function _shift_boundaries(array $lines, array &$changed, array $other_changed): void
	{
		$i = 0;
		$j = 0;

		self::USE_ASSERTS && assert(count($lines) === count($changed));
		$len = count($lines);
		$other_len = count($other_changed);

		while (true)
		{
			/*
			 * Scan forwards to find beginning of another run of changes.
			 * Also keep track of the corresponding point in the other file.
			 *
			 * Throughout this code, $i and $j are adjusted together so that
			 * the first $i elements of $changed and the first $j elements
			 * of $other_changed both contain the same number of zeros
			 * (unchanged lines).
			 */
			while ($j < $other_len && !empty($other_changed[$j]))
			{
				$j++;
			}

			while ($i < $len && empty($changed[$i]))
			{
				self::USE_ASSERTS && assert($j < $other_len && empty($other_changed[$j]));
				$i++;
				$j++;
				while ($j < $other_len && !empty($other_changed[$j]))
				{
					$j++;
				}
			}

			if ($i === $len)
			{
				break;
			}

			$start = $i;

			// Find the end of this run of changes.
			while (++$i < $len && !empty($changed[$i]))
			{
				continue;
			}

			do
			{
				$runlength = $i - $start;

				/*
				 * Move the changed region back, so long as the
				 * previous unchanged line matches the last changed one.
				 * This merges with previous changed regions.
				 */
				while ($start > 0 && $lines[$start - 1] === $lines[$i - 1])
				{
					$changed[--$start] = 1;
					$changed[--$i] = false;
					while ($start > 0 && !empty($changed[$start - 1]))
					{
						$start--;
					}
					self::USE_ASSERTS && assert($j > 0);
					while ($other_changed[--$j])
					{
						continue;
					}
					self::USE_ASSERTS && assert($j >= 0 && empty($other_changed[$j]));
				}

				$corresponding = $j < $other_len ? $i : $len;

				/*
				 * Move the changed region forward, so long as the
				 * first changed line matches the following unchanged one.
				 */
				while ($i < $len && $lines[$start] === $lines[$i])
				{
					$changed[$start++] = false;
					$changed[$i++] = 1;
					while ($i < $len && !empty($changed[$i]))
					{
						$i++;
					}

					self::USE_ASSERTS && assert($j < $other_len && empty($other_changed[$j]));
					$j++;
					if ($j < $other_len && !empty($other_changed[$j]))
					{
						$corresponding = $i;
						while ($j < $other_len && !empty($other_changed[$j]))
						{
							$j++;
						}
					}
				}
			} while ($runlength !== $i - $start);

			while ($corresponding < $i)
			{
				$changed[--$start] = 1;
				$changed[--$i] = 0;
				self::USE_ASSERTS && assert($j > 0);
				while ($other_changed[--$j])
				{
					continue;
				}
				self::USE_ASSERTS && assert($j >= 0 && empty($other_changed[$j]));
			}
		}
	}
}
