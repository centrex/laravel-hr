<div>
    <x-tallui-notification />

    <x-tallui-page-header
        title="HR Dashboard"
        subtitle="Workforce overview — {{ now()->format('F Y') }}"
        icon="o-users"
    >
        <x-slot:breadcrumbs>
            <x-tallui-breadcrumb :links="[['label' => 'HR'], ['label' => 'Dashboard']]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-tallui-button
                label="Add Employee"
                icon="o-plus"
                :link="route('hr.entities.employees.create')"
                class="btn-primary btn-sm"
            />
            <x-tallui-button
                label="Employees"
                icon="o-table-cells"
                :link="route('hr.entities.employees.index')"
                class="btn-ghost btn-sm"
            />
        </x-slot:actions>
    </x-tallui-page-header>

    {{-- ── Headcount KPI row ──────────────────────────────────────────────────── --}}
    <div class="stats shadow w-full mb-6">
        <x-tallui-stat
            title="Total Employees"
            :value="$headcount['total']"
            icon="o-users"
            icon-color="text-primary"
            :desc="$headcount['active'] . ' active'"
        />
        <x-tallui-stat
            title="New This Month"
            :value="$headcount['newThisMonth']"
            icon="o-user-plus"
            icon-color="text-success"
            desc="New joiners"
        />
        <x-tallui-stat
            title="On Leave Today"
            :value="$headcount['onLeaveToday']"
            icon="o-calendar-days"
            icon-color="text-warning"
            desc="Approved leave"
        />
        <x-tallui-stat
            title="Departments"
            :value="$departments"
            icon="o-building-office"
            icon-color="text-secondary"
            :desc="$designations . ' designations'"
        />
    </div>

    {{-- ── Attendance + Leave KPI row ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-tallui-card title="Attendance Today" icon="o-clock" :shadow="true" padding="normal">
            <div class="flex items-center justify-between mt-2">
                <div class="text-center">
                    <div class="text-3xl font-bold text-success">{{ $attendanceStats['present_today'] }}</div>
                    <div class="text-xs text-base-content/50 mt-1">Present</div>
                </div>
                <div class="divider divider-horizontal"></div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-error">{{ $attendanceStats['absent_today'] }}</div>
                    <div class="text-xs text-base-content/50 mt-1">Absent</div>
                </div>
                <div class="divider divider-horizontal"></div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-info">{{ number_format($attendanceStats['total_hours_month'], 0) }}</div>
                    <div class="text-xs text-base-content/50 mt-1">Hours (month)</div>
                </div>
            </div>
        </x-tallui-card>

        <x-tallui-card title="Leave Requests" icon="o-inbox-stack" :shadow="true" padding="normal">
            <div class="flex items-center justify-between mt-2">
                <div class="text-center">
                    <div class="text-3xl font-bold text-warning">{{ $leaveStats['pending'] }}</div>
                    <div class="text-xs text-base-content/50 mt-1">Pending</div>
                </div>
                <div class="divider divider-horizontal"></div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-success">{{ $leaveStats['approved_month'] }}</div>
                    <div class="text-xs text-base-content/50 mt-1">Approved</div>
                </div>
                <div class="divider divider-horizontal"></div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-error">{{ $leaveStats['rejected_month'] }}</div>
                    <div class="text-xs text-base-content/50 mt-1">Rejected</div>
                </div>
            </div>
            @if ($leaveStats['pending'] > 0)
                <div class="mt-3">
                    {{-- <x-tallui-button
                        label="Review {{ $leaveStats['pending'] }} pending"
                        :link="route('hr.entities.leave_requests.index')"
                        class="btn-warning btn-sm btn-outline w-full"
                    /> --}}
                </div>
            @endif
        </x-tallui-card>

        <x-tallui-card title="Quick Actions" icon="o-bolt" :shadow="true" padding="normal">
            <div class="flex flex-col gap-2 mt-2">
                {{-- <x-tallui-button label="Add Attendance" icon="o-clock" :link="route('hr.entities.attendances.create')" class="btn-outline btn-sm w-full" /> --}}
                {{-- <x-tallui-button label="New Leave Request" icon="o-calendar-days" :link="route('hr.entities.leave_requests.create')" class="btn-outline btn-sm w-full" /> --}}
                <x-tallui-button label="Departments" icon="o-building-office" :link="route('hr.entities.departments.index')" class="btn-outline btn-sm w-full" />
            </div>
        </x-tallui-card>
    </div>

    {{-- ── Charts row ──────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        {{-- Attendance trend --}}
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

        {{-- Headcount by department --}}
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
    </div>

    {{-- Leave distribution chart --}}
    @if (array_sum($leaveChart['series']) > 0)
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <x-tallui-card title="Leave Status — {{ now()->year }}" icon="o-chart-pie" :shadow="true">
            <livewire:tallui-pie-chart
                :series="$leaveChart['series']"
                :labels="$leaveChart['categories']"
                :height="220"
            />
        </x-tallui-card>

        {{-- Recent joiners --}}
        <x-tallui-card title="Recent Joiners" icon="o-user-plus" :shadow="true" class="lg:col-span-2">
            @forelse ($recentJoiners as $emp)
                <x-tallui-list-item
                    :title="$emp->name"
                    :subtitle="$emp->department?->name ?? 'No Department'"
                    :value="$emp->joining_date?->format('d M Y')"
                    icon="o-user-circle"
                    icon-color="text-primary"
                    :separator="!$loop->last"
                    :compact="true"
                >
                    <x-slot:actions>
                        <x-tallui-badge :type="$emp->is_active ? 'success' : 'neutral'" size="sm">
                            {{ $emp->is_active ? 'Active' : 'Inactive' }}
                        </x-tallui-badge>
                    </x-slot:actions>
                </x-tallui-list-item>
            @empty
                <x-tallui-empty-state title="No recent joiners" icon="o-user-plus" size="sm" />
            @endforelse
        </x-tallui-card>
    </div>
    @endif

    {{-- ── Recent Leave Requests ──────────────────────────────────────────────── --}}
    <x-tallui-card title="Recent Leave Requests" icon="o-inbox-stack" :shadow="true" padding="none">
        <x-slot:actions>
            {{-- <x-tallui-button
                label="View All"
                :link="route('hr.entities.leave_requests.index')"
                class="btn-ghost btn-sm"
            /> --}}
        </x-slot:actions>

        @forelse ($recentLeaves as $leave)
            @php
                $statusType = match ($leave->status) {
                    'approved'  => 'success',
                    'rejected'  => 'error',
                    'cancelled' => 'neutral',
                    default     => 'warning',
                };
            @endphp
            <x-tallui-list-item
                :title="$leave->employee?->name ?? '—'"
                :subtitle="($leave->leaveType?->name ?? 'Leave') . ' · ' . $leave->starts_at?->format('d M') . ' – ' . $leave->ends_at?->format('d M Y')"
                :value="$leave->days . ' day' . ($leave->days != 1 ? 's' : '')"
                icon="o-calendar-days"
                icon-color="text-secondary"
                :separator="!$loop->last"
                :compact="true"
            >
                <x-slot:actions>
                    <x-tallui-badge :type="$statusType" size="sm">
                        {{ ucfirst($leave->status) }}
                    </x-tallui-badge>
                </x-slot:actions>
            </x-tallui-list-item>
        @empty
            <div class="p-6">
                <x-tallui-empty-state
                    title="No leave requests"
                    description="Leave requests submitted by employees will appear here."
                    icon="o-calendar-days"
                    size="sm"
                />
            </div>
        @endforelse
    </x-tallui-card>
</div>
