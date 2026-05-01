<?php

declare(strict_types = 1);

use Centrex\Hr\Http\Controllers\Api\EntityCrudController;
use Centrex\Hr\Support\HrEntityRegistry;
use Illuminate\Support\Facades\Route;

Route::middleware(config('hr.api_middleware', ['api', 'auth:sanctum']))
    ->prefix(config('hr.api_prefix', 'api/hr'))
    ->as('hr.api.')
    ->group(function (): void {
        foreach (HrEntityRegistry::masterDataEntities() as $entity) {
            Route::get("/{$entity}", [EntityCrudController::class, 'index'])->defaults('entity', $entity)->name("{$entity}.index");
            Route::post("/{$entity}", [EntityCrudController::class, 'store'])->defaults('entity', $entity)->name("{$entity}.store");
            Route::get("/{$entity}/{recordId}", [EntityCrudController::class, 'show'])->defaults('entity', $entity)->name("{$entity}.show");
            Route::match(['put', 'patch'], "/{$entity}/{recordId}", [EntityCrudController::class, 'update'])->defaults('entity', $entity)->name("{$entity}.update");
            Route::delete("/{$entity}/{recordId}", [EntityCrudController::class, 'destroy'])->defaults('entity', $entity)->name("{$entity}.destroy");
        }
    });
