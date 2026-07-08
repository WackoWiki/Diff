<?php

declare(strict_types=1);

namespace Diff\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Diff\Engine\DiffOpAdd;

final class DiffOpAddTest extends TestCase
{
    public function test_type_is_add(): void
    {
        $op = new DiffOpAdd(['new']);

        $this->assertSame('add', $op->type);
    }

    public function test_final_contains_lines(): void
    {
        $op = new DiffOpAdd(['line1', 'line2']);

        $this->assertSame(['line1', 'line2'], $op->final);
    }

    public function test_orig_is_false(): void
    {
        $op = new DiffOpAdd(['new']);

        $this->assertFalse($op->orig);
    }

    public function test_nfinal_returns_count_of_added_lines(): void
    {
        $op = new DiffOpAdd(['a', 'b', 'c', 'd']);

        $this->assertSame(4, $op->nfinal());
    }

    public function test_norig_returns_zero_for_add(): void
    {
        $op = new DiffOpAdd(['a', 'b']);

        $this->assertSame(0, $op->norig());
    }

    public function test_empty_lines_array(): void
    {
        $op = new DiffOpAdd([]);

        $this->assertSame([], $op->final);
        $this->assertSame(0, $op->nfinal());
    }
}
