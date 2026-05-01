<?php

declare(strict_types = 1);

namespace Centrex\Hr\Models;

use Centrex\Hr\Concerns\AddTablePrefix;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Designation extends Model
{
    use AddTablePrefix;
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'department_id', 'description', 'salary_min', 'salary_max', 'is_active'];

    protected $casts = [
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(config('hr.drivers.database.connection', config('database.default')));
    }

    protected function getTableSuffix(): string
    {
        return 'designations';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
