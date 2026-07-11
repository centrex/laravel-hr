<div>
    <x-tallui-notification />

    <x-tallui-page-header title="My Leave" subtitle="{{ $employee->name }}" icon="o-calendar-days">
        <x-slot:breadcrumbs>
            <x-tallui-breadcrumb :links="[['label' => 'HR'], ['label' => 'My Leave']]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-tallui-button wire:click="openCreate" label="Request Leave" icon="o-plus" class="btn-primary btn-sm" />
        </x-slot:actions>
    </x-tallui-page-header>

    <x-tallui-card title="Leave Balances" class="mb-6">
        <div class="stats shadow w-full">
            @foreach ($balances as $balance)
                <x-tallui-stat
                    title="{{ $balance['leave_type']->name }}"
                    value="{{ $balance['remaining'] }}"
                    icon="o-calendar"
                    icon-color="text-primary"
                    desc="{{ $balance['used'] }} used of {{ $balance['allowance'] }}"
                />
            @endforeach
        </div>
    </x-tallui-card>

    <x-tallui-card title="My Requests">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr class="bg-base-300 text-xs text-base-content/60 uppercase tracking-wide border-b border-base-300">
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $request)
                        <tr class="even:bg-base-200/50 hover:bg-base-200">
                            <td>{{ $request->leaveType?->name }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($request->starts_at)->format('d M Y') }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($request->ends_at)->format('d M Y') }}</td>
                            <td>{{ $request->days }}</td>
                            <td>
                                <x-tallui-badge type="{{ match($request->status) {
                                    'approved' => 'success',
                                    'rejected' => 'error',
                                    'cancelled' => 'ghost',
                                    default => 'warning',
                                } }}">
                                    {{ ucfirst($request->status) }}
                                </x-tallui-badge>
                            </td>
                            <td>
                                @if (in_array($request->status, ['pending', 'approved']))
                                    <x-tallui-button
                                        wire:click="cancel({{ $request->id }})"
                                        wire:confirm="Cancel this leave request?"
                                        label="Cancel"
                                        class="btn-ghost btn-xs"
                                    />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-tallui-empty-state title="No leave requests yet" icon="o-calendar-days" size="sm" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-tallui-card>

    @if ($showModal)
        <div class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-4">Request Leave</h3>

                <div class="form-control mb-3">
                    <label class="label"><span class="label-text">Leave Type</span></label>
                    <select wire:model="leaveTypeId" class="select select-bordered">
                        <option value="">Select type</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <x-tallui-error-message field="leaveTypeId" />
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-control">
                        <label class="label"><span class="label-text">From</span></label>
                        <input type="date" wire:model="startsAt" class="input input-bordered" />
                        <x-tallui-error-message field="startsAt" />
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">To</span></label>
                        <input type="date" wire:model="endsAt" class="input input-bordered" />
                        <x-tallui-error-message field="endsAt" />
                    </div>
                </div>

                <div class="form-control mb-3">
                    <label class="label"><span class="label-text">Reason</span></label>
                    <textarea wire:model="reason" class="textarea textarea-bordered"></textarea>
                </div>

                <div class="modal-action">
                    <x-tallui-button wire:click="$set('showModal', false)" label="Cancel" class="btn-ghost" />
                    <x-tallui-button wire:click="submitRequest" label="Submit" class="btn-primary" />
                </div>
            </div>
        </div>
    @endif
</div>
