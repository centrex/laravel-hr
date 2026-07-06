<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Livewire;

use Centrex\Hr\Exceptions\{InsufficientLeaveBalanceException, InvalidLeaveRequestStatusException, UnauthorizedLeaveApprovalException};
use Centrex\Hr\Facades\Hr;
use Centrex\Hr\Models\LeaveRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LeaveApprovalsPage extends Component
{
    public bool $showRejectModal = false;

    public ?int $rejectingId = null;

    public string $rejectNotes = '';

    public function approve(int $leaveRequestId): void
    {
        $leaveRequest = LeaveRequest::findOrFail($leaveRequestId);

        try {
            Hr::approveLeave($leaveRequest, auth()->user());
            $this->dispatch('notify', type: 'success', message: 'Leave request approved.');
        } catch (UnauthorizedLeaveApprovalException|InsufficientLeaveBalanceException|InvalidLeaveRequestStatusException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function openReject(int $leaveRequestId): void
    {
        $this->rejectingId = $leaveRequestId;
        $this->rejectNotes = '';
        $this->showRejectModal = true;
    }

    public function reject(): void
    {
        $leaveRequest = LeaveRequest::findOrFail($this->rejectingId);

        try {
            Hr::rejectLeave($leaveRequest, auth()->user(), $this->rejectNotes ?: null);
            $this->dispatch('notify', type: 'success', message: 'Leave request rejected.');
            $this->showRejectModal = false;
        } catch (UnauthorizedLeaveApprovalException|InvalidLeaveRequestStatusException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        $isAdmin = Gate::allows('hr.leave.approve');
        $managerEmployee = Hr::employeeForUser();

        $query = LeaveRequest::with(['employee', 'leaveType'])->where('status', 'pending');

        if (!$isAdmin) {
            abort_if(!$managerEmployee, 403, 'You are not authorized to approve leave requests.');

            $query->whereHas('employee', fn ($q) => $q->where('manager_id', $managerEmployee->id));
        }

        $pending = $query->orderBy('starts_at')->get();

        return view('hr::livewire.leave-approvals', [
            'pending' => $pending,
            'isAdmin' => $isAdmin,
        ]);
    }
}
