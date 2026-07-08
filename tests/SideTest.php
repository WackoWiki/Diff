<?php

declare(strict_types=1);

namespace Diff\Tests;

use PHPUnit\Framework\TestCase;
use Diff\Side;

final class SideTest extends TestCase
{
	public function test_constructor_sets_content(): void
	{
		$side = new Side('hello world');

		$this->assertSame('hello world', $side->content);
	}

	public function test_constructor_initializes_position_to_zero(): void
	{
		$side = new Side('hello');

		$this->assertSame(0, $side->position);
	}

	public function test_constructor_initializes_cursor_to_zero(): void
	{
		$side = new Side('hello');

		$this->assertSame(0, $side->cursor);
	}

	public function test_constructor_sets_first_character(): void
	{
		$side = new Side('hello');

		$this->assertSame('h', $side->character);
	}

	public function test_constructor_calculates_length(): void
	{
		$side = new Side('hello');

		$this->assertSame(5, $side->length);
	}

	public function test_constructor_handles_unicode_length(): void
	{
		$side = new Side('héllo');

		// mb_strlen counts characters, not bytes
		$this->assertSame(5, $side->length);
	}

	public function test_constructor_with_empty_string(): void
	{
		$side = new Side('');

		$this->assertSame('', $side->character);
		$this->assertSame(0, $side->length);
		$this->assertTrue($side->isend());
	}

	public function test_getposition_returns_current_position(): void
	{
		$side = new Side('hello world');

		$this->assertSame(0, $side->getposition());
	}

	public function test_getcharacter_returns_current_character(): void
	{
		$side = new Side('hello');

		$this->assertSame('h', $side->getcharacter());
	}

	public function test_nextchar_advances_cursor(): void
	{
		$side = new Side('hello');
		$side->nextchar();

		$this->assertSame(1, $side->cursor);
		$this->assertSame('e', $side->character);
	}

	public function test_nextchar_at_end_returns_empty_character(): void
	{
		$side = new Side('hi');
		$side->nextchar();
		$side->nextchar();

		$this->assertSame('', $side->character);
	}

	public function test_isend_returns_true_when_cursor_equals_length(): void
	{
		$side = new Side('hi');
		$side->nextchar();
		$side->nextchar();

		$this->assertTrue($side->isend());
	}

	public function test_isend_returns_false_when_more_chars_remain(): void
	{
		$side = new Side('hello');

		$this->assertFalse($side->isend());
	}

	public function test_isspace_recognizes_whitespace(): void
	{
		$side = new Side('x');

		$this->assertTrue($side->isspace(' '));
		$this->assertTrue($side->isspace("\t"));
		$this->assertTrue($side->isspace("\n"));
		$this->assertTrue($side->isspace('*'));
	}

	public function test_isspace_rejects_non_whitespace(): void
	{
		$side = new Side('x');

		$this->assertFalse($side->isspace('a'));
		$this->assertFalse($side->isspace('1'));
	}

	public function test_isdigit_recognizes_digits(): void
	{
		$side = new Side('0');

		$this->assertTrue($side->isdigit('0'));
		$this->assertTrue($side->isdigit('9'));
		$this->assertFalse($side->isdigit('a'));
		$this->assertFalse($side->isdigit(' '));
	}

	public function test_split_file_into_words_separates_words_by_newlines(): void
	{
		$side = new Side('hello world foo');
		$out = '';
		$side->split_file_into_words($out);

		$this->assertSame("hello\nworld\nfoo\n", $out);
	}

	public function test_split_file_into_words_skips_whitespace(): void
	{
		$side = new Side("hello   world\tfoo");
		$out = '';
		$side->split_file_into_words($out);

		$this->assertSame("hello\nworld\nfoo\n", $out);
	}

	public function test_split_file_into_words_handles_newlines(): void
	{
		$side = new Side("hello\nworld\nfoo");
		$out = '';
		$side->split_file_into_words($out);

		$this->assertStringContainsString('hello', $out);
		$this->assertStringContainsString('world', $out);
		$this->assertStringContainsString('foo', $out);
	}

