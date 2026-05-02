<?php

declare(strict_types = 1);

use Centrex\Hr\Http\Livewire\Entities\{EntityFormPage, EntityIndexPage};
use Centrex\Hr\Http\Livewire\HrDashboard;
use Centrex\Hr\Support\HrEntityRegistry;
use Illuminate\Support\Facades\Route;

Route::middleware(config('hr.web_middleware', ['web', 'auth']))
    ->prefix(config('hr.web_prefix', 'hr'))
    ->as('hr.')
    ->group(function (): void {
        Route::get('/dashboard', HrDashboard::class)->name('dashboard');

        foreach (HrEntityRegistry::masterDataEntities() as $entity) {
            Route::get("/{$entity}", EntityIndexPage::class)->name("entities.{$entity}.index")->defaults('entity', $entity);
            Route::get("/{$entity}/create", EntityFormPage::class)->name("entities.{$entity}.create")->defaults('entity', $entity);
            Route::get("/{$entity}/{recordId}/edit", EntityFormPage::class)->name("entities.{$entity}.edit")->defaults('entity', $entity);
        }
    });
