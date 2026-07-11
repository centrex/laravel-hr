<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Livewire;

use Centrex\Hr\Facades\Hr;
use Centrex\Hr\Models\{Attendance, Employee};
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\{Component, WithPagination};

#[Layout('layouts.app')]
class AttendanceManagementPage extends Component
{
    use WithPagination;

    public string $employeeFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    // Entry modal
    public bool $showModal = false;

    public ?int $employeeId = null;

    public string $workDate = '';

    public string $checkIn = '';

    public string $checkOut = '';

    public string $status = 'present';

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('hr.attendance.manage');

        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function updatingEmployeeFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->employeeId = null;
        $this->workDate = now()->toDateString();
        $this->checkIn = '';
        $this->checkOut = '';
        $this->status = 'present';
        $this->notes = '';
        $this->showModal = true;
    }

    public function openEdit(int $attendanceId): void
    {
        $attendance = Attendance::findOrFail($attendanceId);

        $this->employeeId = $attendance->employee_id;
        $this->workDate = \Illuminate\Support\Carbon::parse($attendance->work_date)->toDateString();
        $this->checkIn = $attendance->check_in ? \Illuminate\Support\Carbon::parse($attendance->check_in)->format('H:i') : '';
        $this->checkOut = $attendance->check_out ? \Illuminate\Support\Carbon::parse($attendance->check_out)->format('H:i') : '';
        $this->status = $attendance->status ?: 'present';
        $this->notes = (string) $attendance->notes;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'employeeId' => 'required|integer|exists:' . (new Employee())->getTable() . ',id',
            'workDate'   => 'required|date',
            'checkIn'    => 'nullable|date_format:H:i',
            'checkOut'   => 'nullable|date_format:H:i',
            'status'     => 'required|in:present,absent,late,on_leave,half_day',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $employee = Employee::findOrFail($this->employeeId);

        $checkIn = $this->checkIn ? $this->workDate . ' ' . $this->checkIn : null;
        $checkOut = $this->checkOut ? $this->workDate . ' ' . $this->checkOut : null;

        Hr::recordAttendance($employee, [
            'work_date' => $this->workDate,
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
            'status'    => $this->status,
            'notes'     => $this->notes ?: null,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Attendance recorded.');
        $this->showModal = false;
    }

    public function render(): View
    {
        $employees = Employee::where('is_active', true)->orderBy('name')->get();

        $records = Attendance::query()
            ->with('employee')
            ->when($this->employeeFilter, fn ($q) => $q->where('employee_id', $this->employeeFilter))
            ->whereBetween('work_date', [$this->fromDate, $this->toDate])
            ->orderByDesc('work_date')
            ->paginate((int) config('hr.per_page.attendances', 31));

        return view('hr::livewire.attendance-management', [
            'employees' => $employees,
            'records'   => $records,
        ]);
    }
}
