<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Livewire;

use Centrex\Hr\Models\{Attendance, Department, Designation, Employee, LeaveRequest};
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class HrDashboard extends Component
{
    public string $attendancePeriod = 'today';

    // ── Headcount ─────────────────────────────────────────────────────────────

    private function headcountStats(): array
    {
        $total = Employee::count();
        $active = Employee::where('is_active', true)->count();
        $newThisMonth = Employee::where('is_active', true)
            ->whereYear('joining_date', now()->year)
            ->whereMonth('joining_date', now()->month)
            ->count();
        $onLeaveToday = LeaveRequest::where('status', 'approved')
            ->where('starts_at', '<=', now()->toDateString())
            ->where('ends_at', '>=', now()->toDateString())
            ->count();

        return compact('total', 'active', 'newThisMonth', 'onLeaveToday');
    }

    // ── Leave ─────────────────────────────────────────────────────────────────

    private function leaveStats(): array
    {
        return [
            'pending'        => LeaveRequest::where('status', 'pending')->count(),
            'approved_month' => LeaveRequest::where('status', 'approved')
                ->whereYear('approved_at', now()->year)
                ->whereMonth('approved_at', now()->month)
                ->count(),
            'rejected_month' => LeaveRequest::where('status', 'rejected')
                ->whereYear('approved_at', now()->year)
                ->whereMonth('approved_at', now()->month)
                ->count(),
        ];
    }

    // ── Attendance ────────────────────────────────────────────────────────────

    private function attendanceStats(): array
    {
        $today = now()->toDateString();

        $presentToday = Attendance::where('work_date', $today)
            ->where('status', 'present')
            ->count();

        $totalHoursMonth = (float) Attendance::whereYear('work_date', now()->year)
            ->whereMonth('work_date', now()->month)
            ->sum('worked_hours');

        $activeCount = Employee::where('is_active', true)->count();

        return [
            'present_today'     => $presentToday,
            'absent_today'      => max(0, $activeCount - $presentToday),
            'total_hours_month' => round($totalHoursMonth, 1),
        ];
    }

    public function render(): View
    {
        // Attendance/Headcount/Leave charts moved to their own lazy-loaded HrChartsCard
        // component — attendanceTrend() is a 30-day raw groupBy that grows with the
        // attendance table, and none of the three need to block the rest of the dashboard.
        $headcount = $this->headcountStats();
        $leaveStats = $this->leaveStats();
        $attendanceStats = $this->attendanceStats();

        $recentLeaves = LeaveRequest::with(['employee', 'leaveType'])
            ->latest()
            ->limit(8)
            ->get();

        $recentJoiners = Employee::with('department')
            ->where('is_active', true)
            ->latest('joining_date')
            ->limit(5)
            ->get();

        $departments = Department::where('is_active', true)->count();
        $designations = Designation::count();

        return view('hr::livewire.dashboard', compact(
            'headcount',
            'leaveStats',
            'attendanceStats',
            'recentLeaves',
            'recentJoiners',
            'departments',
            'designations',
        ));
    }
}
