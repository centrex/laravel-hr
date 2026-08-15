# CLAUDE.md

## Package Overview

`centrex/laravel-hr` — HR module for Laravel: employees, departments, designations, leave requests/approvals, and daily attendance (check-in/out). Livewire UI + REST API. Works fully standalone; optionally mirrors employees into `laravel-payroll` for payroll processing.

Namespace: `Centrex\Hr\`
Service Provider: `HrServiceProvider`
Facade: `Facades\Hr` → resolves `Centrex\Hr\Hr` singleton (also aliased as `hr` and `laravel-hr`)

## Commands

Run from inside this directory (`cd laravel-hr`):

```sh
composer install          # install dependencies
composer test              # full suite: rector dry-run, pint check, phpstan, pest
composer test:unit         # pest tests only
composer test:lint         # pint style check (read-only)
composer test:types        # phpstan static analysis
composer test:refacto      # rector refactor check (read-only)
composer lint               # apply pint formatting
composer refacto             # apply rector refactors
composer analyse              # phpstan (alias)
composer build                 # prepare testbench workbench
composer start                  # build + serve testbench dev server
```

Run a single test:

```sh
vendor/bin/pest tests/Feature/SomeTest.php
vendor/bin/pest --filter "test name"
```

## Structure

```
src/
  Hr.php                      # Main facade target — attendance + leave logic
  HrServiceProvider.php
  Facades/Hr.php
  Concerns/AddTablePrefix.php
  Contracts/ZktecoClient.php  # connect/disconnect/getAttendanceLogs — decouples ZktecoSync from the vendor SDK
  Exceptions/                 # AlreadyCheckedInException, NotCheckedInException, AlreadyCheckedOutException,
                               # InsufficientLeaveBalanceException, InvalidLeaveRequestStatusException,
                               # UnauthorizedLeaveApprovalException, ZktecoNotConfiguredException
  Models/                     # Employee, Department, Designation, LeaveType, LeaveRequest, Attendance, ZktecoDevice
  Observers/EmployeePayrollObserver.php
  Support/
    HrEntityRegistry.php      # Generic CRUD entity definitions (Departments/Designations/Employees/Leave Types/ZKTeco Devices)
    PayrollSync.php           # Mirrors an Employee into laravel-payroll's own Employee record
    ZktecoSync.php            # Pulls punches from a ZktecoDevice, writes them via Hr::recordAttendance()
    Zkteco/JmrashedZktecoClient.php  # ZktecoClient implementation wrapping the suggested jmrashed/zkteco package
  Http/
    Livewire/
      HrDashboard.php
      AttendanceManagementPage.php   # HR/admin attendance editor
      MyAttendancePage.php           # Self-service check-in/out
      MyLeavePage.php                # Self-service leave requests
      LeaveApprovalsPage.php         # Approver queue (admin sees all, manager sees direct reports)
      Entities/{EntityIndexPage,EntityFormPage}.php
    Controllers/Api/EntityCrudController.php
    Resources/EmployeeResource.php
  Commands/
    HrCommand.php             # `hr:install` — prints publish/migrate guidance, does not run it
    ZktecoSyncCommand.php     # `hr:zkteco:sync` — pulls attendance punches from ZKTeco device(s)
config/config.php
database/migrations/
routes/
  web.php
  api.php
