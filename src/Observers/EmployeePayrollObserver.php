<?php

declare(strict_types = 1);

namespace Centrex\Hr\Observers;

use Centrex\Hr\Models\Employee;
use Centrex\Hr\Support\PayrollSync;

class EmployeePayrollObserver
{
    public function saved(Employee $employee): void
    {
        app(PayrollSync::class)->syncEmployee($employee);
    }
}
