<?php

declare(strict_types = 1);

namespace Centrex\Hr\Models;

use Centrex\Hr\Concerns\AddTablePrefix;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Department extends Model
{
    use AddTablePrefix;
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'description', 'manager_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(config('hr.drivers.database.connection', config('database.default')));
    }

    protected function getTableSuffix(): string
    {
        return 'departments';
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }
}
