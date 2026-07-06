<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Livewire;

use Centrex\Hr\Exceptions\{InsufficientLeaveBalanceException, InvalidLeaveRequestStatusException};
use Centrex\Hr\Facades\Hr;
use Centrex\Hr\Models\{LeaveRequest, LeaveType};
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MyLeavePage extends Component
{
    public bool $showModal = false;

    public string $leaveTypeId = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $reason = '';

    public function openCreate(): void
    {
        $this->leaveTypeId = '';
        $this->startsAt = now()->toDateString();
        $this->endsAt = now()->toDateString();
        $this->reason = '';
        $this->showModal = true;
    }

    public function submitRequest(): void
    {
        $this->validate([
            'leaveTypeId' => 'required|integer|exists:' . (new LeaveType())->getTable() . ',id',
            'startsAt'    => 'required|date',
            'endsAt'      => 'required|date|after_or_equal:startsAt',
            'reason'      => 'nullable|string|max:1000',
        ]);

        try {
            Hr::requestLeave($this->employee(), [
                'leave_type_id' => $this->leaveTypeId,
                'starts_at'     => $this->startsAt,
                'ends_at'       => $this->endsAt,
                'reason'        => $this->reason ?: null,
            ]);

            $this->dispatch('notify', type: 'success', message: 'Leave request submitted.');
            $this->showModal = false;
        } catch (InsufficientLeaveBalanceException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function cancel(int $leaveRequestId): void
    {
        $leaveRequest = LeaveRequest::where('employee_id', $this->employee()->id)->findOrFail($leaveRequestId);

        try {
            Hr::cancelLeave($leaveRequest);
            $this->dispatch('notify', type: 'success', message: 'Leave request cancelled.');
        } catch (InvalidLeaveRequestStatusException $e) {
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

        $balances = Hr::getLeaveBalances($employee);

        $requests = LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType')
            ->orderByDesc('starts_at')
            ->limit(30)
            ->get();

        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->get();

        $layout = view()->exists('layouts.app')
            ? 'layouts.app'
            : 'components.layouts.app';

        return view('hr::livewire.my-leave', [
            'employee'   => $employee,
            'balances'   => $balances,
            'requests'   => $requests,
            'leaveTypes' => $leaveTypes,
        ])->layout($layout, ['title' => __('My Leave')]);
    }
}
