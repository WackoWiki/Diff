<?php

declare(strict_types=1);

namespace Diff\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Diff\Engine\DiffOpCopy;

final class DiffOpCopyTest extends TestCase
{
    public function test_type_is_copy(): void
    {
        $op = new DiffOpCopy(['line']);

        $this->assertSame('copy', $op->type);
    }

    public function test_orig_contains_lines(): void
    {
        $op = new DiffOpCopy(['a', 'b', 'c']);

        $this->assertSame(['a', 'b', 'c'], $op->orig);
    }

    public function test_final_defaults_to_same_as_orig(): void
    {
        $op = new DiffOpCopy(['a', 'b']);

        $this->assertSame(['a', 'b'], $op->final);
    }

    public function test_final_can_be_overridden(): void
    {
        $op = new DiffOpCopy(['a', 'b'], ['c', 'd']);

        $this->assertSame(['a', 'b'], $op->orig);
        $this->assertSame(['c', 'd'], $op->final);
    }

    public function test_final_false_is_replaced_with_orig(): void
    {
        $op = new DiffOpCopy(['a', 'b'], false);

        $this->assertSame(['a', 'b'], $op->final);
    }

    public function test_norig_and_nfinal_match_for_default_copy(): void
    {
        $op = new DiffOpCopy(['a', 'b', 'c']);

        $this->assertSame(3, $op->norig());
        $this->assertSame(3, $op->nfinal());
    }
}
