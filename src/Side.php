<?php

declare(strict_types=1);

namespace Diff;

/**
 * Side: a string for wdiff.
 */
class Side
{
	public int $position;
	public int $cursor;
	public string $content;
	public string $character;
	public string $directive;
	/** @var array<int, int|string> */
	public array $argument;
	public int $length;

	public function __construct(string $content)
	{
		$this->content		= $content;
		$this->position		= 0;
		$this->cursor		= 0;
		$this->directive	= '';
		$this->argument		= [];
		$this->length		= mb_strlen($this->content);
		$this->character	= mb_substr($this->content, 0, 1) ?: '';
	}

	public function getposition(): int
	{
		return $this->position;
	}

	public function getcharacter(): string
	{
		return $this->character;
	}

	public function getdirective(): string
	{
		return $this->directive;
	}

	/**
	 * @return array<int, int|string>
	 */
	public function getargument(): array
	{
		return $this->argument;
	}

	public function nextchar(): void
	{
		$this->cursor++;
		$this->character = mb_substr($this->content, $this->cursor, 1) ?: '';
	}

	/**
	 * @param-out string $out
	 */
	public function copy_until_ordinal(int $ordinal, string &$out): void
	{
		while ($this->position < $ordinal)
		{
			$this->copy_whitespace($out);
			$this->copy_word($out);
		}
	}

	public function skip_until_ordinal(int $ordinal): void
	{
		while ($this->position < $ordinal)
		{
			$this->skip_whitespace();
			$this->skip_word();
		}
	}

	/**
	 * @param-out string $out
	 */
	public function split_file_into_words(string &$out): void
	{
		while (!$this->isend())
		{
			$this->skip_whitespace();
			if ($this->isend())
			{
				break;
			}
			$this->copy_word($out);
			$out .= "\n";
		}
	}

	public function init(): void
	{
		$this->position		= 0;
		$this->cursor		= 0;
		$this->directive	= '';
		$this->argument		= [];
		$this->character	= mb_substr($this->content, 0, 1) ?: '';
	}

	public function isspace(string $char): bool
	{
		return (bool) preg_match('/([[:space:]]|\*)/u', $char);
	}

	public function isdigit(string $char): bool
	{
		return (bool) preg_match('/[[:digit:]]/u', $char);
	}

	public function isend(): bool
	{
		return $this->cursor >= $this->length;
	}

	/**
	 * @param-out string $out
	 */
	public function copy_whitespace(string &$out): void
	{
		while (!$this->isend() && $this->isspace($this->character))
		{
			$out .= $this->character;
			$this->nextchar();
		}
	}

	public function skip_whitespace(): void
	{
		while (!$this->isend() && $this->isspace($this->character))
		{
			$this->nextchar();
		}
	}

	public function skip_line(): void
	{
		while (!$this->isend() && !$this->isdigit($this->character))
		{
			while (!$this->isend() && $this->character !== "\n")
			{
				$this->nextchar();
			}

			if ($this->character === "\n")
			{
				$this->nextchar();
			}
		}
	}

	/**
	 * @param-out string $out
	 */
	public function copy_word(string &$out): void
	{
		while (!$this->isend() && !($this->isspace($this->character)))
		{
			$out .= $this->character;
			$this->nextchar();
		}

		$this->position++;
	}

	public function skip_word(): void
	{
		while (!$this->isend() && !($this->isspace($this->character)))
		{
			$this->nextchar();
		}

		$this->position++;
	}

	public function decode_directive_line(): bool
	{
		$value = 0;
		$state = 0;
		$error = false;

		while (!$error && $state < 4)
		{
			// FIX Issue #1: Reset $value at start of each state iteration
			$value = 0;

			if ($this->isdigit($this->character))
			{
				// FIX Issue #2: Inner while loop properly consumes all digits
				while ($this->isdigit($this->character))
				{
					$value = 10 * $value + (int) ($this->character - '0');
					$this->nextchar();
				}
			}
			elseif ($state !== 1 && $state !== 3)
			{
				$error = true;
			}

			$this->argument[$state] = $value;

			switch ($state)
			{
				case 0:
				case 2:
					if ($this->character === ',')
					{
						$this->nextchar();
					}
					break;

				case 1:
					if ($this->character === 'a' || $this->character === 'd' || $this->character === 'c')
					{
						$this->directive = $this->character;
						$this->nextchar();
					}
					else
					{
						$error = true;
					}
					break;

				case 3:
					if ($this->character !== "\n")
					{
						$error = true;
					}
					break;
			}

			$state++;
		}

		while ((!$this->isend()) && ($this->character !== "\n"))
		{
			$this->nextchar();
		}

		if ($this->character === "\n")
		{
			$this->nextchar();
		}

		return !$error;
	}
}
