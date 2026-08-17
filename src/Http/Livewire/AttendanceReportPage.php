<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Livewire;

use Centrex\Hr\Facades\Hr;
use Centrex\Hr\Models\{Attendance, Department, Employee};
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportPage extends Component
{
    public string $departmentFilter = '';

    public string $employeeFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(): void
    {
        Gate::authorize('hr.attendance.view');

        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function updatingDepartmentFilter(): void
    {
        $this->employeeFilter = '';
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->summaryRows();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Employee', 'Department', 'Present', 'Absent', 'On Leave', 'Weekend', 'Working Days', 'Attendance %', 'Worked Hours']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['employee']->name,
                    $row['employee']->department?->name,
                    $row['present'],
                    $row['absent'],
                    $row['on_leave'],
                    $row['weekend'],
                    $row['working_days'],
                    $row['attendance_rate'],
                    $row['worked_hours'],
                ]);
            }

            fclose($handle);
        }, 'attendance-report-' . $this->fromDate . '-to-' . $this->toDate . '.csv');
    }

    /**
     * Per-employee attendance breakdown for the selected filters/date range, built on top
     * of Hr::getAttendanceSummary() (the same day-by-day present/absent/on_leave/weekend
     * reconstruction used by the dashboard) so this report stays consistent with the rest
     * of the package instead of re-deriving state from the raw `status` column.
     *
     * @return Collection<int, array{employee: Employee, present: int, absent: int, on_leave: int, weekend: int, working_days: int, attendance_rate: float, worked_hours: float}>
     */
    public function summaryRows(): Collection
    {
        return $this->filteredEmployees()->map(function (Employee $employee): array {
            $summary = Hr::getAttendanceSummary($employee, $this->fromDate, $this->toDate);

            $workedHours = (float) Attendance::where('employee_id', $employee->id)
                ->whereBetween('work_date', [$this->fromDate, $this->toDate])
                ->sum('worked_hours');

            $workingDays = $summary['present'] + $summary['absent'] + $summary['on_leave'];

            return [
                'employee'        => $employee,
                'present'         => $summary['present'],
                'absent'          => $summary['absent'],
                'on_leave'        => $summary['on_leave'],
                'weekend'         => $summary['weekend'],
                'working_days'    => $workingDays,
                'attendance_rate' => $workingDays > 0 ? round($summary['present'] / $workingDays * 100, 1) : 0.0,
                'worked_hours'    => round($workedHours, 2),
            ];
        });
    }

    /**
     * @param  Collection<int, array{employee: Employee, present: int, absent: int, on_leave: int, weekend: int, working_days: int, attendance_rate: float, worked_hours: float}>  $rows
     * @return array{present: int, absent: int, on_leave: int, weekend: int, working_days: int, attendance_rate: float, worked_hours: float}
     */
    public function totals(Collection $rows): array
    {
        $present = (int) $rows->sum('present');
        $workingDays = (int) $rows->sum('working_days');

        return [
            'present'         => $present,
            'absent'          => (int) $rows->sum('absent'),
            'on_leave'        => (int) $rows->sum('on_leave'),
            'weekend'         => (int) $rows->sum('weekend'),
            'working_days'    => $workingDays,
            'attendance_rate' => $workingDays > 0 ? round($present / $workingDays * 100, 1) : 0.0,
            'worked_hours'    => round((float) $rows->sum('worked_hours'), 2),
        ];
    }

    /**
     * @return Collection<int, Employee>
     */
    private function filteredEmployees(): Collection
    {
        return Employee::where('is_active', true)
            ->when($this->departmentFilter, fn ($q) => $q->where('department_id', $this->departmentFilter))
            ->when($this->employeeFilter, fn ($q) => $q->where('id', $this->employeeFilter))
            ->with('department')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        $rows = $this->summaryRows();

        return view('hr::livewire.attendance-report', [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'employees'   => Employee::where('is_active', true)->orderBy('name')->get(),
            'rows'        => $rows,
            'totals'      => $this->totals($rows),
        ]);
    }
}
