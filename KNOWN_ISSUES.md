# Known Issues — laravel-hr

_Last checked: 2026-08-02_

`vendor/` was already present; no install needed.

## Failing tests

No failing tests. `vendor/bin/pest -p` (parallel, 12 processes): **18 passed** (41 assertions). Coverage of `tests/` is thin: `tests/ExampleTest.php`, `tests/ArchTest.php`, `tests/Feature/HrManagementTest.php`, `tests/Feature/HrChartsCardTest.php`, `tests/Feature/ZktecoSyncTest.php`, and (added 2026-08-02) `tests/Feature/EntityFormPageRedirectTest.php` — still nothing dedicated to leave approvals or attendance beyond what those files cover.

**Fixed** (2026-08-02, reported as "employee create not successful and showing no message"):
`resources/views/livewire/entities/form-page.blade.php` checked
`$errors->first('form.' . $field['name'])` / `$errors->has('form.' . $field['name'])` for
every field, but `EntityFormPage::save()`'s `validator($payload, ...)->validate()` call
validates a plain array keyed by **unprefixed** field names (`code`, `name`, ...) — so any
`ValidationException` it threw populated the error bag under `code`/`name`/etc., never under
`form.code`/`form.name`. The view's lookup never matched, so a failed save (e.g. a duplicate
`code`, which the "employees" entity uniquely validates) produced **no visible error at all**
— the form just silently didn't save, with nothing telling the user why. Confirmed the error
bag's actual keys via a direct reproduction (`tests/Feature/EntityFormPageRedirectTest.php`'s
new "exposes validation errors under the plain field name" test) before fixing. `centrex/laravel-inventory`'s
equivalent `EntityFormPage`/view already used the correct unprefixed form (`$errors->first($field['name'])`)
— used as the reference fix. The identical bug existed in `laravel-payroll`'s own
`form-page.blade.php` (same original scaffold) — fixed there too.

**Fixed** (2026-08-02): `src/Http/Livewire/Entities/EntityFormPage.php::save()` declared
`: \Illuminate\Http\RedirectResponse` and did `return redirect()->route(...)`. Inside a real
Livewire component dispatch, Livewire's `SupportRedirects` hook temporarily rebinds the
container's `redirect` entry so the global `redirect()` helper returns
`Livewire\Features\SupportRedirects\Redirector` instead — a type mismatch PHP enforces as a
fatal `TypeError` the instant the method returns. The identical bug was confirmed crashing
production in the sibling `laravel-payroll` package's own `EntityFormPage.php` (same original
scaffold); this package had the same latent bug, just not yet observed in the wild. Fixed by
using the component's own `$this->redirect(route(...))` instead of returning the global
helper's value (also correctly skips the now-redundant re-render); `save()` now returns
`void`. Added `tests/Feature/EntityFormPageRedirectTest.php` as a regression test — it
exercises `save()` via direct instantiation rather than `Livewire::test()`, since even
mounting this component through the real Livewire test harness renders its view, which uses
`<x-tallui-*>` components this package doesn't install as a test dependency (supplied by the
host app in production); the container-swap that caused the crash is orthogonal to that and
was confirmed by reading Livewire's `SupportRedirects` source directly. Also added
`Livewire\LivewireServiceProvider::class` to `tests/TestCase.php::getPackageProviders()`,
which was missing (needed for any test that exercises a Livewire component directly).

## Style / static-analysis debt

- `vendor/bin/pint --test` reports **11 files** with unapplied fixers, mostly `new_with_braces` (and `class_definition`/`braces_position` in several migration files): `src/HrServiceProvider.php`, `src/Support/HrEntityRegistry.php`, `src/Http/Livewire/AttendanceManagementPage.php`, `src/Http/Livewire/MyLeavePage.php`, `database/migrations/2026_07_27_000001_create_hr_zkteco_devices_table.php`, `database/migrations/2026_05_01_000001_create_hr_tables.php`, `database/migrations/2026_07_06_000001_add_user_id_to_hr_employees.php`, `database/migrations/2026_07_27_000002_add_zkteco_fields_to_hr_employees.php`, `database/migrations/2026_07_07_000001_add_sbu_code_to_hr_employees.php`, `tests/Feature/HrManagementTest.php`, `tests/Feature/HrChartsCardTest.php`. Run `composer lint` to apply.
- `vendor/bin/rector --dry-run` reports **20 files** would change, from `AddOverrideAttributeToOverriddenMethodsRector`, `AddArrowFunctionReturnTypeRector`, and `AddVoidReturnTypeWhereNoReturnRector`. Run `composer refacto` to apply.
- `vendor/bin/phpstan analyse` (level `max`) reports **211 errors**, despite `phpstan-baseline.neon` already baselining 24 error signatures — these 211 are unbaselined/live. Two things stand out as more than generic "missing generic type" noise:
  - **`src/Support/PayrollSync.php`** (lines 32, 34, 41-60, 71) — `Class Centrex\Payroll\Models\Employee not found` plus ~20 "access to an undefined property" errors on `Centrex\Hr\Models\Employee`/`Department`/`Designation` (e.g. `$payroll_profile_id`, `$code`, `$name`, `$monthly_salary`, `$bank_account_name`, …). **Investigated (2026-08-02) and confirmed NOT schema drift**: every property `PayrollSync` reads/writes is a real column and `$fillable` entry on both sides (verified against both packages' current migrations); `PayrollSync.php` and its observer were introduced together in one commit and have evolved in lockstep with `laravel-payroll`'s `Employee` schema ever since (both added `sbu_code` in paired commits). The "class not found" error is expected — `centrex/laravel-payroll` isn't required by this package's `composer.json` at all (soft dependency, guarded by `class_exists()`), so isolated analysis can't resolve it. The "undefined property" errors are a pre-existing Larastan schema-introspection limitation affecting multiple models in this package (they override `$connection` dynamically via `config('hr.drivers.database.connection', ...)`), not specific to `PayrollSync`. A live end-to-end test in `laravel-erp` (`HR_PAYROLL_SYNC_ENABLED=true`) confirmed the sync itself works correctly today.
  - **`src/Support/Zkteco/JmrashedZktecoClient.php`** (previously `RatsZktecoClient.php`; swapped 2026-08-15 to `jmrashed/zkteco`, a near-identical fork of the same original protocol code) — `Instantiated class \Jmrashed\Zkteco\Lib\ZKTeco not found` and subsequent "call to undefined method" on the resulting object — the `jmrashed/zkteco` dependency doesn't appear to be present/autoloadable in this analysis context.
  - The remainder are the usual Larastan "generic relation return type not specified" / "iterable value type not specified" / "undefined property" (missing `@property` docblocks) noise spread across `src/Models/LeaveRequest.php`, `LeaveType.php`, `ZktecoDevice.php`, and `src/Support/HrEntityRegistry.php` (dynamic model resolution via `newQuery()`/`getTable()` on `object`-typed values).

## TODO / FIXME markers

None found (`grep -rn "TODO\|FIXME" --include="*.php" src/ config/ database/`).

## Open GitHub issues

Not checked — the `gh` CLI is not installed in this environment.
