# AGENTS.md

## Commands

- Install/setup is `composer setup`; it runs Composer, creates `.env`, generates the key, runs migrations, then `npm install --ignore-scripts` and `npm run build`.
- Full PHP test suite: `composer test` (clears config first, then runs `php artisan test`).
- Focused tests: `php artisan test --filter=AuthStubTest` or another Pest/PHPUnit filter.
- PHP formatting: `vendor/bin/pint` (Laravel Pint is installed; there is no repo-local `pint.json`).
- Frontend build: `npm run build`; dev assets use `npm run dev`.
- Full local dev stack: `composer dev` starts `php artisan serve`, queue listener, Pail logs, and Vite via `concurrently`.

## App Shape

- This is a Laravel 13 / PHP 8.3 backend with Sanctum API tokens, Filament admin, Pest tests, Vite, and Tailwind 4.
- Public mobile API routes are in `routes/api.php` under `/api/v1`; protected routes use `auth:sanctum`.
- Feature code is grouped under `app/Modules/*` by API area; shared Eloquent models and enums stay in `app/Models` and `app/Enums`.
- Filament admin resources live in `app/Filament/Resources`; the admin panel is mounted at `/admin` and `User::canAccessPanel()` only allows `UserRole::Admin`.

## API Contract

- `docs/api/openapi.yaml` is the public machine-readable contract; update it in the same change as any request, response, endpoint, status-code, or auth behavior change.
- The markdown files in `docs/api/` document business behavior in Russian; keep them consistent with the OpenAPI contract.
- Current dev auth stubs are configured in `config/klinomania.php` and `.env.example`: client OTP `1111` is disabled by default, cleaner code `111111` remains enabled by default.
- `AppServiceProvider` binds `SmsGateway` to `NotisendSmsGateway`; client OTP request still uses the gateway even when the stub code is enabled.

## Testing Notes

- `phpunit.xml` forces tests to in-memory SQLite, array cache/session/mail, sync queue, and `APP_ENV=testing`.
- `tests/Pest.php` does not enable `RefreshDatabase` globally; add `uses(RefreshDatabase::class);` per Pest file when database isolation is needed.
- Existing API tests use JSON requests against `/api/v1/...` and assert Sanctum token responses from auth flows.

## Assets And Env

- `vite.config.js` inputs are `resources/css/app.css` and `resources/js/app.js`; Vite ignores `storage/framework/views` watches.
- `.npmrc` sets `ignore-scripts=true`, so do not rely on npm lifecycle scripts running during install.
- `.env.example` defaults app storage to PostgreSQL/database-backed session/cache/queue; tests override this in `phpunit.xml`.
