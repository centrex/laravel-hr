<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Livewire;

use Centrex\Hr\Models\{Attendance, Department, Designation, Employee, LeaveRequest};
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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

    // ── Charts ────────────────────────────────────────────────────────────────

    private function headcountByDepartment(): array
    {
        $rows = Department::withCount(['employees' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->orderByDesc('employees_count')
            ->limit(10)
            ->get();

        return [
            'series'     => [['name' => 'Employees', 'data' => $rows->pluck('employees_count')->toArray()]],
            'categories' => $rows->pluck('name')->toArray(),
        ];
    }

    private function attendanceTrend(): array
    {
        $prefix = config('hr.table_prefix', 'hr_');
        $connection = config('hr.drivers.database.connection', config('database.default'));
        $days = 30;
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = DB::connection($connection)
            ->table("{$prefix}attendances")
            ->where('work_date', '>=', $from->toDateString())
            ->where('status', 'present')
            ->selectRaw('DATE(work_date) as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $categories = [];
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $categories[] = now()->subDays($i)->format('d M');
            $data[] = (int) ($rows->get($date)?->cnt ?? 0);
        }

        return [
            'series'     => [['name' => 'Present', 'data' => $data]],
            'categories' => $categories,
        ];
    }

    private function leaveStatusDistribution(): array
    {
        $rows = LeaveRequest::whereYear('starts_at', now()->year)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $statuses = ['pending', 'approved', 'rejected', 'cancelled'];
        $counts = $rows->toArray();

        return [
            'series'     => array_map(fn (string $s): int => (int) ($counts[$s] ?? 0), $statuses),
            'categories' => array_map('ucfirst', $statuses),
        ];
    }

    public function render(): View
    {
        $headcount = $this->headcountStats();
        $leaveStats = $this->leaveStats();
        $attendanceStats = $this->attendanceStats();

        $deptChart = $this->headcountByDepartment();
        $attendanceChart = $this->attendanceTrend();
        $leaveChart = $this->leaveStatusDistribution();

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
            'deptChart',
            'attendanceChart',
            'leaveChart',
            'recentLeaves',
            'recentJoiners',
            'departments',
            'designations',
        ));
    }
}
