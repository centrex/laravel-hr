<?php

declare(strict_types = 1);

namespace Centrex\Hr\Support;

use Centrex\Hr\Models\Employee;

/**
 * Mirrors an HR employee into laravel-payroll's own employee record, keeping the two
 * packages independently usable: this only runs when payroll_sync is enabled AND the
 * payroll package is actually installed, so HR works standalone otherwise.
 *
 * Linked via the existing polymorphic pair on both sides — Employee::payroll_profile_*
 * (this side) and Payroll\Models\Employee::modelable_* (the target side) — the same
 * pattern ErpIntegration uses to link inventory customers/suppliers to accounting.
 */
class PayrollSync
{
    public function enabled(): bool
    {
        return (bool) config('hr.payroll_sync.enabled', false)
            && class_exists(\Centrex\Payroll\Models\Employee::class);
    }

    public function syncEmployee(?Employee $employee): ?int
    {
        if (!$employee || !$this->enabled()) {
            return null;
        }

        $payrollEmployeeClass = \Centrex\Payroll\Models\Employee::class;

        $payrollEmployee = $employee->payroll_profile_type === $payrollEmployeeClass && $employee->payroll_profile_id
            ? $payrollEmployeeClass::find($employee->payroll_profile_id)
            : $payrollEmployeeClass::where('modelable_type', Employee::class)
                ->where('modelable_id', $employee->id)
                ->first();

        $payload = [
            'code'                    => $employee->code,
            'name'                    => $employee->name,
            'email'                   => $employee->email,
            'phone'                   => $employee->phone,
            'address'                 => $employee->address,
            'city'                    => $employee->city,
            'country'                 => $employee->country,
            'department'              => $employee->department?->name,
            'designation'             => $employee->designation?->name,
            'employment_type'         => $employee->employment_type,
            'joining_date'            => $employee->joining_date,
            'monthly_salary'          => $employee->monthly_salary,
            'currency'                => $employee->currency,
            'bank_account_name'       => $employee->bank_account_name,
            'bank_account_number'     => $employee->bank_account_number,
            'tax_id'                  => $employee->tax_id,
            'emergency_contact_name'  => $employee->emergency_contact_name,
            'emergency_contact_phone' => $employee->emergency_contact_phone,
            'is_active'               => (bool) $employee->is_active,
            'modelable_type'          => Employee::class,
            'modelable_id'            => $employee->id,
        ];

        if ($payrollEmployee) {
            $payrollEmployee->fill($payload)->save();
        } else {
            $payrollEmployee = $payrollEmployeeClass::create($payload);
        }

        if ($employee->payroll_profile_type !== $payrollEmployeeClass || (int) $employee->payroll_profile_id !== (int) $payrollEmployee->id) {
            $employee->forceFill([
                'payroll_profile_type' => $payrollEmployeeClass,
                'payroll_profile_id'   => $payrollEmployee->id,
            ])->saveQuietly();
        }

        return (int) $payrollEmployee->id;
    }
}
