<?php

declare(strict_types=1);

namespace Diff\Engine;

class DiffOpDelete extends DiffOp
{
	public string $type = 'delete';

	/**
	 * @param array<int, string> $lines
	 */
	public function __construct(array $lines)
	{
		$this->orig = $lines;
		$this->final = false;
	}
}
