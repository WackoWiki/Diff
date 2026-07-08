<?php

declare(strict_types=1);

namespace Diff\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Diff\Engine\DiffOp;

final class DiffOpTest extends TestCase
{
    public function test_default_type_is_empty_string(): void
    {
        $op = new DiffOp();

        $this->assertSame('', $op->type);
    }

    public function test_default_orig_is_false(): void
    {
        $op = new DiffOp();

        $this->assertFalse($op->orig);
    }

    public function test_default_final_is_false(): void
    {
        $op = new DiffOp();

        $this->assertFalse($op->final);
    }

    public function test_norig_returns_zero_when_orig_is_false(): void
    {
        $op = new DiffOp();

        $this->assertSame(0, $op->norig());
    }

    public function test_norig_returns_count_when_orig_is_array(): void
    {
        $op = new DiffOp();
        $op->orig = ['a', 'b', 'c'];

        $this->assertSame(3, $op->norig());
    }

    public function test_nfinal_returns_zero_when_final_is_false(): void
    {
        $op = new DiffOp();

        $this->assertSame(0, $op->nfinal());
    }

    public function test_nfinal_returns_count_when_final_is_array(): void
    {
        $op = new DiffOp();
        $op->final = ['x', 'y'];

        $this->assertSame(2, $op->nfinal());
    }
}