resources/views/
tests/
workbench/
```

No `Enums/` directory — `status`/`employment_type` fields are plain string columns, not backed by native PHP enums.

## Core Concepts

- **Employee** — `code`, optional `user_id` (links to the auth user model), `department_id`/`designation_id` (FK), `manager_id` (self-referencing FK — the reporting chain used for leave approval scoping), `employment_type`, `status`, `monthly_salary`, bank/tax/emergency-contact fields. `sbu_code` (free-text, nullable) tags which company/business-unit the employee belongs to — same convention as `sbu_code` on `laravel-accounting`'s documents — and flows through `PayrollSync` into `laravel-payroll`, where it constrains which employees can share one payroll run (see that package's `AccountingSync`). `nullableMorphs('payroll_profile')` links out to a synced `laravel-payroll` employee.
- **Department** / **Designation** — simple master data; a Designation optionally belongs to a Department, and both can have a salary band (`salary_min`/`salary_max` on Designation).
- **Attendance** — one row per `(employee_id, work_date)` (unique constraint — this is what makes `checkIn()`/`recordAttendance()` idempotent). `status`: `present` | `absent` | `late` | `on_leave` | `half_day`. `worked_hours` is computed from `check_in`/`check_out` when both are present.
- **LeaveType** — `annual_allowance` (days/year), `is_paid`, `requires_approval`.
- **LeaveRequest** — `status`: `pending` → `approved` | `rejected`, or `pending`/`approved` → `cancelled`. `days` defaults to the inclusive date-range length if not given.
- **ZktecoDevice** — one row per ZKTeco biometric device (`name`, `ip_address`, `port` default `4370`, optional `comm_key`, `sbu_code` to tag which branch it belongs to, `last_synced_at`). `Employee.zkteco_device_id` + `Employee.zkteco_user_id` (unique together) link an employee to their enrolled device-local user id — the same numeric id can be reused across different devices/branches, just not twice on the same device.

## Usage

### Attendance

```php
use Centrex\Hr\Facades\Hr;

$employee = Hr::employeeForUser(auth()->id()); // Employee::where('user_id', $userId)->first()

// Self-service check-in/out — idempotent per day, throw on repeat/out-of-order calls:
Hr::checkIn($employee);                         // throws AlreadyCheckedInException if already checked in today
Hr::checkOut($employee);                        // throws NotCheckedInException / AlreadyCheckedOutException

// HR/admin manual entry — upserts by (employee_id, work_date), recomputes worked_hours:
Hr::recordAttendance($employee, [
    'work_date' => '2026-02-15', 'check_in' => '09:00', 'check_out' => '18:00',
    'status' => 'present', 'notes' => 'Manual correction',
]);

Hr::isWeekend('2026-02-13');                     // true if Carbon dayOfWeek is in config('hr.weekend_days', [5,6]) — Fri/Sat by default
Hr::isAbsent($employee, '2026-02-15');           // false on weekends, if checked in, or covered by approved leave

Hr::getAttendanceSummary($employee, '2026-02-01', '2026-02-29');
// ['present' => int, 'absent' => int, 'on_leave' => int, 'weekend' => int,
//  'days' => Collection<{date, state}>]
```

### ZKTeco device attendance sync

Pulls punches from ZKTeco biometric time clocks on the same LAN into the same `Attendance`
table used above, by connecting out to each device (port `4370`) on a schedule — not a
push/webhook receiver, since that protocol is only needed when devices sit behind NAT at
remote sites. Requires `composer require jmrashed/zkteco` (suggested, not a hard dependency —
the rest of the package works without it).

```php
use Centrex\Hr\Models\ZktecoDevice;
use Centrex\Hr\Support\ZktecoSync;

$device = ZktecoDevice::create([
    'name' => 'Main Gate', 'ip_address' => '192.168.1.201', 'sbu_code' => 'DHAKA',
]);

// Link an employee to their enrolled device-user id (or use the Employee entity form —
// zkteco_device_id / zkteco_user_id fields are on it via HrEntityRegistry):
$employee->update(['zkteco_device_id' => $device->id, 'zkteco_user_id' => '7']);

app(ZktecoSync::class)->syncDevice($device);
// ['device_id' => int, 'synced' => int, 'unmatched' => array<string device_user_id, int punch_count>]
```

Per employee per day: earliest punch → `check_in`, latest (if more than one) → `check_out`,
written through the existing `Hr::recordAttendance()` upsert — so worked-hours computation
and the `(employee_id, work_date)` idempotency guarantee are inherited for free, and any
`notes` an HR user already wrote for that day survive a re-sync untouched. An unrecognized
device-user-id is skipped and counted in `unmatched`, not thrown — one unenrolled punch
shouldn't abort the whole batch. `config('hr.zkteco.late_after')` (e.g. `'09:15'`) marks a
day `late` instead of `present` when the first punch is after that time.

Run `php artisan hr:zkteco:sync` (optionally `--device=1 --device=2` to target specific
`ZktecoDevice` IDs; default is all `is_active` devices) — schedule it from the host app, e.g.
`Schedule::command('hr:zkteco:sync')->everyFiveMinutes()`.

### Leave

```php
Hr::getLeaveBalance($employee, $leaveType, year: 2026);
// ['leave_type' => LeaveType, 'allowance' => float, 'used' => float, 'remaining' => float]

