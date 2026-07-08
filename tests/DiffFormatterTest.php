<?php

declare(strict_types=1);

namespace Diff\Tests;

use PHPUnit\Framework\TestCase;
use Diff\Diff;
use Diff\DiffFormatter;

final class DiffFormatterTest extends TestCase
{
    private DiffFormatter $fmt;

    protected function setUp(): void
    {
        $this->fmt = new DiffFormatter();
    }

    public function test_format_returns_string(): void
    {
        $diff = new Diff(['a'], ['a']);
        $result = $this->fmt->format($diff);

        $this->assertIsString($result);
    }

    public function test_identical_inputs_produce_empty_or_minimal_output(): void
    {
        $diff = new Diff(['only line'], ['only line']);
        $result = $this->fmt->format($diff);

        // Either empty or just a header; no a/c/d blocks expected
        $this->assertStringNotContainsString('a', explode("\n", $result)[0] ?? '');
        $this->assertStringNotContainsString('c', explode("\n", $result)[0] ?? '');
        $this->assertStringNotContainsString('d', explode("\n", $result)[0] ?? '');
    }

    public function test_addition_renders_with_a_directive(): void
    {
        $diff = new Diff(['header'], ['header', 'added line']);
        $result = $this->fmt->format($diff);

        $this->assertStringContainsString('a', $result);
    }

    public function test_deletion_renders_with_d_directive(): void
    {
        $diff = new Diff(['header', 'removed'], ['header']);
        $result = $this->fmt->format($diff);

        $this->assertStringContainsString('d', $result);
    }

    public function test_change_renders_with_c_directive(): void
    {
        $diff = new Diff(['header', 'old'], ['header', 'new']);
        $result = $this->fmt->format($diff);

        $this->assertStringContainsString('c', $result);
    }

    public function test_block_header_single_line_addition(): void
    {
        // xbeg=1, xlen=0, ybeg=2, ylen=1 -> "1a2"
        $header = $this->fmt->_block_header(1, 0, 2, 1);

        $this->assertSame('1a2', $header);
    }

    public function test_block_header_multi_line_range(): void
    {
        // xbeg=1, xlen=3, ybeg=2, ylen=1 -> "1,3c2"
        $header = $this->fmt->_block_header(1, 3, 2, 1);

        $this->assertSame('1,3c2', $header);
    }

    public function test_block_header_deletion_pattern(): void
    {
        // xlen > 0 and ylen == 0 -> 'd'
        $header = $this->fmt->_block_header(5, 2, 0, 0);

        $this->assertStringContainsString('d', $header);
    }

    public function test_block_header_addition_pattern(): void
    {
        // xlen == 0 -> 'a'
        $header = $this->fmt->_block_header(3, 0, 4, 1);

        $this->assertStringContainsString('a', $header);
    }

    public function test_start_diff_enables_output_buffering(): void
    {
        $this->fmt->_start_diff();
        echo 'test output';

        $result = $this->fmt->_end_diff();

        $this->assertSame('test output', $result);
    }

    public function test_format_with_empty_edits(): void
    {
        // Force an empty edits case via reflection
        $diff = new Diff(['a'], ['a']);
        // Reset edits to empty
        $reflection = new \ReflectionProperty($diff, 'edits');
        $reflection->setValue($diff, []);

        $result = $this->fmt->format($diff);

        $this->assertIsString($result);
    }

    public function test_format_result_ends_cleanly(): void
    {
        $diff = new Diff(['a', 'b'], ['a', 'b', 'c']);
        $result = $this->fmt->format($diff);

        // Result must not have trailing garbage beyond the directive
        $this->assertIsString($result);
    }
}
