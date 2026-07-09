# WackoWiki Diff

A modernized PHP diff library originally derived from WackoWiki's PHP port of
Geoffrey T. Dairiki's `Algorithm::Diff` Perl module, itself based on Ned Konz's
work and the GNU diff algorithm from François Pinard (1992).

This fork modernizes the codebase for PHP 8.3+ with strict types, proper namespacing,
and a full PHPUnit 11 test suite.

---

## Features

- **Strict types** throughout — full PHP 8.3+ compatibility
- **Word-level diffs** — for inline comparison via `Diff\Side`
- **Line-level diffs** — classic GNU diff format via `Diff\Diff` + `Diff\DiffFormatter`
- **Fully tested** — 95+ unit tests covering all classes
- **Zero dependencies** — pure PHP, no external libraries
- **GPL-2.0-or-later** — open source license

---

## Requirements

- **PHP 8.3** or higher
- **Composer** (for installation and testing)
- **PHPUnit 11** (development only)

---

## Installation

Install via Composer:

```bash
composer require wackowiki/diff
```

Or add to your `composer.json`:

```json
{
    "require": {
        "wackowiki/diff": "^1.0"
    }
}
```

---

## Usage

### 1. Line-Level Diff (Classic)

Compare two files line-by-line and output GNU diff format:

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Diff\Diff;
use Diff\DiffFormatter;

$from_lines = [
	"The quick brown fox",
	"jumps over the lazy dog",
];

$to_lines = [
	"The quick brown fox",
	"jumps over the lazy cat",
	"and runs away",
];

// Compute the diff
$diff = new Diff($from_lines, $to_lines);

// Format as classic GNU diff output
$formatter = new DiffFormatter();
$output = $formatter->format($diff);

echo $output;
```

**Output:**
```
1,2c1,3
< The quick brown fox
< jumps over the lazy dog
---
> The quick brown fox
> jumps over the lazy cat
> and runs away
```

### 2. Inspect Diff Operations

Each `Diff` object exposes its edit operations directly:

```php
<?php

declare(strict_types=1);

use Diff\Diff;
use Diff\Engine\DiffOpCopy;
use Diff\Engine\DiffOpAdd;
use Diff\Engine\DiffOpDelete;
use Diff\Engine\DiffOpChange;

$diff = new Diff(['a', 'b', 'c'], ['a', 'X', 'c']);

foreach ($diff->edits as $edit)
{
	echo match (true)
	{
		$edit instanceof DiffOpCopy   => "COPY: " . implode(', ', $edit->orig) . PHP_EOL,
		$edit instanceof DiffOpAdd    => "ADD: "  . implode(', ', $edit->final) . PHP_EOL,
		$edit instanceof DiffOpDelete => "DEL: "  . implode(', ', $edit->orig) . PHP_EOL,
		$edit instanceof DiffOpChange => "CHANGE: -[" . implode(', ', $edit->orig) .
										 "] +[" . implode(', ', $edit->final) . "]" . PHP_EOL,
	};
}
```

**Output:**
```
COPY: a
CHANGE: -[b] +[X]
COPY: c
```

### 3. Word-Level Diff (Inline Comparison)

For word-level diffs (useful for inline comparison UIs):

```php
<?php

declare(strict_types=1);

use Diff\Side;
use Diff\Diff;
use Diff\DiffFormatter;

$text_a = "The quick brown fox";
$text_b = "The slow brown fox";

$side_a = new Side($text_a);
$side_b = new Side($text_b);

// Split into words for comparison
$body_a = '';
$side_a->split_file_into_words($body_a);

$body_b = '';
$side_b->split_file_into_words($body_b);

// Compute word-level diff
$diff = new Diff(explode("\n", $body_a), explode("\n", $body_b));

// Format and decode for inline markup
$formatter = new DiffFormatter();
$output = new Side($formatter->format($diff));

$resync_left  = 0;
$resync_right = 0;
$output_text  = '';
$count_total_right = $side_b->getposition();
$side_a->init();
$side_b->init();

while (!$output->isend())
{
	$output->skip_line();

	if ($output->decode_directive_line())
	{
		$argument = $output->getargument();
		$letter   = $output->getdirective();

		$resync_left = match ($letter)
		{
			'a'        => $argument[0],
			'd', 'c'   => $argument[0] - 1,
		};
		$resync_right = match ($letter)
		{
			'a'  => $argument[2] - 1,
			'd'  => $argument[2],
			'c'  => $argument[2] - 1,
		};

		$side_a->skip_until_ordinal($resync_left);
		$side_b->copy_until_ordinal($resync_right, $output_text);

		if ($letter === 'd' || $letter === 'c')
		{
			$side_a->copy_whitespace($output_text);
			$output_text .= '**[DELETED]** ';
			$side_a->copy_word($output_text);
			$side_a->copy_until_ordinal($argument[1], $output_text);
		}

		if ($letter === 'a' || $letter === 'c')
		{
			$side_b->copy_whitespace($output_text);
			$output_text .= ' **[INSERTED]** ';
			$side_b->copy_word($output_text);
			$side_b->copy_until_ordinal($argument[3], $output_text);
		}
	}
}

