# Tests

This folder contains integration (PHPUnit) and E2E (Playwright) tests.

## Docker stack (WordPress)
Use the automation scripts:
- `./scripts/stack-up.sh`

## Integration tests (PHPUnit)
Run all tests (stack + WP test suite + PHPUnit + E2E):
- `./scripts/run-tests.sh`

## E2E tests (Playwright)
E2E is executed in the Playwright container via `./scripts/run-tests.sh` using the browsers bundled in the Playwright image.

Notes: E2E uses admin/admin and temporarily switches siteurl/home to http://wordpress during the run.
