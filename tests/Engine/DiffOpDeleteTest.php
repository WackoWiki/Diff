<?php

declare(strict_types=1);

namespace Diff\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Diff\Engine\DiffOpDelete;

final class DiffOpDeleteTest extends TestCase
{
    public function test_type_is_delete(): void
    {
        $op = new DiffOpDelete(['removed']);

        $this->assertSame('delete', $op->type);
    }

    public function test_orig_contains_lines(): void
    {
        $op = new DiffOpDelete(['line1', 'line2', 'line3']);

        $this->assertSame(['line1', 'line2', 'line3'], $op->orig);
    }

    public function test_final_is_false(): void
    {
        $op = new DiffOpDelete(['removed']);

        $this->assertFalse($op->final);
    }

    public function test_norig_returns_count_of_deleted_lines(): void
    {
        $op = new DiffOpDelete(['a', 'b', 'c', 'd', 'e']);

        $this->assertSame(5, $op->norig());
    }

    public function test_nfinal_returns_zero_for_delete(): void
    {
        $op = new DiffOpDelete(['a', 'b']);

        $this->assertSame(0, $op->nfinal());
    }
}
