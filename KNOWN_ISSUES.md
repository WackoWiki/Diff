# Known Issues

This document tracks known issues in the modernized PHP 8.3+ port of the
classic diff library. These are pre-existing quirks inherited from the
original Perl → PHP port lineage and are documented here to ensure they
are not lost during future maintenance.

---

## Issue #1: Multi-digit numbers only parse first digit

**Severity:** Medium (affects line-count calculations)

**Affected versions:** All versions

**Description:**

When a directive state encounters a multi-digit number (e.g., `10`, `12`),
only the FIRST digit is parsed into `$value`. Subsequent digits are silently
skipped.

**Examples:**

| Input          | Expected argument  | Actual argument    |
|----------------|-------------------|-------------------|
| `"5a10\n"`     | `[5, 0, 10, 0]`   | `[5, 0, 1, 0]`     |
| `"3,5c8,10\n"` | `[3, 5, 8, 10]`   | `[3, 5, 8, 1]`     |

**Root cause:**

The inner digit-parsing loop in `decode_directive_line()` terminates
after one iteration instead of consuming all consecutive digits:

```php
if ($this->isdigit($this->character))
{
    $value = 0;
    while ($this->isdigit($this->character))  // ← loop exits after 1 iteration
    {
        $value = 10 * $value + (int) ($this->character - '0');
        $this->nextchar();
    }
}
```

Suspected cause: a recent patch may have inadvertently changed the loop
condition or scoping. Requires review of the patch diff against the
original `Side.php`.

**Proposed fix:**

Verify the inner while loop is correctly consuming all consecutive
digits while ensuring `$value` is properly scoped:

```php
while (!$error && $state < 4)
{
    $value = 0;  // Reset at top of outer loop
    if ($this->isdigit($this->character))
    {
        while ($this->isdigit($this->character))  // Inner loop intact
        {
            $value = 10 * $value + (int) ($this->character - '0');
            $this->nextchar();
        }
    }
    elseif ($state !== 1 && $state !== 3)
    {
        $error = true;
    }

    $this->argument[$state] = $value;
    // ...
}
```

**Workaround for callers:**

Line-count consumers should defensively handle single-digit values
as approximations of actual line counts. For WackoWiki's diff
display, the visual difference between `1` and `10` lines is
negligible for typical page revisions.

---

## Issue #2: `'a'` and `'c'` directives return false from `decode_directive_line()`

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
3. **State machine edge case** when multi-digit truncation (Issue #1)
   interacts with the error-setting code path

**Workaround for callers:**

In `wackowiki/src/handler/page/diff.php`, the existing code already
ignores the return value of `decode_directive_line()` in some paths.
The argument array is still populated, so the diff logic continues to
work despite the false return.

**Proposed fix:**

Requires deeper investigation. Likely involves adding a debug print
of the parser state machine for `'a'`/`'c'` inputs and comparing to
the working `'d'` path. Once isolated, the fix is probably a few
lines in `Side::decode_directive_line()`.

---

## Resolved Issues

### Issue #0: `$value` carry-over between states (FIXED)

Previously, when a directive slot had no digit content, `$value`
retained the previous state's digit parse value. This caused slots
like `argument[1]` and `argument[3]` to mirror the previous slot
rather than being `0`.

**Example:** `"5a10\n"` previously produced `[5, 5, 1, 1]`.

**Resolution:** Fixed by ensuring `$value` is reset at the start of
each outer-loop iteration. Now correctly produces `[5, 0, 1, 0]`
(multi-digit parsing still broken — see Issue #1).

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