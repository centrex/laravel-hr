<?php

declare(strict_types = 1);

namespace Centrex\Hr\Contracts;

interface ZktecoClient
{
    public function connect(): bool;

    public function disconnect(): void;

    /**
     * @return array<int, array{user_id: string, timestamp: \DateTimeInterface, state: int}>
     */
    public function getAttendanceLogs(): array;
}
