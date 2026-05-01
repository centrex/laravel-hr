<?php

declare(strict_types = 1);

namespace Centrex\Hr\Models;

use Centrex\Hr\Concerns\AddTablePrefix;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use AddTablePrefix;
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'annual_allowance', 'is_paid', 'requires_approval', 'is_active'];

    protected $casts = [
        'annual_allowance'  => 'integer',
        'is_paid'           => 'boolean',
        'requires_approval' => 'boolean',
        'is_active'         => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(config('hr.drivers.database.connection', config('database.default')));
    }

    protected function getTableSuffix(): string
    {
        return 'leave_types';
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
