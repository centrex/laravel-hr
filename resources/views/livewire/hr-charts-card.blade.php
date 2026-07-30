<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <x-tallui-card title="Attendance — Last 30 Days" icon="o-chart-bar" :shadow="true">
        @if (!empty($attendanceChart['series'][0]['data']) && array_sum($attendanceChart['series'][0]['data']) > 0)
            <livewire:tallui-area-chart
                :series="$attendanceChart['series']"
                :categories="$attendanceChart['categories']"
                :height="220"
            />
        @else
            <x-tallui-empty-state
                title="No attendance data"
                description="Attendance records will appear here once logged."
                icon="o-clock"
                size="sm"
            />
        @endif
    </x-tallui-card>

    <x-tallui-card title="Headcount by Department" icon="o-building-office" :shadow="true">
        @if (!empty($deptChart['categories']))
            <livewire:tallui-bar-chart
                :series="$deptChart['series']"
                :categories="$deptChart['categories']"
                :height="220"
            />
        @else
            <x-tallui-empty-state
                title="No departments"
                description="Add departments and employees to see headcount distribution."
                icon="o-building-office"
                size="sm"
            />
        @endif
    </x-tallui-card>

    <x-tallui-card title="Leave Status — {{ now()->year }}" icon="o-chart-pie" :shadow="true">
        @if (array_sum($leaveChart['series']) > 0)
            <livewire:tallui-pie-chart
                :series="$leaveChart['series']"
                :labels="$leaveChart['categories']"
                :height="220"
            />
        @else
            <x-tallui-empty-state
                title="No leave data"
                description="Leave requests will appear here once submitted."
                icon="o-inbox-stack"
                size="sm"
            />
        @endif
    </x-tallui-card>
</div>
