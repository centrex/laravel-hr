<?php

declare(strict_types = 1);

namespace Centrex\Hr\Concerns;

trait AddTablePrefix
{
    public function getTable(): string
    {
        return config('hr.table_prefix', 'hr_') . $this->getTableSuffix();
    }

    abstract protected function getTableSuffix(): string;
}
