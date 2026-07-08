<?php

declare(strict_types=1);

namespace Diff\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Diff\Engine\DiffEngine;
use Diff\Engine\DiffOpAdd;
use Diff\Engine\DiffOpChange;
use Diff\Engine\DiffOpCopy;
use Diff\Engine\DiffOpDelete;

final class DiffEngineTest extends TestCase
{
    private function diff(array $from, array $to): array
    {
        $engine = new DiffEngine();
        return $engine->diff($from, $to);
    }

    public function test_identical_inputs_produce_single_copy(): void
    {
        $edits = $this->diff(['a', 'b', 'c'], ['a', 'b', 'c']);

        $this->assertCount(1, $edits);
        $this->assertInstanceOf(DiffOpCopy::class, $edits[0]);
        $this->assertSame(['a', 'b', 'c'], $edits[0]->orig);
    }

    public function test_empty_inputs_produce_no_edits(): void
    {
        $edits = $this->diff([], []);

        $this->assertSame([], $edits);
    }

    public function test_complete_addition(): void
    {
        $edits = $this->diff([], ['a', 'b']);

        $this->assertCount(1, $edits);
        $this->assertInstanceOf(DiffOpAdd::class, $edits[0]);
        $this->assertSame(['a', 'b'], $edits[0]->final);
    }

    public function test_complete_deletion(): void
    {
        $edits = $this->diff(['a', 'b'], []);

        $this->assertCount(1, $edits);
        $this->assertInstanceOf(DiffOpDelete::class, $edits[0]);
        $this->assertSame(['a', 'b'], $edits[0]->orig);
    }

    public function test_appended_line_is_add(): void
    {
        $edits = $this->diff(['a', 'b'], ['a', 'b', 'c']);

        $types = array_map(fn($e) => $e->type, $edits);
        $this->assertContains('add', $types);
        $this->assertContains('copy', $types);
    }

    public function test_removed_line_is_delete(): void
    {
        $edits = $this->diff(['a', 'b', 'c'], ['a', 'b']);

        $types = array_map(fn($e) => $e->type, $edits);
        $this->assertContains('delete', $types);
    }

    public function test_replaced_line_is_change(): void
    {
        $edits = $this->diff(['a', 'X', 'c'], ['a', 'Y', 'c']);

        $changeOps = array_filter($edits, fn($e) => $e->type === 'change');
        $this->assertNotEmpty($changeOps);
    }

    public function test_leading_common_lines_are_skipped_from_changes(): void
    {
        $edits = $this->diff(['common1', 'common2', 'changed'], ['common1', 'common2', 'new']);

        $copyOps = array_values(array_filter($edits, fn($e) => $e->type === 'copy'));
        $this->assertGreaterThan(0, count($copyOps));
    }

    public function test_trailing_common_lines_are_skipped_from_changes(): void
    {
        $edits = $this->diff(['changed', 'tail1', 'tail2'], ['new', 'tail1', 'tail2']);

        $copyOps = array_values(array_filter($edits, fn($e) => $e->type === 'copy'));
        $this->assertGreaterThan(0, count($copyOps));
    }

    public function test_no_redundant_change_blocks_for_adjacent_changes(): void
    {
        // Adjacent identical changes should be merged
        $edits = $this->diff(
            ['a', 'old1', 'old2', 'b'],
            ['a', 'new1', 'new2', 'b']
        );

        $changeOps = array_values(array_filter($edits, fn($e) => $e->type === 'change'));
        $this->assertCount(1, $changeOps);
        $this->assertSame(['old1', 'old2'], $changeOps[0]->orig);
        $this->assertSame(['new1', 'new2'], $changeOps[0]->final);
    }

    public function test_engine_state_is_reset_between_calls(): void
    {
        $engine = new DiffEngine();
        $first = $engine->diff(['a'], ['a', 'b']);
        $second = $engine->diff(['a'], ['a', 'b']);

        // Both calls should produce the same result, confirming no state leakage
        $this->assertCount(count($first), $second);
        $this->assertSame($first[0]->type, $second[0]->type);
    }

    public function test_lcs_handles_duplicate_lines(): void
    {
        $edits = $this->diff(
            ['a', 'b', 'a', 'b', 'a'],
            ['b', 'a', 'b', 'a', 'b']
        );

        // Just verify it doesn't throw and produces valid edits
        foreach ($edits as $edit) {
            $this->assertContains($edit->type, ['copy', 'add', 'delete', 'change']);
        }
    }
}
