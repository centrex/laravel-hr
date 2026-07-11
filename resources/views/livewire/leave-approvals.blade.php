<div>
    <x-tallui-notification />

    <x-tallui-page-header
        title="Leave Approvals"
        subtitle="{{ $isAdmin ? 'All pending leave requests' : 'Pending requests from your direct reports' }}"
        icon="o-clipboard-document-check"
    >
        <x-slot:breadcrumbs>
            <x-tallui-breadcrumb :links="[['label' => 'HR'], ['label' => 'Leave Approvals']]" />
        </x-slot:breadcrumbs>
    </x-tallui-page-header>

    <x-tallui-card>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr class="bg-base-300 text-xs text-base-content/60 uppercase tracking-wide border-b border-base-300">
                        <th>Employee</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $request)
                        <tr class="even:bg-base-200/50 hover:bg-base-200">
                            <td>{{ $request->employee?->name }}</td>
                            <td>{{ $request->leaveType?->name }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($request->starts_at)->format('d M Y') }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($request->ends_at)->format('d M Y') }}</td>
                            <td>{{ $request->days }}</td>
                            <td class="max-w-xs truncate">{{ $request->reason }}</td>
                            <td class="flex gap-2">
                                <x-tallui-button wire:click="approve({{ $request->id }})" label="Approve" class="btn-success btn-xs" />
                                <x-tallui-button wire:click="openReject({{ $request->id }})" label="Reject" class="btn-error btn-xs" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-tallui-empty-state title="No pending leave requests" icon="o-check-circle" size="sm" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-tallui-card>

    @if ($showRejectModal)
        <div class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-4">Reject Leave Request</h3>
                <div class="form-control mb-3">
                    <label class="label"><span class="label-text">Notes (optional)</span></label>
                    <textarea wire:model="rejectNotes" class="textarea textarea-bordered"></textarea>
                </div>
                <div class="modal-action">
                    <x-tallui-button wire:click="$set('showRejectModal', false)" label="Cancel" class="btn-ghost" />
                    <x-tallui-button wire:click="reject" label="Reject" class="btn-error" />
                </div>
            </div>
        </div>
    @endif
</div>
