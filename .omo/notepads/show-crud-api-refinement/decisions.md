# Decisions

- Tests are now in scope. Add PHPUnit feature tests before extracting Form Requests.
- Preserve current implementation behavior: `main_image_path`, `title max:160`, slug max `180`, paginate `20`, admin-protected routes.
- Do not add frontend/uploads/show dates/tickets/orders/payments/multi-tenancy.

## 2026-05-24 Task: scope-update-no-tests
- User changed scope: “no quiero tocar test. Nada de test. Los vemos despues”.
- Updated plan now forbids creating, editing, or running tests in this work session.
- Current deliverable is only a narrow FormRequest extraction plus syntax/static/file-inspection evidence.
- Earlier note saying tests are in scope is superseded by this scope update.
