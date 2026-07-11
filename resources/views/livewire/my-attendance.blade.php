<div>
    <x-tallui-notification />

    <x-tallui-page-header
        title="My Attendance"
        subtitle="{{ $employee->name }} — {{ now()->format('F Y') }}"
        icon="o-finger-print"
    >
        <x-slot:breadcrumbs>
            <x-tallui-breadcrumb :links="[['label' => 'HR'], ['label' => 'My Attendance']]" />
        </x-slot:breadcrumbs>
    </x-tallui-page-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <x-tallui-card title="Today" class="md:col-span-1">
            <div class="flex flex-col gap-3">
                <div class="text-sm text-base-content/60">{{ now()->format('l, d F Y') }}</div>

                @if ($today?->check_in)
                    <div class="text-sm">Checked in: <strong>{{ \Illuminate\Support\Carbon::parse($today->check_in)->format('h:i A') }}</strong></div>
                @endif
                @if ($today?->check_out)
                    <div class="text-sm">Checked out: <strong>{{ \Illuminate\Support\Carbon::parse($today->check_out)->format('h:i A') }}</strong></div>
                @endif

                @if (!$today?->check_in)
                    <x-tallui-button wire:click="checkIn" label="Check In" icon="o-arrow-right-on-rectangle" class="btn-primary" />
                @elseif (!$today?->check_out)
                    <x-tallui-button wire:click="checkOut" label="Check Out" icon="o-arrow-left-on-rectangle" class="btn-warning" />
                @else
                    <x-tallui-badge type="success">Day complete</x-tallui-badge>
                @endif
            </div>
        </x-tallui-card>

        <x-tallui-card title="This Month" class="md:col-span-2">
            <div class="stats shadow w-full">
                <x-tallui-stat title="Present" :value="$summary['present']" icon="o-check-circle" icon-color="text-success" />
                <x-tallui-stat title="Absent" :value="$summary['absent']" icon="o-x-circle" icon-color="text-error" />
                <x-tallui-stat title="On Leave" :value="$summary['on_leave']" icon="o-calendar-days" icon-color="text-warning" />
                <x-tallui-stat title="Weekend" :value="$summary['weekend']" icon="o-moon" icon-color="text-base-content/50" />
            </div>
        </x-tallui-card>
    </div>

    <x-tallui-card title="Recent History">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr class="bg-base-300 text-xs text-base-content/60 uppercase tracking-wide border-b border-base-300">
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Worked Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $row)
                        <tr class="even:bg-base-200/50 hover:bg-base-200">
                            <td>{{ \Illuminate\Support\Carbon::parse($row->work_date)->format('d M Y') }}</td>
                            <td>{{ $row->check_in ? \Illuminate\Support\Carbon::parse($row->check_in)->format('h:i A') : '—' }}</td>
                            <td>{{ $row->check_out ? \Illuminate\Support\Carbon::parse($row->check_out)->format('h:i A') : '—' }}</td>
                            <td>{{ $row->worked_hours ?? '—' }}</td>
                            <td>
                                <x-tallui-badge type="{{ $row->status === 'present' ? 'success' : ($row->status === 'late' ? 'warning' : 'ghost') }}">
                                    {{ ucfirst($row->status) }}
                                </x-tallui-badge>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-tallui-empty-state title="No attendance recorded yet" icon="o-finger-print" size="sm" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-tallui-card>
</div>
