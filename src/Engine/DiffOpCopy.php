<?php

declare(strict_types=1);

namespace Diff\Engine;

class DiffOpCopy extends DiffOp
{
	public string $type = 'copy';

	/**
	 * @param array<int, string>      $orig
	 * @param array<int, string>|false $final
	 */
	public function __construct(array $orig, array|false $final = false)
	{
		if (!is_array($final))
		{
			$final = $orig;
		}

		$this->orig = $orig;
		$this->final = $final;
	}
}
