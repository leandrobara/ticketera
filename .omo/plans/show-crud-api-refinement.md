# Show CRUD API Form Request Refinement (No Tests For Now)

## TL;DR
> **Summary**: Refactor the existing admin `Show` CRUD validation into dedicated Form Request classes without adding or modifying tests in this work session.
> **Deliverables**:
> - `app/Http/Requests/Admin/CreateShowRequest.php`
> - `app/Http/Requests/Admin/UpdateShowRequest.php`
> - `app/Http/Controllers/Api/Admin/ShowController.php` updated to use those requests
> - Syntax/inspection evidence under `.omo/evidence/`
> **Effort**: Short
> **Parallel**: NO
> **Critical Path**: Task 1 → Task 2 → Task 3 → Final Verification Wave

## Context
### Updated User Direction
- The user changed the plan explicitly: **“modifica el plan, no quiero tocar test. Nada de test. Los venmos despues”**.
- Therefore this plan must not add, edit, run, or depend on `tests/Feature/AdminShowsTest.php` or any other test file.
- Tests are intentionally deferred to a later plan/session.

### Existing Implementation Context
- The admin `Show` CRUD is already implemented.
- Current goal is only a narrow behavior-preserving Form Request extraction.
- Route behavior, response payloads, auth rules, pagination size, ordering, search semantics, validation rules, slug behavior, and `published_at` behavior must remain unchanged.

## Work Objectives
### Core Objective
Extract inline validation from `ShowController` into Show-specific Laravel Form Requests while preserving existing runtime behavior and avoiding all test work.

### Deliverables
- New file: `app/Http/Requests/Admin/CreateShowRequest.php`.
- New file: `app/Http/Requests/Admin/UpdateShowRequest.php`.
- Modified file: `app/Http/Controllers/Api/Admin/ShowController.php`.
- Verification evidence under `.omo/evidence/` using syntax checks and file inspection only.

### Definition of Done
- No test files are created or modified.
- No test commands are required by this plan.
- `CreateShowRequest` and `UpdateShowRequest` exist and parse.
- Both requests return `true` from `authorize()` because route middleware handles admin auth.
- Validation rules match the existing controller rules exactly.
- `UpdateShowRequest` ignores the current route-bound `Show` for slug uniqueness.
- `ShowController` uses the Form Requests for `store()` and `update()` and no longer contains the private `validatedData()` helper.
- No unrelated model/migration/route/auth/response-helper changes.

### Must Have
- Preserve existing current behavior, including:
  - `main_image_path` field, not `cover_image`.
  - `title` max `160`, not `255`.
  - `slug` max `180`, `alpha_dash`, unique validation.
  - `index()` pagination size `20`.
  - `index()` search by `title` or `slug`.
  - `latest()` ordering.
  - `published_at` set/cleared by current controller logic.
- Form Requests must live exactly at:
  - `app/Http/Requests/Admin/CreateShowRequest.php`
  - `app/Http/Requests/Admin/UpdateShowRequest.php`
- `authorize()` must return `true` in both requests.
- Update request must ignore the current route-bound `Show` for slug uniqueness.

### Must NOT Have
- Do not create, edit, or run tests in this plan.
- Do not modify `tests/Feature/AdminShowsTest.php` or any file under `tests/`.
- Do not create/recreate `Show` model, migration, factory, route, middleware, response helpers, policies, serializers, resources, services, or seeders.
- Do not rename `main_image_path` to `cover_image`.
- Do not change `title` max from `160` to `255`.
- Do not implement file upload handling.
- Do not implement show functions/dates, tickets, orders, payments, frontend, or multi-tenancy.
- Do not introduce public read routes; `show` remains admin-protected.
- Do not introduce a slug service or incremental suffix behavior.

## Verification Strategy
> ZERO TEST WORK IN THIS PLAN.
- Use file inspection, PHP syntax checks, and static/LSP diagnostics only.
- Evidence files:
  - `.omo/evidence/task-1-form-requests-parse.txt`
  - `.omo/evidence/task-1-form-request-rules.txt`
  - `.omo/evidence/task-2-controller-parse.txt`
  - `.omo/evidence/task-2-controller-inspection.txt`
  - `.omo/evidence/task-3-no-tests-touched.txt`
- Preferred commands:
  - `php -l app/Http/Requests/Admin/CreateShowRequest.php`
  - `php -l app/Http/Requests/Admin/UpdateShowRequest.php`
  - `php -l app/Http/Controllers/Api/Admin/ShowController.php`
  - `git diff -- tests` or equivalent file-diff inspection, if git metadata is available

## Execution Strategy
### Parallel Execution Waves
This work remains sequential because the controller wiring depends on the request classes existing first.

Wave 1: Task 1 `[quick]` — create Show Form Requests.
Wave 2: Task 2 `[quick]` — wire controller to Form Requests.
Wave 3: Task 3 `[unspecified-low]` — verify no test files were touched and record inspection evidence.

