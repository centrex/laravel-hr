<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'department_id'   => $this->department_id,
            'designation_id'  => $this->designation_id,
            'manager_id'      => $this->manager_id,
            'employment_type' => $this->employment_type,
            'status'          => $this->status,
            'joining_date'    => $this->joining_date?->toDateString(),
            'monthly_salary'  => $this->monthly_salary,
            'currency'        => $this->currency,
            'is_active'       => $this->is_active,
        ];
    }
}