Hr::getLeaveBalances($employee, 2026); // same, mapped over every active LeaveType

// Throws InsufficientLeaveBalanceException if the type requires_approval and balance is short.
$request = Hr::requestLeave($employee, [
    'leave_type_id' => $leaveType->id, 'starts_at' => '2026-03-01', 'ends_at' => '2026-03-03',
    'reason' => 'Family event', // 'days' optional, defaults to date-range length
]);

// Approval is allowed if Gate::forUser($approver)->allows('hr.leave.approve'), OR the approver
// is the Employee whose manager_id matches the requester — otherwise throws UnauthorizedLeaveApprovalException.
Hr::approveLeave($request, $approver, notes: 'Approved');   // re-checks balance, throws InsufficientLeaveBalanceException if exceeded
Hr::rejectLeave($request, $approver, notes: 'Needs more notice');
Hr::cancelLeave($request); // only from pending or approved — else InvalidLeaveRequestStatusException
```

## Web UI routes (Livewire)

Two route groups, both under `config('hr.web_prefix')` (default `hr`), name prefix `hr.`:

| Route name | Path | Middleware | Component |
|---|---|---|---|
| `hr.dashboard` | `/hr/dashboard` | `web_middleware` (default `web,auth,can:hr.employees.view`) | `HrDashboard` |
| `hr.attendance` | `/hr/attendance` | `web_middleware` | `AttendanceManagementPage` |
| `hr.entities.{entity}.index` | `/hr/{entity}` | `web_middleware` | `EntityIndexPage` |
| `hr.entities.{entity}.create` | `/hr/{entity}/create` | `web_middleware` | `EntityFormPage` |
| `hr.entities.{entity}.edit` | `/hr/{entity}/{recordId}/edit` | `web_middleware` | `EntityFormPage` |
| `hr.my-attendance` | `/hr/my-attendance` | `self_service_middleware` (default `web,auth` — no `can:` gate) | `MyAttendancePage` |
| `hr.my-leave` | `/hr/my-leave` | `self_service_middleware` | `MyLeavePage` |
| `hr.leave-approvals` | `/hr/leave-approvals` | `self_service_middleware` | `LeaveApprovalsPage` |

`{entity}` is one of `departments`, `designations`, `employees`, `leave-types`, `zkteco-devices` (`HrEntityRegistry::masterDataEntities()`). Note `LeaveRequest` and `Attendance` are **not** generic entities — they're managed only through the dedicated Livewire pages above, not `EntityIndexPage`/`EntityFormPage`.

## REST API

Base prefix: `api/hr` (configurable). Default middleware: `['api', 'auth:sanctum', 'can:hr.employees.view']`. Route name prefix `hr.api.`.

| Method | Endpoint | Action |
|---|---|---|
| GET | `/api/hr/{entity}` | list |
| POST | `/api/hr/{entity}` | create |
| GET | `/api/hr/{entity}/{recordId}` | show |
| PUT/PATCH | `/api/hr/{entity}/{recordId}` | update |
| DELETE | `/api/hr/{entity}/{recordId}` | delete |

Same 5 entities as the web UI (`departments`, `designations`, `employees`, `leave-types`, `zkteco-devices`), all via `EntityCrudController`. No API endpoints for leave requests, approvals, or attendance — those are Livewire-only today. No API for triggering a ZKTeco sync either — that's the `hr:zkteco:sync` Artisan command only.

## Authorization gates

Registered unconditionally in `HrServiceProvider::boot()`. All 9 gates use the *identical* fallback logic (they don't differentiate by resource/action internally — defined separately so a host app can override any one individually):

`hr.employees.view` / `.manage`, `hr.departments.view` / `.manage`, `hr.leave.view` / `.request` / `.approve`, `hr.attendance.view` / `.manage`

Fallback: passes if a host-defined `hr-admin` gate allows the user, else if the user model has `hasRole()`, checks it against `config('hr.admin_roles')` (default `admin,hr-admin`), else denies.

`hr.leave.approve` is checked directly in code (`Hr::assertCanApprove()`, `LeaveApprovalsPage::render()`), not just via route middleware.

## Environment Variables

```env
HR_DB_CONNECTION=
HR_TABLE_PREFIX=hr_
HR_WEB_ENABLED=true
HR_API_ENABLED=true
HR_CURRENCY=BDT             # falls back to PAYROLL_CURRENCY, then 'BDT', if unset
HR_PAYROLL_SYNC_ENABLED=false
HR_ZKTECO_ENABLED=false
HR_ZKTECO_LATE_AFTER=        # e.g. 09:15 — nullable, off by default
```

`weekend_days` (default `[5, 6]` — Fri/Sat) and `admin_roles` (default `admin,hr-admin`) are array config values, not single env-backed strings.

## Cross-package integration

- **`laravel-payroll`** (optional, outbound) — `Support\PayrollSync::syncEmployee()` mirrors an HR `Employee` into `Centrex\Payroll\Models\Employee`, linked via the same polymorphic-pair pattern `laravel-inventory`'s `ErpIntegration` uses for customers/suppliers: `Employee::payroll_profile_type`/`payroll_profile_id` here, `Payroll\Employee::modelable_type`/`modelable_id` on the payroll side. Wired via `Employee::observe(EmployeePayrollObserver::class)` — but **only attached at all** when `hr.payroll_sync.enabled=true` AND `class_exists(\Centrex\Payroll\Models\Employee::class)`, so the package is fully inert (no observer, no coupling) when payroll isn't installed or sync is off. The observer fires `syncEmployee()` on every `Employee::saved` (create or update).
- No other Centrex package integrations exist — `laravel-hr` doesn't touch `laravel-accounting` or `laravel-inventory` directly.

## External hardware integration

- **ZKTeco biometric devices** (optional, `jmrashed/zkteco` suggested — not required) — `Support\ZktecoSync::syncDevice()` connects out to a `ZktecoDevice`'s `ip_address:port`, pulls its attendance log via `Contracts\ZktecoClient` (implemented by `Support\Zkteco\JmrashedZktecoClient`, which only ever touches the vendor SDK by dynamic class-string instantiation — never a direct type-hint — so this package stays fully loadable, and `composer test:types` stays clean, whether or not `jmrashed/zkteco` is installed), and writes punches through the same `Hr::recordAttendance()` upsert used by the manual-entry UI. Guarded by `config('hr.zkteco.enabled')` at the config level and a `class_exists()` check inside `ZktecoSync` itself (throws `ZktecoNotConfiguredException` if the vendor package is missing) — there's no service-provider-level observer to gate, since nothing runs until `hr:zkteco:sync` is invoked. The vendor SDK hardcodes a 60.5s UDP socket receive timeout with no constructor option to change it; `JmrashedZktecoClient` reaches into its public `_zkclient` socket resource and overrides `SO_RCVTIMEO` down to `config('hr.zkteco.connect_timeout', 5)` seconds so an unreachable device fails fast instead of blocking `hr:zkteco:sync` for a full minute per device.

## Conventions

- PHP 8.3+, `declare(strict_types=1)` in all files
- Pest for tests, snake_case test names
- Pint with `laravel` preset
- Rector targeting PHP 8.3 with `CODE_QUALITY`, `DEAD_CODE`, `EARLY_RETURN`, `TYPE_DECLARATION`, `PRIVATIZATION` sets
- PHPStan at level `max` with Larastan