### Dependency Matrix
| Task | Depends On | Blocks |
|---|---|---|
| 1. Create Show Form Requests | none | 2, 3 |
| 2. Wire ShowController to Form Requests | 1 | 3 |
| 3. No-Test Scope Verification | 2 | Final Verification Wave |

## TODOs
> Implementation + verification evidence = ONE task. Never separate.
> EVERY task MUST have: Agent Profile + Parallelization + QA Scenarios.

- [x] 1. Create Show Form Requests Without Touching Tests

  **What to do**:
  1. Create directory `app/Http/Requests/Admin/` if it does not exist.
  2. Add `app/Http/Requests/Admin/CreateShowRequest.php`.
  3. Add `app/Http/Requests/Admin/UpdateShowRequest.php`.
  4. Both classes must extend `Illuminate\Foundation\Http\FormRequest`.
  5. Both classes must define `authorize(): bool` returning `true`.
  6. `CreateShowRequest::rules()` must return existing create validation rules from `ShowController`.
  7. `UpdateShowRequest::rules()` must return the same rules but with slug uniqueness ignoring the route-bound show. Use `$this->route('show')` and `Rule::unique('shows', 'slug')->ignore($show)`.
  8. Do not add custom `messages()`, `attributes()`, `prepareForValidation()`, or `failedValidation()`.
  9. Do not touch any file under `tests/`.
  10. Save syntax/rule inspection evidence under `.omo/evidence/`.

  **Must NOT do**:
  - Do not create, edit, or run tests.
  - Do not move slug generation into Form Requests.
  - Do not change validation rules.
  - Do not add policies or authorization checks inside Form Requests beyond `authorize(): true`.

  **Recommended Agent Profile**:
  - Category: `quick` - Reason: two small Laravel request classes.
  - Skills: `[]` - No special skill needed.

  **Parallelization**: Can Parallel: NO | Wave 1 | Blocks: [2, 3] | Blocked By: []

  **References**:
  - Pattern: `app/Http/Requests/AuthLoginRequest.php`.
  - Source rules: `app/Http/Controllers/Api/Admin/ShowController.php`.
  - Route binding name: `routes/api.php`.

  **Acceptance Criteria**:
  - [ ] `test -f app/Http/Requests/Admin/CreateShowRequest.php` exits `0`.
  - [ ] `test -f app/Http/Requests/Admin/UpdateShowRequest.php` exits `0`.
  - [ ] Both request files parse with `php -l` or Docker equivalent.
  - [ ] Both requests contain rules for `title`, `slug`, `description`, `main_image_path`, and `status`.
  - [ ] `UpdateShowRequest` ignores the current route-bound `show` for slug uniqueness.
  - [ ] No file under `tests/` is modified.

  **QA Scenarios**:
  ```
  Scenario: Form Request files exist and parse
    Tool: Bash
    Steps: Run `php -l app/Http/Requests/Admin/CreateShowRequest.php && php -l app/Http/Requests/Admin/UpdateShowRequest.php` or Docker equivalent.
    Expected: Both files report `No syntax errors detected`.
    Evidence: .omo/evidence/task-1-form-requests-parse.txt

  Scenario: Form Request rules match current controller behavior
    Tool: Bash / file inspection
    Steps: Inspect both request classes for rules matching current `ShowController` validation.
    Expected: Rules match current behavior.
    Evidence: .omo/evidence/task-1-form-request-rules.txt
  ```

  **Commit**: NO | Message: `refactor(shows): extract show form requests` | Files: [`app/Http/Requests/Admin/CreateShowRequest.php`, `app/Http/Requests/Admin/UpdateShowRequest.php`]

