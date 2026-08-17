<div>
    <x-tallui-notification />

    <x-tallui-page-header title="Attendance Report" subtitle="Per-employee attendance summary for a date range" icon="o-chart-bar">
        <x-slot:breadcrumbs>
            <x-tallui-breadcrumb :links="[['label' => 'HR'], ['label' => 'Attendance'], ['label' => 'Report']]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-tallui-button wire:click="exportCsv" label="Export CSV" icon="o-arrow-down-tray" class="btn-primary btn-sm" />
        </x-slot:actions>
    </x-tallui-page-header>

    <x-tallui-card class="mb-6">
        <div class="flex flex-wrap gap-4 items-end">
            <div class="form-control">
                <label class="label"><span class="label-text">Department</span></label>
                <select wire:model.live="departmentFilter" class="select select-bordered select-sm">
                    <option value="">All Departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">Employee</span></label>
                <select wire:model.live="employeeFilter" class="select select-bordered select-sm">
                    <option value="">All Employees</option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">From</span></label>
                <input type="date" wire:model.live="fromDate" class="input input-bordered input-sm" />
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">To</span></label>
                <input type="date" wire:model.live="toDate" class="input input-bordered input-sm" />
            </div>
        </div>
    </x-tallui-card>

    <div class="stats shadow w-full mb-6">
        <x-tallui-stat title="Present" value="{{ $totals['present'] }}" icon="o-check-circle" icon-color="text-success" />
        <x-tallui-stat title="Absent" value="{{ $totals['absent'] }}" icon="o-x-circle" icon-color="text-error" />
        <x-tallui-stat title="On Leave" value="{{ $totals['on_leave'] }}" icon="o-calendar-days" icon-color="text-warning" />
        <x-tallui-stat title="Attendance Rate" value="{{ $totals['attendance_rate'] }}%" icon="o-chart-pie" />
        <x-tallui-stat title="Worked Hours" value="{{ $totals['worked_hours'] }}" icon="o-clock" />
    </div>

    <x-tallui-card>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr class="bg-base-300 text-xs text-base-content/60 uppercase tracking-wide border-b border-base-300">
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>On Leave</th>
                        <th>Weekend</th>
                        <th>Working Days</th>
                        <th>Attendance %</th>
                        <th>Worked Hours</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="even:bg-base-200/50 hover:bg-base-200">
                            <td>{{ $row['employee']->name }}</td>
                            <td>{{ $row['employee']->department?->name ?? '—' }}</td>
                            <td>{{ $row['present'] }}</td>
                            <td>{{ $row['absent'] }}</td>
                            <td>{{ $row['on_leave'] }}</td>
                            <td>{{ $row['weekend'] }}</td>
                            <td>{{ $row['working_days'] }}</td>
                            <td>
                                <x-tallui-badge type="{{ $row['attendance_rate'] >= 90 ? 'success' : ($row['attendance_rate'] >= 75 ? 'warning' : 'error') }}">
                                    {{ $row['attendance_rate'] }}%
                                </x-tallui-badge>
                            </td>
                            <td>{{ $row['worked_hours'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-tallui-empty-state title="No employees match this filter" icon="o-chart-bar" size="sm" /></td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="font-semibold border-t border-base-300">
                            <td colspan="2">Total</td>
                            <td>{{ $totals['present'] }}</td>
                            <td>{{ $totals['absent'] }}</td>
                            <td>{{ $totals['on_leave'] }}</td>
                            <td>{{ $totals['weekend'] }}</td>
                            <td>{{ $totals['working_days'] }}</td>
                            <td>{{ $totals['attendance_rate'] }}%</td>
                            <td>{{ $totals['worked_hours'] }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-tallui-card>
</div>
