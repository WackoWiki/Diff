<?php

declare(strict_types=1);

namespace Diff;

/**
 * A class to format Diffs.
 *
 * This class formats the diff in classic diff format.
 * It is intended that this class be customized via inheritance,
 * to obtain fancier outputs.
 */
class DiffFormatter
{
	/**
	 * Format a diff.
	 *
	 * @param \Diff\Diff $diff A Diff object.
	 * @return string The formatted output.
	 */
	public function format(Diff $diff): string
	{
		$xi = $yi = 1;
		$block = false;
		$context = [];

		$this->_start_diff();

		foreach ($diff->edits as $edit)
		{
			if ($edit->type === 'copy')
			{
				if (is_array($block))
				{
					if (count($edit->orig) <= 0)
					{
						$block[] = $edit;
					}
					else
					{
						$this->_block($x0, $xi - $x0, $y0, $yi - $y0, $block);
						$block = false;
					}
				}
			}
			else
			{
				if (!is_array($block))
				{
					$x0 = $xi;
					$y0 = $yi;
					$block = [];
				}

				$block[] = $edit;
			}

			if ($edit->orig)
			{
				$xi += count($edit->orig);
			}

			if ($edit->final)
			{
				$yi += count($edit->final);
			}
		}

		if (is_array($block))
		{
			$this->_block($x0, $xi - $x0, $y0, $yi - $y0, $block);
		}

		return $this->_end_diff();
	}

	public function _block(int $xbeg, int $xlen, int $ybeg, int $ylen, array &$edits): void
	{
		$this->_start_block($this->_block_header($xbeg, $xlen, $ybeg, $ylen));
	}

	public function _start_diff(): void
	{
		ob_start();
	}

	public function _end_diff(): string
	{
		$val = ob_get_contents();
		ob_end_clean();

		return (string) $val;
	}

	public function _block_header(int $xbeg, int $xlen, int $ybeg, int $ylen): string
	{
		$xbeg_str = (string) $xbeg;
		$ybeg_str = (string) $ybeg;

		if ($xlen > 1)
		{
			$xbeg_str .= ',' . ($xbeg + $xlen - 1);
		}

		if ($ylen > 1)
		{
			$ybeg_str .= ',' . ($ybeg + $ylen - 1);
		}

		return $xbeg_str . ($xlen ? ($ylen ? 'c' : 'd') : 'a') . $ybeg_str;
	}

	public function _start_block(string $header): void
	{
		echo $header . "\n";
	}
}
