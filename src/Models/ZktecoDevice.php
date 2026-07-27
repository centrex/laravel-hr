<?php

declare(strict_types = 1);

namespace Centrex\Hr\Models;

use Centrex\Hr\Concerns\AddTablePrefix;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZktecoDevice extends Model
{
    use AddTablePrefix;
    use SoftDeletes;

    protected $fillable = ['name', 'ip_address', 'port', 'comm_key', 'sbu_code', 'is_active'];

    protected $casts = [
        'port'           => 'integer',
        'comm_key'       => 'integer',
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(config('hr.drivers.database.connection', config('database.default')));
    }

    protected function getTableSuffix(): string
    {
        return 'zkteco_devices';
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
