<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Livewire;

use Centrex\Hr\Models\{Department, LeaveRequest};
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\{Blade, Cache, DB};
use Livewire\Component;

/**
 * Attendance/Headcount/Leave charts, split out of HrDashboard. Individually these are cheap
 * grouped-aggregate queries except attendanceTrend() (a 30-day raw groupBy that grows with
 * the attendance table) — bundled into one lazy component rather than three, so the two
 * cheap ones don't cost their own separate round-trip.
 *
 * Caches via the plain Cache facade rather than tallui's CachesData trait — unlike
 * laravel-accounting/laravel-inventory/laravel-payroll, this package doesn't declare
 * centrex/tallui as a composer dependency (its blade views only reference <x-tallui-*>
 * tags, resolved by whichever host app installs it), so a hard PHP-level import of a
 * tallui class would break this package when used standalone.
 */
class HrChartsCard extends Component
{
    private const CACHE_TTL_SECONDS = 300;

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

    /** @return array{deptChart: array, attendanceChart: array, leaveChart: array} */
    public function charts(): array
    {
        return Cache::remember(
            'hr:charts-card',
            self::CACHE_TTL_SECONDS,
            fn (): array => [
                'deptChart'       => $this->headcountByDepartment(),
                'attendanceChart' => $this->attendanceTrend(),
                'leaveChart'      => $this->leaveStatusDistribution(),
            ],
        );
    }

    public function placeholder(): string
    {
        return Blade::render(<<<'BLADE'
            <div role="status" aria-label="Loading charts" class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6 animate-pulse">
                @for ($i = 0; $i < 3; $i++)
                    <div class="h-64 rounded-2xl border border-base-200 bg-base-100"></div>
                @endfor
            </div>
            BLADE);
    }

    public function render(): View
    {
        return view('hr::livewire.hr-charts-card', $this->charts());
    }
}
