<?php

declare(strict_types=1);

namespace Diff\Engine;

class DiffOp
{
	public string $type = '';
	/** @var array<int, string>|false */
	public array|false $orig = false;
	/** @var array<int, string>|false */
	public array|false $final = false;

	public function norig(): int
	{
		return $this->orig ? count($this->orig) : 0;
	}

	public function nfinal(): int
	{
		return $this->final ? count($this->final) : 0;
	}
}