- [x] 2. Wire ShowController to Form Requests Without Changing Behavior

  **What to do**:
  1. In `app/Http/Controllers/Api/Admin/ShowController.php`, import:
     - `App\Http\Requests\Admin\CreateShowRequest`
     - `App\Http\Requests\Admin\UpdateShowRequest`
  2. Change `store(Request $request)` to `store(CreateShowRequest $request)`.
  3. Change `update(Request $request, Show $show)` to `update(UpdateShowRequest $request, Show $show)`.
  4. Replace `$this->validatedData($request)` with `$request->validated()` in `store()`.
  5. Replace `$this->validatedData($request, $show)` with `$request->validated()` in `update()`.
  6. Remove the now-unused private `validatedData()` method.
  7. Remove unused import `Illuminate\Validation\Rule`; keep `Illuminate\Http\Request` for `index(Request $request)`.
  8. Preserve all other logic exactly: search, pagination, slug fallback, `published_at`, create/update/delete responses.
  9. Save parse and inspection evidence under `.omo/evidence/`.

  **Must NOT do**:
  - Do not create, edit, or run tests.
  - Do not change `index()`, `show()`, or `destroy()` behavior.
  - Do not change response status codes or JSON payloads.
  - Do not move slug or publication business logic into Form Requests.

  **Recommended Agent Profile**:
  - Category: `quick` - Reason: narrow controller refactor plus verification.
  - Skills: `[]` - No special skill needed.

  **Parallelization**: Can Parallel: NO | Wave 2 | Blocks: [3] | Blocked By: [1]

  **References**:
  - Current target: `app/Http/Controllers/Api/Admin/ShowController.php`.
  - Store flow to preserve: current `store()`.
  - Update flow to preserve: current `update()`.
  - Index flow to preserve: current `index()`.

  **Acceptance Criteria**:
  - [ ] `ShowController::store()` typehints `CreateShowRequest`.
  - [ ] `ShowController::update()` typehints `UpdateShowRequest`.
  - [ ] `ShowController` no longer contains private `validatedData()`.
  - [ ] `ShowController` still imports `Illuminate\Http\Request` for `index()`.
  - [ ] `ShowController` no longer imports `Illuminate\Validation\Rule`.
  - [ ] `php -l app/Http/Controllers/Api/Admin/ShowController.php` or Docker equivalent exits `0`.
  - [ ] No file under `tests/` is modified.

  **QA Scenarios**:
  ```
  Scenario: Controller parses after refactor
    Tool: Bash
    Steps: Run `php -l app/Http/Controllers/Api/Admin/ShowController.php` or Docker equivalent.
    Expected: `No syntax errors detected`.
    Evidence: .omo/evidence/task-2-controller-parse.txt

  Scenario: Controller inspection confirms behavior preservation
    Tool: Bash / file inspection
    Steps: Inspect controller imports, method typehints, validated calls, and unchanged index/show/destroy logic.
    Expected: Only validation extraction changed.
    Evidence: .omo/evidence/task-2-controller-inspection.txt
  ```

  **Commit**: NO | Message: `refactor(shows): use form requests` | Files: [`app/Http/Controllers/Api/Admin/ShowController.php`]

- [x] 3. Verify No Test Files Were Touched

  **What to do**:
  1. Inspect repository diff/status for files under `tests/`.
  2. Confirm this plan did not create, edit, or run test files.
  3. Record exact evidence in `.omo/evidence/task-3-no-tests-touched.txt`.
  4. If test files were modified by prior interrupted work, revert those test-file changes only and document the action.

  **Must NOT do**:
  - Do not run test commands.
  - Do not edit production code in this task except reverting accidental test-file changes if needed.
  - Do not mark this complete without evidence.

  **Recommended Agent Profile**:
  - Category: `unspecified-low` - Reason: command execution and evidence capture.
  - Skills: `[]` - No special skill needed.

  **Parallelization**: Can Parallel: NO | Wave 3 | Blocks: [Final Verification Wave] | Blocked By: [2]

  **References**:
  - User direction: “Nada de test. Los vemos despues”.
  - Scope files: `app/Http/Requests/Admin/CreateShowRequest.php`, `app/Http/Requests/Admin/UpdateShowRequest.php`, `app/Http/Controllers/Api/Admin/ShowController.php`.

  **Acceptance Criteria**:
  - [ ] Evidence file exists at `.omo/evidence/task-3-no-tests-touched.txt`.
  - [ ] Evidence states no files under `tests/` are modified, or documents reverted accidental modifications.
  - [ ] No test commands were run as part of this plan after the user direction changed.

  **QA Scenarios**:
  ```
  Scenario: No test-file scope violation
    Tool: Bash / file inspection
    Steps: Inspect changed files and confirm no path under `tests/` is changed.
    Expected: No test files changed.
    Evidence: .omo/evidence/task-3-no-tests-touched.txt
  ```

  **Commit**: NO | Message: `chore(shows): confirm no test changes` | Files: [`.omo/evidence/task-3-no-tests-touched.txt`]

## Final Verification Wave (MANDATORY — after ALL implementation tasks)
> 4 review agents run in PARALLEL. ALL must APPROVE.
> Reviewers must enforce the updated no-test scope.
- [x] F1. Plan Compliance Audit — oracle
- [x] F2. Code Quality Review — unspecified-high
- [x] F3. No-Test Scope QA — unspecified-high
- [x] F4. Scope Fidelity Check — deep

## Commit Strategy
- No commit is required unless the user explicitly requests git operations.
- If committing later, use one atomic commit after verification: `refactor(shows): extract show form requests`.
- Include only:
  - `app/Http/Requests/Admin/CreateShowRequest.php`
  - `app/Http/Requests/Admin/UpdateShowRequest.php`
  - `app/Http/Controllers/Api/Admin/ShowController.php`
  - optional `.omo/evidence/*` only if the repository intentionally tracks planning evidence.

## Success Criteria
- Show Form Requests exist and match existing validation behavior.
- ShowController delegates validation to those Form Requests.
- No tests are added, edited, or run in this plan after the user’s scope change.
- No route/auth/model/migration/response-helper churn.
- Final verification agents all approve.
