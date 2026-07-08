<?php

declare(strict_types=1);

namespace Diff\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Diff\Engine\DiffOpChange;

final class DiffOpChangeTest extends TestCase
{
    public function test_type_is_change(): void
    {
        $op = new DiffOpChange(['old'], ['new']);

        $this->assertSame('change', $op->type);
    }

    public function test_orig_contains_old_lines(): void
    {
        $op = new DiffOpChange(['old1', 'old2'], ['new1']);

        $this->assertSame(['old1', 'old2'], $op->orig);
    }

    public function test_final_contains_new_lines(): void
    {
        $op = new DiffOpChange(['old'], ['new1', 'new2']);

        $this->assertSame(['new1', 'new2'], $op->final);
    }

    public function test_norig_returns_count_of_removed_lines(): void
    {
        $op = new DiffOpChange(['a', 'b', 'c'], ['x']);

        $this->assertSame(3, $op->norig());
    }

    public function test_nfinal_returns_count_of_added_lines(): void
    {
        $op = new DiffOpChange(['a'], ['x', 'y', 'z', 'w']);

        $this->assertSame(4, $op->nfinal());
    }

    public function test_one_to_one_change(): void
    {
        $op = new DiffOpChange(['foo'], ['bar']);

        $this->assertSame(1, $op->norig());
        $this->assertSame(1, $op->nfinal());
    }
}