	public function test_split_file_into_words_with_empty_content(): void
	{
		$side = new Side('');
		$out = '';
		$side->split_file_into_words($out);

		$this->assertSame('', $out);
	}

	public function test_split_file_into_words_only_whitespace(): void
	{
		$side = new Side('   ');
		$out = '';
		$side->split_file_into_words($out);

		$this->assertSame('', $out);
	}

	public function test_init_resets_position_and_cursor(): void
	{
		$side = new Side('hello world');
		$side->nextchar();
		$side->nextchar();
		$side->nextchar();

		$side->init();

		$this->assertSame(0, $side->position);
		$this->assertSame(0, $side->cursor);
		$this->assertSame('h', $side->character);
	}

	public function test_copy_whitespace_appends_to_output(): void
	{
		$side = new Side('   hello');
		$out = '';
		$side->copy_whitespace($out);

		$this->assertSame('   ', $out);
	}

	public function test_copy_word_appends_word_to_output(): void
	{
		$side = new Side('hello world');
		$out = '';
		$side->copy_word($out);

		$this->assertSame('hello', $out);
		$this->assertSame(1, $side->position);
	}

	public function test_copy_word_stops_at_whitespace(): void
	{
		$side = new Side('foo bar');
		$out = '';
		$side->copy_word($out);

		$this->assertSame('foo', $out);
	}

	public function test_skip_word_advances_position_without_output(): void
	{
		$side = new Side('foo bar');
		$side->skip_word();

		$this->assertSame(1, $side->position);
	}

	public function test_skip_until_ordinal_skips_n_words(): void
	{
		$side = new Side('one two three four');
		$side->skip_until_ordinal(2);

		$this->assertSame(2, $side->position);
	}

	public function test_skip_until_ordinal_preserves_whitespace_skipping(): void
	{
		$side = new Side("one   two   three");
		$side->skip_until_ordinal(2);

		$this->assertSame(2, $side->position);
	}

	public function test_copy_until_ordinal_copies_words(): void
	{
		$side = new Side('one two three four');
		$out = '';
		$side->copy_until_ordinal(2, $out);

		$this->assertStringContainsString('one', $out);
		$this->assertStringContainsString('two', $out);
		$this->assertSame(2, $side->position);
	}

	public function test_copy_until_ordinal_includes_whitespace(): void
	{
		$side = new Side('one   two');
		$out = '';
		$side->copy_until_ordinal(2, $out);

		$this->assertStringContainsString('   ', $out);
	}

	/**
	 * Documents known parser quirks in Diff\Side::decode_directive_line().
	 *
	 * KNOWN ISSUE #1: Multi-digit numbers only parse first digit
	 *
	 *   When a state encounters a multi-digit number (e.g., "10" or "12"),
	 *   only the FIRST digit is parsed. Subsequent digits are skipped.
	 *
	 *   Example: "5a10\n" → argument[2] = 1, not 10
	 *   Example: "3,5c8,10\n" → argument[3] = 1, not 10
	 *
	 * KNOWN ISSUE #2: 'a' and 'c' directives return false
	 *
	 *   decode_directive_line() returns false for 'a' and 'c' directives
	 *   even when well-formed. The 'd' directive parses successfully.
	 *   This persists from the original library lineage.
	 *
	 * RESOLVED: $value carry-over between non-digit states
	 *
	 *   Previously, $value retained the previous state's digit parse
	 *   value for non-digit states (argument[1] and argument[3]).
	 *   This has been fixed; these slots now correctly contain 0.
	 *
	 * These tests document the CURRENT post-patch behavior. They will
	 * need to be updated once the remaining issues are properly fixed.
	 *
	 * @see KNOWN_ISSUES.md
	 */
	public function test_decode_directive_line_add(): void
	{
		$side = new Side("5a10\n");
		$result = $side->decode_directive_line();

		// KNOWN ISSUE #2: 'a' directive returns false
		$this->assertFalse($result, 'KNOWN ISSUE #2: "a" directive returns false');

		// Directive letter IS captured
		$this->assertSame('a', $side->directive);

		// POST-PATCH: $value carry-over is FIXED
		// REMAINING: multi-digit "10" parsed as "1" (issue #1)
		$this->assertSame([5, 0, 1, 0], $side->argument);
	}

