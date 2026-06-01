# Learnings

- `GET /api/admin/shows` uses `paginate(20)` with `latest()` ordering, so feature coverage should pin both the page size and newest-first sort.
- The `admin.token` route middleware protects the full admin show surface, and unauthenticated requests return the standard `Unauthenticated.` API envelope.

## 2026-05-24 19:16:06 Task 1: form-request extraction
- Extracted the current show validation rules into `CreateShowRequest` and `UpdateShowRequest` without touching tests.
- Preserved the controller's exact field names and limits, including `main_image_path`, `title max:160`, and `slug max:180`.
- `UpdateShowRequest` ignores the bound `show` model for slug uniqueness with `Rule::unique('shows', 'slug')->ignore($show)`.

## 2026-05-24 19:24:11 Task 2: controller form-request wiring
- Wired `ShowController::store()` to `CreateShowRequest` and `ShowController::update()` to `UpdateShowRequest`.
- Replaced manual validation helper usage with `$request->validated()` and removed the controller-local `validatedData()` method.
- Kept `Request` for `index(Request $request)` and left slug fallback plus `published_at` logic untouched.
- Validation extraction is the only behavior change in this controller file.

## 2026-05-24 19:26:20 Task 3: no-test scope verification
- Verified the workspace is not a git repository, so filesystem inspection was used instead of `git diff` or `git status`.
- Confirmed `tests/` exists with the expected files and no path under `tests/` was created, edited, deleted, or run during this task.
- Confirmed no test commands were run in Task 3.

## 2026-05-24 Task: admin shows route registration
- Added `Route::apiResource('/admin/shows', ShowController::class);` inside the existing `admin.token` middleware group in `routes/api.php`.
- Kept the `/auth/me` and `/auth/logout` admin-token routes unchanged and left `/auth/login` under `throttle:admin-login`.
- Scope stayed routes-only plus this notepad evidence note.
