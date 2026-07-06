<div>
    <x-tallui-notification />

    <x-tallui-page-header title="Attendance" subtitle="Manage attendance records for any employee" icon="o-finger-print">
        <x-slot:breadcrumbs>
            <x-tallui-breadcrumb :links="[['label' => 'HR'], ['label' => 'Attendance']]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-tallui-button wire:click="openCreate" label="Record Attendance" icon="o-plus" class="btn-primary btn-sm" />
        </x-slot:actions>
    </x-tallui-page-header>

    <x-tallui-card class="mb-6">
        <div class="flex flex-wrap gap-4 items-end">
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

    <x-tallui-card>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Worked Hours</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $row)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->work_date)->format('d M Y') }}</td>
                            <td>{{ $row->employee?->name }}</td>
                            <td>{{ $row->check_in ? \Illuminate\Support\Carbon::parse($row->check_in)->format('h:i A') : '—' }}</td>
                            <td>{{ $row->check_out ? \Illuminate\Support\Carbon::parse($row->check_out)->format('h:i A') : '—' }}</td>
                            <td>{{ $row->worked_hours ?? '—' }}</td>
                            <td>
                                <x-tallui-badge type="{{ $row->status === 'present' ? 'success' : ($row->status === 'absent' ? 'error' : 'warning') }}">
                                    {{ ucfirst(str_replace('_', ' ', $row->status)) }}
                                </x-tallui-badge>
                            </td>
                            <td>
                                <x-tallui-button wire:click="openEdit({{ $row->id }})" icon="o-pencil" class="btn-ghost btn-xs" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-tallui-empty-state title="No attendance records in this range" icon="o-finger-print" size="sm" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $records->links() }}</div>
    </x-tallui-card>

    @if ($showModal)
        <div class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-4">Record Attendance</h3>

                <div class="form-control mb-3">
                    <label class="label"><span class="label-text">Employee</span></label>
                    <select wire:model="employeeId" class="select select-bordered">
                        <option value="">Select employee</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                    <x-tallui-error-message field="employeeId" />
                </div>

                <div class="form-control mb-3">
                    <label class="label"><span class="label-text">Work Date</span></label>
                    <input type="date" wire:model="workDate" class="input input-bordered" />
                    <x-tallui-error-message field="workDate" />
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Check In</span></label>
                        <input type="time" wire:model="checkIn" class="input input-bordered" />
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Check Out</span></label>
                        <input type="time" wire:model="checkOut" class="input input-bordered" />
                    </div>
                </div>

                <div class="form-control mb-3">
                    <label class="label"><span class="label-text">Status</span></label>
                    <select wire:model="status" class="select select-bordered">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                        <option value="on_leave">On Leave</option>
                        <option value="half_day">Half Day</option>
                    </select>
                </div>

                <div class="form-control mb-3">
                    <label class="label"><span class="label-text">Notes</span></label>
                    <textarea wire:model="notes" class="textarea textarea-bordered"></textarea>
                </div>

                <div class="modal-action">
                    <x-tallui-button wire:click="$set('showModal', false)" label="Cancel" class="btn-ghost" />
                    <x-tallui-button wire:click="save" label="Save" class="btn-primary" />
                </div>
            </div>
        </div>
    @endif
</div>
