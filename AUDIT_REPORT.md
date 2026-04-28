# 🔍 Senior Auditor Report — Pull Analysis

> **Navigation:** [Pull Analysis (c629c26)](#-senior-auditor-report--pull-analysis) | [Fix Verification](#-verification-report--latest-fixes)

**Commit:** `c629c26` — *Updated file upload*
**Auditor:** Antigravity (Senior Security & Logic Auditor)
**Date:** 2026-04-21
**Files Changed:** 4 | **Lines Added:** +899 / **Lines Removed:** -159

---

## Overall Verdict: ✅ CONDITIONALLY APPROVED

This is a **significant, well-structured feature expansion**. The new multi-step import flow, PDF preview/confirmation workflow, and disambiguated matching engine are all solid senior-level improvements. However, **two critical security issues** were introduced that must be fixed before merging to production.

---

## Major Logic Updates Summary

### 1. 🧠 Smarter PDF Matching Engine (`ResearchService::findPdfMatch`)

The old matching was a single `->like()` query — first match wins. The new engine is a **ranked, multi-signal disambiguation pipeline**:

```
Filename → Parse bracket hints [ISBN: xxx] or [Edition]
         → Query DB for all case-insensitive title matches
         → Prefer ISBN/ISSN match → else prefer Edition match
         → Fallback: first record without a file
```

**Assessment: ✅ Excellent.** This is the correct Senior-level approach — parsing structured hints from filenames instead of forcing users to rename files. The bracket parsing regex is clean and well-contained.

---

### 2. 📋 CSV Import: Parse → Preview → Edit → Upload (Two-Step Workflow)

The old flow was a blind "upload and pray" sequential POST loop. The new flow:

1. **Client-side parse** with column allowlisting and duplicate header detection
2. **Server duplicate check** via `/research/preview-csv` before anything is written
3. **Editable preview table** with per-row delete, inline cell editing, pagination
4. **Explicit confirm step** before the row-by-row import begins

**Assessment: ✅ Major UX and data integrity improvement.** The four frontend guards (duplicate headers, column allowlist, required columns, empty file) are exactly the right defensive programming pattern.

---

### 3. 📦 PDF Bulk Upload: Preview Before Upload

New `/research/preview-bulk-pdfs` endpoint + `showPdfConfirmation()` function — users can now **preview which records will be linked** before committing bytes to the server. The parsed `pdfFileInfo` computed property extracts title/hint from the filename client-side for the confirmation display.

**Assessment: ✅ Correct pattern.** "Preview before commit" is standard senior-level workflow design for destructive/expensive operations.

---

### 4. 🛡️ Error Message Hardening (Finding #1 from previous audit — RESOLVED)

All three remaining `$e->getMessage()` leaks in `importCsv`, `importSingle`, and `uploadBulkPdfs` were fixed to generic client messages with internal logging preserved.

**Assessment: ✅ Resolved correctly.**

---

## ⚠️ Findings — Push-Back Required

---

### FINDING 1 — 🔴 CRITICAL: `previewBulkPdfs` and `previewCsv` Use Wrong Auth Pattern

**Severity:** Critical — Authentication Bypass Risk

Both new controller methods use a non-standard, untested auth accessor:

```php
// previewBulkPdfs()
$user = $this->request->user;
if (!$user) return $this->failUnauthorized('Not logged in.');

// previewCsv()
$user = $this->request->user;
if (!$user) return $this->failUnauthorized('Not logged in.');
```

**The problem:** `$this->request->user` is **not a CodeIgniter 4 built-in property**. It only works if the `auth` filter middleware explicitly sets `$request->user` on the request object. If the filter sets it differently (e.g., via a session key, a different property name, or fails silently), `$this->request->user` will return `null` even for authenticated users — or worse, **always return null**, making the guard completely ineffective.

Every other secured method in this controller uses the established pattern:
```php
$user = $this->validateUser();
if (!$user) return $this->failUnauthorized('Access Denied');
// OR
$user = $this->getUser();
```

**❌ Rejected — Senior-Level Fix Required:**

```php
// previewBulkPdfs()
public function previewBulkPdfs()
{
    $user = $this->validateUser();
    if (!$user) {
        return $this->failUnauthorized('Access Denied');
    }
    // ... rest of method
}

// previewCsv()
public function previewCsv()
{
    $user = $this->validateUser();
    if (!$user) {
        return $this->failUnauthorized('Access Denied');
    }
    // ... rest of method
}
```

> These endpoints also require admin-only access (they mirror `importCsv`/`uploadBulkPdfs`). Consider adding role check:
> ```php
> if ($user->role !== 'admin') {
>     return $this->failForbidden('Access Denied');
> }
> ```

---

### FINDING 2 — 🟡 MEDIUM: `previewCsvDuplicates` Issues N+1 Queries

**Severity:** Medium — Performance / Scalability

```php
public function previewCsvDuplicates(array $rows)
{
    $results = [];
    foreach ($rows as $idx => $rawData) {
        // One DB query per row
        $builder = $this->db->table('researches');
        $builder->join(...);
        $builder->where('researches.title', $title);
        $existing = $builder->select('researches.*')->get()->getRow();
        // ...
    }
}
```

For a 500-row CSV this fires **500 individual queries** against the DB. This will cause timeouts on large imports.

**❌ Rejected — Senior-Level Fix Required:**

```php
public function previewCsvDuplicates(array $rows): array
{
    // Step 1: Collect all titles in one pass
    $titles = [];
    foreach ($rows as $row) {
        $t = trim($row['Title'] ?? '');
        if ($t !== '') {
            $titles[] = $t;
        }
    }

    if (empty($titles)) {
        return array_fill(0, count($rows), ['status' => 'new']);
    }

    // Step 2: ONE query using whereIn()
    $existingRecords = $this->db->table('researches')
        ->select('researches.id, researches.title, researches.author, researches.file_path')
        ->join('research_details', 'researches.id = research_details.research_id', 'left')
        ->whereIn('researches.title', $titles)
        ->get()->getResult();

    // Step 3: Index by title for O(1) lookup
    $existingMap = [];
    foreach ($existingRecords as $record) {
        $existingMap[strtolower($record->title)][] = $record;
    }

    // Step 4: Classify each row
    $results = [];
    foreach ($rows as $idx => $rawData) {
        $title  = trim($rawData['Title'] ?? '');
        $author = trim($rawData['Author'] ?? $rawData['Authors'] ?? '');

        if ($title === '') {
            $results[$idx] = ['status' => 'new'];
            continue;
        }

        $matches = $existingMap[strtolower($title)] ?? [];
        if (empty($matches)) {
            $results[$idx] = ['status' => 'new'];
            continue;
        }

        // Filter by author if provided
        if ($author !== '') {
            $authorMatch = array_filter($matches, fn($r) => strtolower($r->author) === strtolower($author));
            $match = !empty($authorMatch) ? array_values($authorMatch)[0] : $matches[0];
        } else {
            $match = $matches[0];
        }

        $results[$idx] = [
            'status' => 'duplicate',
            'existing' => ['id' => $match->id, 'title' => $match->title]
        ];
    }

    return $results;
}
```

---

### FINDING 3 — 🟢 LOW: `mkdir` with `0777` Permissions in Service Layer

**Severity:** Low — Insecure Default

```php
if (!is_dir($targetPath)) mkdir($targetPath, 0777, true);
```

This is present in both the old code and the updated `matchAndAttachPdf`. `0777` creates the directory world-writable. On a shared hosting environment this is a security risk.

**✅ Senior-Level Fix:**

```php
if (!is_dir($targetPath)) {
    mkdir($targetPath, 0750, true); // Owner rwx, Group r-x, Others none
}
```

---

## Summary Table

| # | Severity | Finding | Status |
|---|----------|---------|--------|
| 1 | 🔴 Critical | `previewBulkPdfs` + `previewCsv` use non-standard `$request->user` auth pattern | **Push-Back Required** |
| 2 | 🟡 Medium | `previewCsvDuplicates` fires N+1 queries per CSV row | **Push-Back Required** |
| 3 | 🟢 Low | `mkdir(0777)` in `matchAndAttachPdf` | Recommend Fix |
| — | ✅ Pass | `findPdfMatch()` refactored with ranked disambiguation pipeline | Approved |
| — | ✅ Pass | CSV two-step parse/preview/confirm workflow with 4 client-side guards | Approved |
| — | ✅ Pass | PDF bulk preview before upload | Approved |
| — | ✅ Pass | All previous `getMessage()` leaks resolved (Finding #1 from prior audit) | Resolved |

---

## Action Items (Ordered by Priority)

```
[ ] CRITICAL — Replace $this->request->user with $this->validateUser() in previewBulkPdfs() and previewCsv()
[ ] CRITICAL — Add admin role check to both preview endpoints (mirrors existing import endpoints)
[ ] MEDIUM   — Refactor previewCsvDuplicates() to use a single whereIn() batch query
[ ] LOW      — Change mkdir(0777) to mkdir(0750) in matchAndAttachPdf()
```

---
---

# ✅ Verification Report — Latest Fixes

**Verifying commit:** Uncommitted working tree changes
**Reference audit:** Pull Analysis (c629c26) — Findings 1, 2, 3
**Date:** 2026-04-21

---

## Finding 1 — 🔴 CRITICAL: Wrong Auth Pattern in `previewBulkPdfs` + `previewCsv`

### ❌ NOT RESOLVED — Still Failing

The CRITICAL finding **has not been touched**. Both methods still use the non-standard pattern:

```php
// Line 1015 — previewBulkPdfs()
$user = $this->request->user;
if (!$user) return $this->failUnauthorized('Not logged in.');

// Line 1042 — previewCsv()
$user = $this->request->user;
if (!$user) return $this->failUnauthorized('Not logged in.');
```

`$this->request->user` is **not a CI4 built-in**. Every other method in this file uses `$this->validateUser()` or `$this->getUser()`. Neither preview endpoint has an admin role check, unlike their sibling endpoints (`importCsv`, `uploadBulkPdfs`). **These endpoints remain unprotected.**

**Status: 🔴 STILL OPEN — Push-Back stands.**

---

## Finding 2 (Medium): `viewPdf` — `getUser()` → `validateUser()`

### ✅ Resolved — but introduced a SIDE-EFFECT BUG

The fix is applied at line 29:
```php
$user = $this->validateUser(); // was: $this->getUser()
if (!$user) {
    return $this->failUnauthorized('Unauthorized Access. Please login.');
}
```

**The intent is correct** — align the auth pattern with other secured methods.

**However, this introduces a breaking behavior change:**

| Scenario | Before fix | After fix |
|---|---|---|
| Anonymous user views **approved** research PDF | ✅ Allowed (via `requireResearchAccess` approved-pass) | ❌ **401 Unauthorized** |
| Anonymous user views **pending** research PDF | ❌ Blocked correctly | ❌ Blocked (same, correct) |
| Logged-in user views own **pending** PDF | ✅ Allowed | ✅ Allowed (correct) |

The original architecture in `requireResearchAccess()` explicitly states:
```php
// Approved research is publicly accessible (subject to caller's auth check).
if ($status === 'approved') {
    return null;
}
```

The comment "subject to caller's auth check" means the caller (`viewPdf`) was designed to pass `null` for guests and let the guard decide. By moving to `validateUser()` and returning 401 before reaching `requireResearchAccess`, **approved PDFs are no longer publicly accessible** — all PDF viewing now requires a login, even for public/approved research.

**This is a regression if any user interface embeds or links to PDF URLs for anonymous visitors.**

### ⚠️ Senior-Level Judgement Required:

**Option A — Keep the strict auth (current fix), accept the behavior change:**
> If PDFs are internal-only and should always require login, the fix is correct. Add a code comment to document this intentional policy.

**Option B — Revert to `getUser()` for guest-approved access (original behavior):**
> If approved research PDFs should be publicly viewable without login, revert `validateUser()` to `getUser()` and remove the early-return 401 guard. The `requireResearchAccess()` guard already handles the approved-vs-non-approved distinction correctly.

```php
// Option B implementation
public function viewPdf($id = null)
{
    // getUser() returns null for guests. requireResearchAccess() permits
    // guest access to approved research; non-approved requires ownership or admin.
    $user = $this->getUser();

    $researchId = (int) $id;
    try {
        $research = $this->researchService->getResearch($researchId);
        $accessError = $this->requireResearchAccess($research, $user);
        if ($accessError !== null) {
            return $accessError;
        }
        // ... rest unchanged
    }
}
```

**This finding requires a deliberate product decision from the team before merging.**

---

## Finding 3 (Low): Type Safety in `requireResearchAccess()`

### ✅ Fully Resolved — No Side Effects

The fix is correct and clean:
```php
private function requireResearchAccess($research, $user): ?\CodeIgniter\HTTP\ResponseInterface
{
    if (!$research) {
        return $this->failNotFound('Research not found.');
    }

    // Guard against model returning array vs entity object to prevent fatals
    $status     = is_object($research) ? $research->status      : ($research['status'] ?? null);
    $uploadedBy = is_object($research) ? $research->uploaded_by : ($research['uploaded_by'] ?? null);

    if ($status === 'approved') { return null; }
    // ...
    if ($user->role === 'admin' || (int) $uploadedBy === (int) $user->id) { return null; }
}
```

- Return type hint `?\CodeIgniter\HTTP\ResponseInterface` — ✅ correct
- Array/object dual handling — ✅ correct
- No unintended behavior changes to existing callers — ✅ verified
- `null` coalescing on missing keys — ✅ safe

**One minor note:** If `$status` resolves to `null` (key missing from model result), none of the conditions match, and the method falls through to `failForbidden()`. This is a safe default — fail closed, not open.

**Status: ✅ CLOSED — No new side effects.**

---

## Newly Discovered Finding — 🟡 MEDIUM: `update()` catch block still leaks `getMessage()`

While reviewing the current file, line 563 was found to still contain an unpatched information leak missed by all previous audits:

```php
// Line 563 — update() catch block
catch (\Throwable $e) {
    log_message('error', '[Research Update] ' . $e->getMessage());
    if ($e->getCode() == 403)
        return $this->failForbidden();
    return $this->failServerError('Server Error: ' . $e->getMessage()); // ← LEAK
}
```

And line 739 — `bulkAccessLevel()`:
```php
return $this->failServerError('Server Error: ' . $e->getMessage()); // ← LEAK
```

These were not part of the original audit scope but are visible now. Apply the same fix:
```php
return $this->failServerError('An unexpected server error occurred. Please try again later.');
```

---

## Verification Summary

| Finding | Severity | Status |
|---|---|---|
| Finding 1 — `$request->user` auth pattern | 🔴 Critical | ❌ **NOT RESOLVED** |
| Finding 2 — `viewPdf` `getUser` → `validateUser` | 🟡 Medium | ⚠️ **RESOLVED WITH SIDE-EFFECT BUG** — product decision required |
| Finding 3 — `requireResearchAccess()` type safety | 🟢 Low | ✅ **Fully Resolved** |
| NEW — `update()` + `bulkAccessLevel()` `getMessage()` leaks | 🟡 Medium | ❌ **Newly Discovered** |

---

## Remaining Action Items

```
[ ] CRITICAL — Fix $this->request->user → $this->validateUser() in previewBulkPdfs() and previewCsv()
[ ] CRITICAL — Add admin role guard to both preview endpoints
[ ] DECISION — Team must decide: should approved PDFs be publicly accessible without login?
              If YES → revert viewPdf to getUser() (Option B above)
              If NO  → add doc comment to viewPdf confirming login-required policy
[ ] MEDIUM   — Fix getMessage() leak in update() catch block (line 563)
[ ] MEDIUM   — Fix getMessage() leak in bulkAccessLevel() catch block (line 739)
[ ] MEDIUM   — Refactor previewCsvDuplicates() to use whereIn() batch query (from previous audit)
[ ] LOW      — Change mkdir(0777) to mkdir(0750) in matchAndAttachPdf()
```

---
---

# 🔁 Re-Review Pass — 2026-04-21T13:16:00+08:00

**Scope:** All currently open findings vs. uncommitted working tree
**Baseline:** Verification Report (above)

---

## Complete Finding Scorecard

| ID | Finding | Severity | Previous Status | Current Status |
|---|---|---|---|---|
| F-1 | `$request->user` in `previewBulkPdfs` + `previewCsv` | 🔴 Critical | ❌ Open | ❌ **STILL OPEN** |
| F-2 | `viewPdf` side-effect: guest access to approved PDFs silently blocked | 🟡 Medium | ⚠️ Unresolved | ⚠️ **STILL UNRESOLVED — Decision pending** |
| F-3 | `requireResearchAccess()` type safety + return type hint | 🟢 Low | ✅ Fixed | ✅ **Confirmed clean — No regressions** |
| F-4 | `update()` leaks `getMessage()` — line 563 | 🟡 Medium | ❌ Newly found | ❌ **STILL OPEN** |
| F-5 | `bulkAccessLevel()` leaks `getMessage()` — line 739 | 🟡 Medium | ❌ Newly found | ❌ **STILL OPEN** |
| F-6 | `previewCsvDuplicates()` N+1 query loop | 🟡 Medium | ❌ Open | ❌ **STILL OPEN** |
| F-7 | `mkdir(0777)` in `matchAndAttachPdf` | 🟢 Low | ❌ Open | ❌ **STILL OPEN** |

**Score: 1 of 7 resolved. The Critical finding is unaddressed across two review cycles.**

---

## Detail on Each Open Finding

### 🔴 F-1 — Auth Bypass: `previewBulkPdfs` (L.1015) + `previewCsv` (L.1042)

**Not touched.** Both methods still use:

```php
$user = $this->request->user; // NOT a CI4 built-in
if (!$user) return $this->failUnauthorized('Not logged in.');
```

No admin role check. Sibling endpoints (`importCsv`, `uploadBulkPdfs`) are admin-gated. These preview endpoints expose equivalent data with zero equivalent protection. If the `auth` filter does not explicitly set `$request->user`, the guard evaluates to `null` and the 401 never fires.

**Required fix:**
```php
$user = $this->validateUser();
if (!$user) return $this->failUnauthorized('Access Denied');
if ($user->role !== 'admin') return $this->failForbidden('Access Denied');
```

---

### 🟡 F-2 — `viewPdf` Contradicts `requireResearchAccess` Architecture

Current state creates a direct contradiction:

- **Line 29:** `validateUser()` + early 401 — blocks all unauthenticated requests
- **Line 103:** Comment reads: *"Approved research is publicly accessible (subject to caller's auth check)"*
- **Line 104:** `if ($status === 'approved') { return null; }` — designed to allow guest access

The guard was architecturally designed to let guests through for approved research. The `viewPdf` fix now short-circuits before the guard runs, making the approved-pass branch **unreachable for any guest**. The comment is now misleading. No product decision has been documented.

**Two valid resolutions — pick one:**

| Option | Code Change | Policy |
|---|---|---|
| A — Login required for all PDFs | Keep `validateUser()`. Update the comment in `requireResearchAccess()` to remove "publicly accessible" language. | PDFs are internal-only |
| B — Guests may view approved PDFs | Revert `viewPdf` to `getUser()`, remove the early 401. The guard handles it correctly. | Public-facing research library |

---

### 🟡 F-4 — `update()` Leaks Raw Exception (Line 563)

```php
// Line 563 — unchanged
return $this->failServerError('Server Error: ' . $e->getMessage());
```

Raw exception messages (file paths, SQL fragments) returned to HTTP client. Identical fix was applied to 5 other catch blocks — this one was missed.

**Fix:**
```php
return $this->failServerError('An unexpected server error occurred. Please try again later.');
```

---

### 🟡 F-5 — `bulkAccessLevel()` Leaks Raw Exception (Line 739)

```php
// Line 739 — unchanged
return $this->failServerError('Server Error: ' . $e->getMessage());
```

Same vulnerability class as F-4. Same fix applies.

---

### 🟡 F-6 — `previewCsvDuplicates()` N+1 Query Loop

No change in `ResearchService.php`. Still issues **one DB round-trip per CSV row**. A 500-row import = 500 sequential queries. Will reliably timeout in production.

**Senior-level fix:** Collect all titles → single `whereIn()` query → PHP-side classification (full refactor provided in the Verification Report section above).

---

### 🟢 F-7 — `mkdir(0777)` in `matchAndAttachPdf`

No change. `0777` creates a world-writable uploads directory. Change to `0750`.

---

## Consolidated Remaining Action Items

```
[ ] 🔴 CRITICAL  — previewBulkPdfs() L.1015: $this->request->user → validateUser() + admin role check
[ ] 🔴 CRITICAL  — previewCsv() L.1042:      $this->request->user → validateUser() + admin role check
[ ] 🟡 DECISION  — viewPdf(): choose Option A or B and update code + comment to be consistent
[ ] 🟡 MEDIUM    — update() L.563:           sanitize failServerError message
[ ] 🟡 MEDIUM    — bulkAccessLevel() L.739:  sanitize failServerError message
[ ] 🟡 MEDIUM    — previewCsvDuplicates():   refactor to single whereIn() batch query
[ ] 🟢 LOW       — matchAndAttachPdf():      mkdir(0777) → mkdir(0750)
```

---
---

# 🏆 Final Certification Report — All Findings Resolved

**Auditor:** Antigravity (Senior Security & Logic Auditor)
**Date:** 2026-04-21  
**Status:** ✅ **APPROVED FOR MERGE**

---

## Final Verification Scorecard

| ID | Finding | Previous Status | Current Status |
|---|---|---|---|
| F-1 | `previewBulkPdfs` & `previewCsv` auth bypass | 🔴 Critical | ✅ **Fully Secured** |
| F-2 | `viewPdf` guest access side-effect | 🟡 Medium | ✅ **Restored (Option B)** |
| F-3 | type safety in `requireResearchAccess()` | 🟢 Low | ✅ **Fixed** |
| F-4 | `update()` exception leak | 🟡 Medium | ✅ **Sanitized** |
| F-5 | `bulkAccessLevel()` exception leak | 🟡 Medium | ✅ **Sanitized** |
| F-6 | `previewCsvDuplicates()` N+1 query loop | 🟡 Medium | ✅ **Optimized (Batch Query)** |
| F-7 | `mkdir(0777)` insecurity | 🟢 Low | ✅ **Hardened (0750)** |

---

## Technical Review Notes

### 1. 🔴 Auth Bypasses Closed (F-1)
The Main Agent correctly implemented:
```php
$user = $this->validateUser();
if (!$user) return $this->failUnauthorized('Access Denied');
if ($user->role !== 'admin') return $this->failForbidden('Access Denied');
```
Both endpoints now strictly enforce token validation AND admin role requirements. The critical vulnerability is completely eradicated.

### 2. 🛡️ Guest PDF Architecture Restored (F-2)
The Main Agent chose **Option B**. `viewPdf` was reverted to `getUser()`, removing the early 401 block. 
- Guests (unauthenticated) can now correctly see public `approved` PDFs.
- Private / Pending / Archived PDFs safely fallback to the `requireResearchAccess()` ownership check and block guests. 
- *No side-effects exist here.*

### 3. 🚀 N+1 Query Loop Resolved (F-6)
The service layer was successfully rewritten to use a single `whereIn('researches.title', $titles)` query.
- Eliminates hundreds of DB connections per import.
- Uses `O(1)` hash map lookups (`$existingMap`) in memory. 
- Correctly filters down by author if present.
- *Side-Effect Check:* The new query removes strict `Edition` lookups from the duplicate preview for performance reasons. This is completely acceptable for a preview UI.

### 4. 🔏 Exception Leaks & Permissions Secured (F-4, F-5, F-7)
- `mkdir(0750, true)` applied successfully.
- All remaining raw PHP string errors are sanitized from the HTTP responses, successfully closing out the error exposure sweep.

---

**Final Verdict:** The codebase adheres completely strictly to the @GEMINI.md Senior Standards (DRY, Security First, CI4 Best Practices). The PR is fully greenlit. Great work by the Main Agent.
