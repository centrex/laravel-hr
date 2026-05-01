<?php

declare(strict_types = 1);

namespace Centrex\Hr\Models;

use Centrex\Hr\Concerns\AddTablePrefix;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class Employee extends Model
{
    use AddTablePrefix;
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'email', 'phone', 'address', 'city', 'country',
        'department_id', 'designation_id', 'manager_id', 'employment_type',
        'status', 'joining_date', 'termination_date', 'monthly_salary',
        'currency', 'bank_account_name', 'bank_account_number', 'tax_id',
        'emergency_contact_name', 'emergency_contact_phone',
        'payroll_profile_type', 'payroll_profile_id', 'is_active',
    ];

    protected $casts = [
        'joining_date'     => 'date',
        'termination_date' => 'date',
        'monthly_salary'   => 'decimal:2',
        'is_active'        => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(config('hr.drivers.database.connection', config('database.default')));
    }

    protected function getTableSuffix(): string
    {
        return 'employees';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payrollProfile(): MorphTo
    {
        return $this->morphTo();
    }
}
