<?php

declare(strict_types = 1);

namespace Centrex\Hr;

use Centrex\Hr\Exceptions\{AlreadyCheckedInException, AlreadyCheckedOutException, InsufficientLeaveBalanceException, InvalidLeaveRequestStatusException, NotCheckedInException, UnauthorizedLeaveApprovalException};
use Centrex\Hr\Models\{Attendance, Employee, LeaveRequest, LeaveType};
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\Gate;

class Hr
{
    // ── Attendance ────────────────────────────────────────────────────────────

    public function employeeForUser(?int $userId = null): ?Employee
    {
        $userId ??= auth()->id();

        if (!$userId) {
            return null;
        }

        return Employee::where('user_id', $userId)->first();
    }

    public function checkIn(Employee $employee, \DateTimeInterface|string|null $at = null): Attendance
    {
        $at = Carbon::parse($at ?? now());
        $date = $at->toDateString();

        $attendance = Attendance::firstOrNew(['employee_id' => $employee->id, 'work_date' => $date]);

        if ($attendance->exists && $attendance->check_in) {
            throw new AlreadyCheckedInException("Employee [{$employee->id}] already checked in on [{$date}].");
        }

        $attendance->check_in = $at;
        $attendance->status = 'present';
        $attendance->save();

        return $attendance;
    }

    public function checkOut(Employee $employee, \DateTimeInterface|string|null $at = null): Attendance
    {
        $at = Carbon::parse($at ?? now());
        $date = $at->toDateString();

        $attendance = Attendance::where('employee_id', $employee->id)->where('work_date', $date)->first();

        if (!$attendance || !$attendance->check_in) {
            throw new NotCheckedInException("Employee [{$employee->id}] has not checked in on [{$date}].");
        }

        if ($attendance->check_out) {
            throw new AlreadyCheckedOutException("Employee [{$employee->id}] already checked out on [{$date}].");
        }

        $attendance->check_out = $at;
        $attendance->worked_hours = round(Carbon::parse($attendance->check_in)->diffInMinutes($at) / 60, 2);
        $attendance->save();

        return $attendance;
    }

    /**
     * HR/admin manual entry or edit — upserts by (employee, work_date) so backfilling or
     * correcting a day is idempotent.
     */
    public function recordAttendance(Employee $employee, array $data): Attendance
    {
        $date = Carbon::parse($data['work_date'])->toDateString();

        $attendance = Attendance::firstOrNew(['employee_id' => $employee->id, 'work_date' => $date]);
        $attendance->fill([
            'check_in'  => $data['check_in'] ?? $attendance->check_in,
            'check_out' => $data['check_out'] ?? $attendance->check_out,
            'status'    => $data['status'] ?? ($attendance->status ?: 'present'),
            'notes'     => $data['notes'] ?? $attendance->notes,
        ]);

        if ($attendance->check_in && $attendance->check_out) {
            $attendance->worked_hours = round(Carbon::parse($attendance->check_in)->diffInMinutes(Carbon::parse($attendance->check_out)) / 60, 2);
        }

        $attendance->save();

        return $attendance;
    }

    public function isWeekend(\DateTimeInterface|string $date): bool
    {
        $weekendDays = config('hr.weekend_days', [5, 6]);

        return in_array(Carbon::parse($date)->dayOfWeek, $weekendDays, true);
    }

    /**
     * A working day counts as "absent" only when there's no attendance check-in and no
     * approved leave covering it — weekends are never absences.
     */
    public function isAbsent(Employee $employee, \DateTimeInterface|string $date): bool
    {
        $date = Carbon::parse($date);

        if ($this->isWeekend($date)) {
            return false;
        }

        $hasAttendance = Attendance::where('employee_id', $employee->id)
            ->where('work_date', $date->toDateString())
            ->whereNotNull('check_in')
            ->exists();

        if ($hasAttendance) {
            return false;
        }

        return !$this->onApprovedLeave($employee, $date);
    }

    private function onApprovedLeave(Employee $employee, \DateTimeInterface|string $date): bool
    {
        $date = Carbon::parse($date);

        return LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('starts_at', '<=', $date->toDateString())
            ->whereDate('ends_at', '>=', $date->toDateString())
            ->exists();
    }

