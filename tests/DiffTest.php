<?php

declare(strict_types=1);

namespace Diff\Tests;

use PHPUnit\Framework\TestCase;
use Diff\Diff;
use Diff\Engine\DiffOpCopy;
use Diff\Engine\DiffOpAdd;
use Diff\Engine\DiffOpDelete;

final class DiffTest extends TestCase
{
    public function test_constructor_populates_edits_property(): void
    {
        $diff = new Diff(['a'], ['a']);

        $this->assertIsArray($diff->edits);
        $this->assertNotEmpty($diff->edits);
    }

    public function test_identical_inputs_produce_copy_only(): void
    {
        $diff = new Diff(
            ["line one", "line two", "line three"],
            ["line one", "line two", "line three"]
        );

        $this->assertCount(1, $diff->edits);
        $this->assertInstanceOf(DiffOpCopy::class, $diff->edits[0]);
    }

    public function test_addition_at_end(): void
    {
        $diff = new Diff(
            ["original"],
            ["original", "appended"]
        );

        $hasAdd = false;
        foreach ($diff->edits as $edit) {
            if ($edit instanceof DiffOpAdd) {
                $hasAdd = true;
                $this->assertSame(["appended"], $edit->final);
            }
        }
        $this->assertTrue($hasAdd);
    }

    public function test_deletion_at_end(): void
    {
        $diff = new Diff(
            ["keep", "remove"],
            ["keep"]
        );

        $hasDelete = false;
        foreach ($diff->edits as $edit) {
            if ($edit instanceof DiffOpDelete) {
                $hasDelete = true;
                $this->assertSame(["remove"], $edit->orig);
            }
        }
        $this->assertTrue($hasDelete);
    }

    public function test_empty_input_lists(): void
    {
        $diff = new Diff([], []);

        $this->assertSame([], $diff->edits);
    }

    public function test_from_empty_to_content(): void
    {
        $diff = new Diff([], ["brand", "new", "content"]);

        $this->assertCount(1, $diff->edits);
        $this->assertInstanceOf(DiffOpAdd::class, $diff->edits[0]);
        $this->assertSame(["brand", "new", "content"], $diff->edits[0]->final);
    }

    public function test_from_content_to_empty(): void
    {
        $diff = new Diff(["removed", "all"], []);

        $this->assertCount(1, $diff->edits);
        $this->assertInstanceOf(DiffOpDelete::class, $diff->edits[0]);
    }

    public function test_large_input_is_processed(): void
    {
        $from = range(1, 100);
        $to = range(1, 50);

        $diff = new Diff(array_map('strval', $from), array_map('strval', $to));

        $this->assertNotEmpty($diff->edits);
    }

    public function test_diff_is_independent_between_instances(): void
    {
        $diff1 = new Diff(['a'], ['b']);
        $diff2 = new Diff(['a'], ['b']);

        // Two separate instances should produce equivalent but independent results
        $this->assertCount(count($diff1->edits), $diff2->edits);
        $this->assertSame($diff1->edits[0]->type, $diff2->edits[0]->type);
    }
}
