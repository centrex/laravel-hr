<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'employee_id'   => $this->employee_id,
            'leave_type_id' => $this->leave_type_id,
            'starts_at'     => $this->starts_at?->toDateString(),
            'ends_at'       => $this->ends_at?->toDateString(),
            'days'          => $this->days,
            'status'        => $this->status,
            'reason'        => $this->reason,
            'approved_by'   => $this->approved_by,
            'approved_at'   => $this->approved_at?->toISOString(),
        ];
    }
}
