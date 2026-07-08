<?php

declare(strict_types=1);

namespace Diff\Engine;

class DiffOpAdd extends DiffOp
{
	public string $type = 'add';

	/**
	 * @param array<int, string> $lines
	 */
	public function __construct(array $lines)
	{
		$this->final = $lines;
		$this->orig = false;
	}
}
