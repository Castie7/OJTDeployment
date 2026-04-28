# Security Test Checklist

Use this before every deployment.

## 1) Setup (once per test session)
1. Start backend and frontend.
2. Prepare 3 accounts:
   - `admin` account
   - `user1` account
   - `user2` account
3. Open browser DevTools Console on the frontend app.
4. Paste this helper:

```js
const BASE = 'http://192.168.60.60/OJT2/backend/public/index.php';

async function req(path, method = 'GET', body, sendCsrf = true) {
  const headers = { 'Content-Type': 'application/json' };
  const csrf = localStorage.getItem('csrf_token');
  if (sendCsrf && csrf) headers['X-CSRF-TOKEN'] = csrf;

  const res = await fetch(`${BASE}${path}`, {
    method,
    credentials: 'include',
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  const text = await res.text();
  let data;
  try { data = JSON.parse(text); } catch { data = text; }
  console.log('STATUS:', res.status, data);
  return { status: res.status, data };
}
```

## 2) Access Control Tests
### A. Register endpoint must be admin-only
1. Logout (guest state).
2. Run:
```js
await req('/auth/register', 'POST', {
  name: 'x',
  email: 'x@test.com',
  password: 'Aa!12345678',
  role: 'admin'
});
```
3. Expected: `401` or `403` (not `200`).

### B. Admin logs must be admin-only
1. Login as normal user.
2. Run:
```js
await req('/api/logs', 'GET', undefined, false);
await req('/api/logs/export', 'GET', undefined, false);
await req('/api/logs/1', 'GET', undefined, false);
```
3. Expected: `403` on all.

### C. Research ownership checks
1. Login as `user2`.
2. Try archive/restore a record owned by `user1`:
```js
await req('/research/archive/USER1_RESEARCH_ID', 'POST', {});
await req('/research/restore/USER1_RESEARCH_ID', 'POST', {});
```
3. Expected: `403` (or permission error), not success.

## 3) IDOR Tests
### A. Notifications should not trust `user_id` input
1. Login as `user1`.
2. Run:
```js
await req('/api/notifications?user_id=999', 'GET', undefined, false);
await req('/api/notifications/read', 'POST', { user_id: 999 });
```
3. Expected: server uses session user only (no access to another user’s notifications).

### B. Research detail route should be protected
1. Run:
```js
await req('/research/12', 'GET', undefined, false);
```
2. Expected:
   - Not `404` because route exists.
   - `200` only if owner/admin.
   - Otherwise `403`.

## 3.1) Visibility Tests (Public vs Private)
### A. Guest should see only public approved research
1. Logout (guest).
2. Run:
```js
await req('/research', 'GET', undefined, false);
```
3. Expected: only approved items with `access_level = public`.

### B. Logged-in user should see approved public + private research
1. Login as normal user.
2. Run:
```js
await req('/research', 'GET', undefined, false);
```
3. Expected: approved items include both `public` and `private`.

### C. Admin can change visibility in Masterlist edit
1. Login as admin.
2. Open `Masterlist` > `Edit` an item.
3. Set `Visibility` to `Private (Login Required)`.
4. Save.
5. Re-test A and B.
6. Expected:
   - guest no longer sees that item
   - logged-in user still sees it

### D. Admin bulk visibility update
1. Login as admin.
2. Open `Masterlist`.
3. Select multiple rows using checkboxes.
4. Choose `Set to Public` or `Set to Private (Login Required)`.
5. Click `Apply to Selected`.
6. Expected: success message with updated count, and selected items show the new visibility badge.

## 4) CSRF Tests
1. Login normally.
2. Run a state-changing request **without** CSRF token:
```js
await req('/research/archive/USER1_RESEARCH_ID', 'POST', {}, false);
```
3. Expected: CSRF failure (`403`) for protected endpoints.
4. Note: `auth/verify` may be intentionally exempt.

## 5) Upload Validation Tests
### A. File type
1. Try uploading non-PDF (e.g., `.txt`, `.exe`).
2. Expected: rejected (`400`/`422`).

### B. File size
1. Try PDF larger than limit.
2. Expected: rejected.
3. Current project target:
   - App validation: max `128MB`
   - PHP config should be at least:
     - `upload_max_filesize = 128M`
     - `post_max_size = 140M`

## 6) Password Policy Test
1. As admin, try reset password with weak value (`12345678`).
2. Expected: validation failure.
3. Try strong password (`Aa!12345678`).
4. Expected: success.

## 7) Session/Auth Token Test
1. Logout.
2. Call protected endpoint.
3. Expected: `401`.
4. Confirm fake/old `auth_token` alone does not authenticate.

## 8) CORS Test
1. Call API from a non-allowed origin.
2. Expected: preflight blocked or no `Access-Control-Allow-Origin` for that origin.

## 9) Pass Criteria
- No guest privilege escalation.
- No non-admin access to admin routes.
- No cross-user data access.
- No state changes without CSRF.
- Upload rules enforced.
- Password policy enforced.
- Session-based auth enforcement is consistent.

## 10) Recommended Next Improvements
1. Add automated API security tests in CI (authz + CSRF + IDOR cases).
2. Add login rate limiting and lockout thresholds.
3. Add security headers (`CSP`, `X-Frame-Options`, `X-Content-Type-Options`) in one central filter.
4. Add periodic dependency vulnerability scanning.