	public function test_decode_directive_line_delete(): void
	{
		$side = new Side("5,7d3\n");
		$result = $side->decode_directive_line();

		// 'd' directive works correctly
		$this->assertTrue($result);
		$this->assertSame('d', $side->directive);

		// POST-PATCH: $value carry-over is FIXED
		$this->assertSame([5, 7, 3, 0], $side->argument);
	}

	public function test_decode_directive_line_change(): void
	{
		$side = new Side("3,5c8,10\n");
		$result = $side->decode_directive_line();

		// KNOWN ISSUE #2: 'c' directive returns false
		$this->assertFalse($result, 'KNOWN ISSUE #2: "c" directive returns false');

		// Directive letter IS captured
		$this->assertSame('c', $side->directive);

		// KNOWN ISSUE #1: multi-digit "10" parsed as "1"
		$this->assertSame([3, 5, 8, 1], $side->argument);
	}

	public function test_decode_directive_line_invalid_directive(): void
	{
		$side = new Side("3x5\n");
		$result = $side->decode_directive_line();

		$this->assertFalse($result);
	}

	public function test_decode_directive_line_missing_newline(): void
	{
		$side = new Side("3a5");
		$result = $side->decode_directive_line();

		$this->assertFalse($result);
	}

	public function test_decode_directive_line_range_form_delete(): void
	{
		$side = new Side("5,7d3,4\n");
		$result = $side->decode_directive_line();

		$this->assertTrue($result);
		$this->assertSame('d', $side->directive);

		// POST-PATCH: $value carry-over is FIXED
		$this->assertSame([5, 7, 3, 4], $side->argument);
	}

	public function test_decode_directive_line_range_form_add(): void
	{
		$side = new Side("5,7a10,12\n");
		$result = $side->decode_directive_line();

		$this->markTestIncomplete(
			'KNOWN ISSUE #2: "a" directive returns false — verify after fix'
			);
	}

	public function test_decode_directive_line_range_form_change(): void
	{
		$side = new Side("3,5c8,10\n");
		$result = $side->decode_directive_line();

		$this->markTestIncomplete(
			'KNOWN ISSUE #2: "c" directive returns false — verify after fix'
			);
	}

	public function test_decode_directive_line_single_digit_delete(): void
	{
		$side = new Side("3d5\n");
		$result = $side->decode_directive_line();

		$this->assertTrue($result);
		$this->assertSame('d', $side->directive);

		// POST-PATCH: $value carry-over is FIXED
		$this->assertSame([3, 0, 5, 0], $side->argument);
	}

	public function test_skip_line_skips_non_directive_lines(): void
	{
		$side = new Side("not a directive\n3a5\n");
		$side->skip_line();

		$this->assertSame('3', $side->character);
	}

	public function test_multiple_split_file_into_words_calls(): void
	{
		$side = new Side('alpha beta gamma');
		$out1 = '';
		$side->split_file_into_words($out1);

		$side->init();
		$out2 = '';
		$side->split_file_into_words($out2);

		$this->assertSame($out1, $out2);
	}

	public function test_argument_defaults_to_empty_array(): void
	{
		$side = new Side('hello');

		$this->assertSame([], $side->argument);
		$this->assertSame([], $side->getargument());
	}

	public function test_getdirective_defaults_to_empty_string(): void
	{
		$side = new Side('hello');

		$this->assertSame('', $side->getdirective());
	}
}