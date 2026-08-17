<?php

declare(strict_types = 1);

use Centrex\Hr\Http\Livewire\AttendanceReportPage;
use Centrex\Hr\Models\{Attendance, Department, Employee, LeaveRequest, LeaveType};
use Illuminate\Support\Facades\{Artisan, Gate};

beforeEach(function (): void {
    Artisan::call('migrate', ['--database' => 'testing']);
    Gate::define('hr.attendance.view', fn ($user = null): bool => true);
});

// 2026-03-02 (Mon) through 2026-03-06 (Fri); Friday falls in the default
// weekend_days config ([5, 6] — Fri/Sat), so this range has exactly 4 working days.
function makeReportComponent(): AttendanceReportPage
{
    $component = new AttendanceReportPage;
    $component->mount();
    $component->fromDate = '2026-03-02';
    $component->toDate = '2026-03-06';

    return $component;
}

it('summarizes attendance per employee over the date range', function (): void {
    $department = Department::query()->create(['code' => 'OPS', 'name' => 'Operations']);

    $employeeA = Employee::query()->create([
        'code' => 'E-1', 'name' => 'Amina Rahman', 'department_id' => $department->getKey(), 'is_active' => true,
    ]);
    $employeeB = Employee::query()->create(['code' => 'E-2', 'name' => 'Rafi Khan', 'is_active' => true]);

    foreach (['2026-03-02', '2026-03-03', '2026-03-04'] as $date) {
        Attendance::query()->create([
            'employee_id'  => $employeeA->getKey(), 'work_date' => $date,
            'check_in'     => $date . ' 09:00:00', 'check_out' => $date . ' 17:00:00',
            'worked_hours' => 8, 'status' => 'present',
        ]);
    }
    // 2026-03-05 (Thu) left with no attendance and no leave — counts as absent.

    foreach (['2026-03-02', '2026-03-03'] as $date) {
        Attendance::query()->create([
            'employee_id'  => $employeeB->getKey(), 'work_date' => $date,
            'check_in'     => $date . ' 09:00:00', 'check_out' => $date . ' 17:00:00',
            'worked_hours' => 8, 'status' => 'present',
        ]);
    }

    $leaveType = LeaveType::query()->create(['code' => 'AL', 'name' => 'Annual Leave', 'annual_allowance' => 18]);
    LeaveRequest::query()->create([
        'employee_id' => $employeeB->getKey(), 'leave_type_id' => $leaveType->getKey(),
        'starts_at'   => '2026-03-04', 'ends_at' => '2026-03-05', 'days' => 2, 'status' => 'approved',
    ]);

    $component = makeReportComponent();
    $rows = $component->summaryRows()->keyBy(fn (array $row): int => $row['employee']->id);

    expect($rows[$employeeA->id])
        ->present->toBe(3)
        ->absent->toBe(1)
        ->on_leave->toBe(0)
        ->weekend->toBe(1)
        ->working_days->toBe(4)
        ->attendance_rate->toBe(75.0)
        ->worked_hours->toBe(24.0);

    expect($rows[$employeeB->id])
        ->present->toBe(2)
        ->absent->toBe(0)
        ->on_leave->toBe(2)
        ->weekend->toBe(1)
        ->working_days->toBe(4)
        ->attendance_rate->toBe(50.0)
        ->worked_hours->toBe(16.0);

    $totals = $component->totals($rows->values());

    expect($totals)->toBe([
        'present'         => 5,
        'absent'          => 1,
        'on_leave'        => 2,
        'weekend'         => 2,
        'working_days'    => 8,
        'attendance_rate' => 62.5,
        'worked_hours'    => 40.0,
    ]);
});

it('filters the report to a single department', function (): void {
    $ops = Department::query()->create(['code' => 'OPS', 'name' => 'Operations']);
    $sales = Department::query()->create(['code' => 'SLS', 'name' => 'Sales']);

    $opsEmployee = Employee::query()->create(['code' => 'E-1', 'name' => 'Amina Rahman', 'department_id' => $ops->getKey(), 'is_active' => true]);
    Employee::query()->create(['code' => 'E-2', 'name' => 'Rafi Khan', 'department_id' => $sales->getKey(), 'is_active' => true]);

    $component = makeReportComponent();
    $component->departmentFilter = (string) $ops->getKey();

    $rows = $component->summaryRows();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['employee']->id)->toBe($opsEmployee->id);
});

it('excludes inactive employees from the report', function (): void {
    Employee::query()->create(['code' => 'E-1', 'name' => 'Left Company', 'is_active' => false]);
    $active = Employee::query()->create(['code' => 'E-2', 'name' => 'Still Here', 'is_active' => true]);

    $component = makeReportComponent();
    $rows = $component->summaryRows();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['employee']->id)->toBe($active->id);
});
