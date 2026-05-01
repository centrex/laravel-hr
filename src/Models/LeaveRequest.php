<?php

declare(strict_types = 1);

namespace Centrex\Hr\Models;

use Centrex\Hr\Concerns\AddTablePrefix;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use AddTablePrefix;
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'leave_type_id', 'starts_at', 'ends_at', 'days',
        'status', 'reason', 'approved_by', 'approved_at', 'approval_notes',
    ];

    protected $casts = [
        'starts_at'   => 'date',
        'ends_at'     => 'date',
        'days'        => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(config('hr.drivers.database.connection', config('database.default')));
    }

    protected function getTableSuffix(): string
    {
        return 'leave_requests';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