    /**
     * Day-by-day breakdown between $from/$to (inclusive) for one employee: present,
     * absent, on_leave, or weekend — used by attendance reports and the dashboard.
     *
     * @return array{present: int, absent: int, on_leave: int, weekend: int, days: Collection}
     */
    public function getAttendanceSummary(Employee $employee, string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $a): string => Carbon::parse($a->work_date)->toDateString());

        $counts = ['present' => 0, 'absent' => 0, 'on_leave' => 0, 'weekend' => 0];
        $days = collect();

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $dateKey = $cursor->toDateString();

            $state = match (true) {
                $this->isWeekend($cursor)                       => 'weekend',
                $attendances->get($dateKey)?->check_in !== null => 'present',
                $this->onApprovedLeave($employee, $cursor)      => 'on_leave',
                default                                         => 'absent',
            };

            $counts[$state]++;
            $days->push(['date' => $dateKey, 'state' => $state]);
        }

        return [...$counts, 'days' => $days];
    }

    // ── Leave ─────────────────────────────────────────────────────────────────

    public function getLeaveBalance(Employee $employee, LeaveType $leaveType, ?int $year = null): array
    {
        $year ??= (int) now()->year;

        $used = (float) LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('status', 'approved')
            ->whereYear('starts_at', $year)
            ->sum('days');

        $allowance = (float) $leaveType->annual_allowance;

        return [
            'leave_type' => $leaveType,
            'allowance'  => $allowance,
            'used'       => $used,
            'remaining'  => max(0.0, $allowance - $used),
        ];
    }

    /** @return Collection<int, array> balances for every active leave type */
    public function getLeaveBalances(Employee $employee, ?int $year = null): Collection
    {
        return LeaveType::where('is_active', true)->get()
            ->map(fn (LeaveType $type): array => $this->getLeaveBalance($employee, $type, $year));
    }

    public function requestLeave(Employee $employee, array $data): LeaveRequest
    {
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $days = (float) ($data['days'] ?? ($startsAt->diffInDays($endsAt) + 1));

        if ($leaveType->requires_approval) {
            $balance = $this->getLeaveBalance($employee, $leaveType, (int) $startsAt->year);

            if ($days > $balance['remaining']) {
                throw new InsufficientLeaveBalanceException(
                    "Requested {$days} day(s) of [{$leaveType->name}] exceeds remaining balance of {$balance['remaining']}.",
                );
            }
        }

        return LeaveRequest::create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $leaveType->id,
            'starts_at'     => $startsAt,
            'ends_at'       => $endsAt,
            'days'          => $days,
            'status'        => 'pending',
            'reason'        => $data['reason'] ?? null,
        ]);
    }

    /**
     * An admin/HR user (passes the hr.leave.approve gate) may approve any request; a
     * manager may only approve requests from their own direct reports.
     */
    public function assertCanApprove(LeaveRequest $leaveRequest, $approver): void
    {
        if (Gate::forUser($approver)->allows('hr.leave.approve')) {
            return;
        }

        $approverEmployee = $this->employeeForUser($approver->id ?? null);

        if (!$approverEmployee || $leaveRequest->employee?->manager_id !== $approverEmployee->id) {
            throw new UnauthorizedLeaveApprovalException('You are not authorized to approve this leave request.');
        }
    }

    public function approveLeave(LeaveRequest $leaveRequest, $approver, ?string $notes = null): LeaveRequest
    {
        if ($leaveRequest->status !== 'pending') {
            throw new InvalidLeaveRequestStatusException("Leave request [{$leaveRequest->id}] is not pending.");
        }

        $this->assertCanApprove($leaveRequest, $approver);

        $leaveType = $leaveRequest->leaveType;

        if ($leaveType && $leaveType->requires_approval) {
            $balance = $this->getLeaveBalance($leaveRequest->employee, $leaveType, (int) Carbon::parse($leaveRequest->starts_at)->year);

            if ((float) $leaveRequest->days > $balance['remaining']) {
                throw new InsufficientLeaveBalanceException(
                    "Approving {$leaveRequest->days} day(s) of [{$leaveType->name}] would exceed remaining balance of {$balance['remaining']}.",
                );
            }
        }

        $leaveRequest->update([
            'status'         => 'approved',
            'approved_by'    => $approver->id ?? null,
            'approved_at'    => now(),
            'approval_notes' => $notes,
        ]);

        return $leaveRequest;
    }

    public function rejectLeave(LeaveRequest $leaveRequest, $approver, ?string $notes = null): LeaveRequest
    {
        if ($leaveRequest->status !== 'pending') {
            throw new InvalidLeaveRequestStatusException("Leave request [{$leaveRequest->id}] is not pending.");
        }

        $this->assertCanApprove($leaveRequest, $approver);

        $leaveRequest->update([
            'status'         => 'rejected',
            'approved_by'    => $approver->id ?? null,
            'approved_at'    => now(),
            'approval_notes' => $notes,
        ]);

        return $leaveRequest;
    }

    public function cancelLeave(LeaveRequest $leaveRequest): LeaveRequest
    {
        if (!in_array($leaveRequest->status, ['pending', 'approved'], true)) {
            throw new InvalidLeaveRequestStatusException("Leave request [{$leaveRequest->id}] can no longer be cancelled.");
        }

        $leaveRequest->update(['status' => 'cancelled']);

        return $leaveRequest;
    }
}
