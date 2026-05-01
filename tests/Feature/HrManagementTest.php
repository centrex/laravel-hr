<?php

declare(strict_types = 1);

use Centrex\Hr\Models\{Department, Designation, Employee, LeaveRequest, LeaveType};
use Centrex\Hr\Support\HrEntityRegistry;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('migrate', ['--database' => 'testing']);
});

it('uses the configured hr table prefix', function (): void {
    config()->set('hr.table_prefix', 'people_');

    expect((new Employee())->getTable())->toBe('people_employees');
});

it('creates an employee with department and designation details', function (): void {
    $department = Department::query()->create([
        'code' => 'OPS',
        'name' => 'Operations',
    ]);

    $designation = Designation::query()->create([
        'code'          => 'OPS-MGR',
        'name'          => 'Operations Manager',
        'department_id' => $department->getKey(),
        'salary_min'    => 50000,
        'salary_max'    => 90000,
    ]);

    $employee = Employee::query()->create([
        'code'            => 'E-1001',
        'name'            => 'Amina Rahman',
        'email'           => 'amina@example.test',
        'department_id'   => $department->getKey(),
        'designation_id'  => $designation->getKey(),
        'employment_type' => 'full_time',
        'status'          => 'active',
        'monthly_salary'  => 75000,
        'currency'        => 'BDT',
    ]);

    expect($employee->department->name)->toBe('Operations')
        ->and($employee->designation->name)->toBe('Operations Manager');
});

it('tracks leave requests for employees', function (): void {
    $employee = Employee::query()->create([
        'code' => 'E-1002',
        'name' => 'Nadia Islam',
    ]);

    $leaveType = LeaveType::query()->create([
        'code'             => 'AL',
        'name'             => 'Annual Leave',
        'annual_allowance' => 18,
    ]);

    $leave = LeaveRequest::query()->create([
        'employee_id'   => $employee->getKey(),
        'leave_type_id' => $leaveType->getKey(),
        'starts_at'     => '2026-05-04',
        'ends_at'       => '2026-05-06',
        'days'          => 3,
        'status'        => 'pending',
        'reason'        => 'Family event',
    ]);

    expect($employee->leaveRequests)->toHaveCount(1)
        ->and($leave->leaveType->name)->toBe('Annual Leave');
});

it('normalizes registry payloads consistently with sibling packages', function (): void {
    $payload = HrEntityRegistry::fillablePayload('employees', [
        'code'     => ' E-1003 ',
        'name'     => '  Rafi Khan ',
        'currency' => 'usd',
    ]);

    expect($payload['code'])->toBe('E-1003')
        ->and($payload['name'])->toBe('Rafi Khan')
        ->and($payload['currency'])->toBe('USD')
        ->and($payload['is_active'])->toBeTrue();
});
