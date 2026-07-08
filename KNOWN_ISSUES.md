# Known Issues

This document tracks known issues in the modernized PHP 8.3+ port of the
classic diff library. These are pre-existing quirks inherited from the
original Perl → PHP port lineage and are documented here to ensure they
are not lost during future maintenance.

---

## Issue #1: `$value` carry-over in `Diff\Side::decode_directive_line()`

**Severity:** Low (cosmetic — does not affect diff computation)

**Affected versions:** All versions

**Description:**

When parsing a diff directive line, the `$value` variable is only reset
to `0` when a digit is encountered. If a directive slot has no digit
content (single-range directives like `5a10`), `$value` retains the
previous parsed value.

**Example:**

```
Input:  "5,7d3\n"
Expected: argument = [5, 7, 3, 0]
Actual:   argument = [5, 7, 3, 3]
```

**Root cause:**

In `Side::decode_directive_line()`:

```php
if ($this->isdigit($this->character))
{
    $value = 0;  // ← reset only happens here
    while ($this->isdigit($this->character)) {
        $value = 10 * $value + (int) ($this->character - '0');
        $this->nextchar();
    }
}
elseif ($state !== 1 && $state !== 3)
{
    $error = true;
}

$this->argument[$state] = $value;  // ← uses stale $value if non-digit
```

**Proposed fix:**

Move `$value = 0;` to the top of the outer `while` loop, before the
digit check:

```php
while (!$error && $state < 4)
{
    $value = 0;  // ← always reset
    if ($this->isdigit($this->character)) {
        // ...
    }
    // ...
}
```

**Impact on callers:**

Callers that rely on `argument[1]` or `argument[3]` being exactly `0`
for single-range directives will see different values. For typical
diff consumers (WackoWiki, revision viewers), the affected slots
represent line counts in the second file's range, and the carry-over
value is harmless because it's used in arithmetic with the actual
ranges.

---

## Issue #2: `'a'` and `'c'` directives return `false` from `decode_directive_line()`

**Severity:** Medium (affects diff display logic)

**Affected versions:** All versions

**Description:**

`decode_directive_line()` returns `false` (indicating parse error) for
well-formed `'a'` and `'c'` directives, but returns `true` for `'d'`
directives. The argument array and directive letter ARE populated
correctly before the false return.

**Examples:**

```
Input                | Expected  | Actual
---------------------|-----------|--------
"5a10\n"             | true      | false
"3,5c8,10\n"         | true      | false
"5,7d3\n"            | true      | true ✓
```

**Root cause:**

Unknown. The `case 1:` switch handles all three letters identically:

```php
case 1:
    if ($this->character === 'a' || $this->character === 'd' || $this->character === 'c')
    {
        $this->directive = $this->character;
        $this->nextchar();
    }
    else
    {
        $error = true;
    }
    break;
```

Suspected causes (in order of likelihood):

1. **Subtle logic bug** in the trailing-newline consumer for `'a'`/`'c'`
2. **PHP 8.x behavior change** in `mb_substr` or related string functions
3. **State machine edge case** when `$value` carry-over (Issue #1) interacts
   with the error-setting code path

**Workaround for callers:**

In `wackowiki/src/handler/page/diff.php`, the existing code already
ignores the return value of `decode_directive_line()` in some paths:

```php
if ($side_o->decode_directive_line())  // ← return value is ignored on truthy checks
{
    $argument = $side_o->getargument();
    // ...
}
```

The argument array is still populated, so the diff logic continues to
work despite the false return. However, this masks the underlying bug
and should be properly addressed.

**Proposed fix:**

Requires deeper investigation. Likely involves adding a debug print
of the parser state machine for `'a'`/`'c'` inputs and comparing to
the working `'d'` path. Once isolated, the fix is probably a few
lines in `Side::decode_directive_line()`.

---

## Tracking

These issues are tracked for resolution in a future release. To
contribute a fix:

1. Fork the repository
2. Reference this file (`KNOWN_ISSUES.md`) in your commit
3. Add a regression test that demonstrates the fix
4. Submit a pull request

**Status:** Open
**Target:** v1.1.0