$side_b->copy_until_ordinal($count_total_right, $output_text);
echo $output_text;
```

**Output:**
```
The [DELETED]quick **[INSERTED]**slow brown fox
```

### 4. Empty Inputs

The library safely handles empty inputs:

```php
<?php

use Diff\Diff;

$diff = new Diff([], []);
assert($diff->edits === []); // No edits

$diff = new Diff([], ['new content']);
// Returns one DiffOpAdd operation
```

---

## Architecture

### Class Hierarchy

```
Diff\Diff
└── uses Diff\Engine\DiffEngine (computes LCS-based diff)
    └── produces Diff\Engine\DiffOp* objects

Diff\DiffFormatter
└── formats Diff\Diff into classic GNU diff output

Diff\Side
└── parses diff output / splits text into words
```

### Core Classes

| Class | Purpose |
|-------|---------|
| `Diff\Diff` | Container for edit operations between two sequences |
| `Diff\DiffFormatter` | Formats a `Diff` into GNU-style output |
| `Diff\Side` | Parser/iterator for word-level diff operations |
| `Diff\Engine\DiffEngine` | LCS algorithm implementation |
| `Diff\Engine\DiffOp` | Base class for diff operations |
| `Diff\Engine\DiffOpCopy` | Unchanged lines |
| `Diff\Engine\DiffOpAdd` | Added lines |
| `Diff\Engine\DiffOpDelete` | Deleted lines |
| `Diff\Engine\DiffOpChange` | Replaced lines |

---

## Testing

This library has a comprehensive PHPUnit 11 test suite covering all 9 classes.

### Run Tests

```bash
# Install dev dependencies
composer install

# Run all tests
./vendor/bin/phpunit

# Run with verbose output
./vendor/bin/phpunit --testdox

# Run specific test class
./vendor/bin/phpunit tests/SideTest.php

# Run with coverage report (requires Xdebug or PCOV)
./vendor/bin/phpunit --coverage-text
```

### Test Coverage

The test suite includes:

- **95+ test cases** covering all public APIs
- **Edge cases**: empty inputs, unicode, state reset between instances
- **Algorithm validation**: LCS behavior, duplicate lines, boundary detection
- **Strict types verification**: ensures no `null` coercion regressions

---

## Development

### Setup

```bash
# Clone the repository
git clone https://github.com/wackowiki/diff.git
cd diff

# Install dependencies
composer install
```

### Project Structure

```
.
├── src/
│   ├── Diff.php
│   ├── DiffFormatter.php
│   ├── Side.php
│   └── Engine/
│       ├── DiffEngine.php
│       ├── DiffOp.php
│       ├── DiffOpAdd.php
│       ├── DiffOpChange.php
│       ├── DiffOpCopy.php
│       └── DiffOpDelete.php
├── tests/
│   ├── bootstrap.php
│   ├── DiffTest.php
│   ├── DiffFormatterTest.php
│   ├── SideTest.php
│   └── Engine/
│       ├── DiffEngineTest.php
│       ├── DiffOpTest.php
│       ├── DiffOpAddTest.php
│       ├── DiffOpChangeTest.php
│       ├── DiffOpCopyTest.php
│       └── DiffOpDeleteTest.php
├── composer.json
├── phpunit.xml
└── README.md
```

### Coding Standards

- **PHP 8.3+ strict types** — every file starts with `declare(strict_types=1);`
- **PSR-4** — namespacing follows `Diff\` and `Diff\Tests\` conventions
- **Type declarations** — all parameters and returns are typed
- **No dependencies** — pure PHP, no runtime dependencies

---

## Credits & History

This library traces its lineage through several iterations:

### Original Authors

- **François Pinard** (1992) — Original GNU `diff` algorithm
- **Geoffrey T. Dairiki** (2000-2001) — Perl `Algorithm::Diff` implementation
- **David DELON** (2002-2004) — Initial PHP port
- **Patrick PAUL** (2002) — Contributor to PHP port
- **Eric FELDSTEIN** (2003) — Contributor to PHP port

### Modernization (2024+)

The codebase was modernized for PHP 8.3+ with:
- Strict type declarations throughout
- Proper namespacing under `Diff\`
- Complete PHPUnit 11 test suite
- Modernized method signatures and return types

---

## License

GNU General Public License v2.0 or later — see [LICENSE](LICENSE) for details.

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.

---

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/improvement`)
3. Add tests for any new functionality
4. Ensure all tests pass (`./vendor/bin/phpunit`)
5. Submit a pull request

---

## Support

- 🐛 **Issues**: https://github.com/wackowiki/diff/issues
- 💬 **Discussions**: https://github.com/wackowiki/diff/discussions
- 📖 **WackoWiki**: https://wackowiki.org
