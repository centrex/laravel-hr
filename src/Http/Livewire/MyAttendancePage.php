<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Livewire;

use Centrex\Hr\Exceptions\{AlreadyCheckedInException, AlreadyCheckedOutException, NotCheckedInException};
use Centrex\Hr\Facades\Hr;
use Centrex\Hr\Models\Attendance;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MyAttendancePage extends Component
{
    public function checkIn(): void
    {
        $employee = $this->employee();

        try {
            Hr::checkIn($employee);
            $this->dispatch('notify', type: 'success', message: 'Checked in at ' . now()->format('h:i A') . '.');
        } catch (AlreadyCheckedInException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function checkOut(): void
    {
        $employee = $this->employee();

        try {
            Hr::checkOut($employee);
            $this->dispatch('notify', type: 'success', message: 'Checked out at ' . now()->format('h:i A') . '.');
        } catch (NotCheckedInException|AlreadyCheckedOutException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    private function employee()
    {
        $employee = Hr::employeeForUser();

        abort_if(!$employee, 403, 'No HR employee record is linked to your account.');

        return $employee;
    }

    public function render(): View
    {
        $employee = $this->employee();

        $today = Attendance::where('employee_id', $employee->id)
            ->where('work_date', now()->toDateString())
            ->first();

        $history = Attendance::where('employee_id', $employee->id)
            ->orderByDesc('work_date')
            ->limit(30)
            ->get();

        $summary = Hr::getAttendanceSummary($employee, now()->startOfMonth()->toDateString(), now()->toDateString());

        $layout = view()->exists('layouts.app')
            ? 'layouts.app'
            : 'components.layouts.app';

        return view('hr::livewire.my-attendance', [
            'employee' => $employee,
            'today'    => $today,
            'history'  => $history,
            'summary'  => $summary,
        ])->layout($layout, ['title' => __('My Attendance')]);
    }
}
