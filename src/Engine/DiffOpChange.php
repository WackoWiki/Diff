<?php

declare(strict_types=1);

namespace Diff\Engine;

class DiffOpChange extends DiffOp
{
	public string $type = 'change';

	/**
	 * @param array<int, string> $orig
	 * @param array<int, string> $final
	 */
	public function __construct(array $orig, array $final)
	{
		$this->orig = $orig;
		$this->final = $final;
	}
}
