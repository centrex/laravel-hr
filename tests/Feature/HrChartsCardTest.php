<?php

declare(strict_types = 1);

use Centrex\Hr\Http\Livewire\HrChartsCard;
use Centrex\Hr\Models\{Department, Employee};
use Illuminate\Support\Facades\{Artisan, DB};

beforeEach(function (): void {
    Artisan::call('migrate', ['--database' => 'testing']);
    config()->set('cache.default', 'array');
});

it('builds the three chart datasets and caches the result', function (): void {
    $department = Department::query()->create(['code' => 'OPS', 'name' => 'Operations']);

    Employee::query()->create([
        'code'            => 'E-1001', 'name' => 'Amina Rahman', 'department_id' => $department->getKey(),
        'employment_type' => 'full_time', 'status' => 'active', 'is_active' => true,
    ]);

    $component = new HrChartsCard();

    $charts = $component->charts();

    expect($charts)->toHaveKeys(['deptChart', 'attendanceChart', 'leaveChart'])
        ->and($charts['deptChart']['categories'])->toContain('Operations');

    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    $component->charts();

    expect($queryCount)->toBe(0);
});

it('placeholder renders a skeleton', function (): void {
    $component = new HrChartsCard();

    expect($component->placeholder())->toBeString()->toContain('role="status"');
});
