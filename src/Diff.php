<?php

declare(strict_types=1);

/**
 * Copyright (C) 1992 Free Software Foundation, Inc. Francois Pinard <pinard@iro.umontreal.ca>.
 * Copyright (C) 2000, 2001 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Copyright 2002,2003,2004  David DELON
 * Copyright 2002  Patrick PAUL
 * Copyright 2003  Eric FELDSTEIN
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 */

namespace Diff;



/**
 * Class representing a 'diff' between two sequences of strings.
 */
class Diff
{
	/**
	 * @var array<int, \Diff\Engine\DiffOp>
	 */
	public array $edits;

	/**
	 * Computes diff between sequences of strings.
	 *
	 * @param array<int, string> $from_lines An array of strings (typically lines from a file).
	 * @param array<int, string> $to_lines   An array of strings.
	 */
	public function __construct(array $from_lines, array $to_lines)
	{
		$engine = new Engine\DiffEngine;
		$this->edits = $engine->diff($from_lines, $to_lines);
	}
}
