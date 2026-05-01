<?php

declare(strict_types = 1);

use Centrex\Hr\Http\Livewire\Entities\{EntityFormPage, EntityIndexPage};
use Centrex\Hr\Support\HrEntityRegistry;
use Illuminate\Support\Facades\Route;

Route::middleware(config('hr.web_middleware', ['web', 'auth']))
    ->prefix(config('hr.web_prefix', 'hr'))
    ->as('hr.')
    ->group(function (): void {
        Route::redirect('/', '/hr/employees')->name('dashboard');

        foreach (HrEntityRegistry::masterDataEntities() as $entity) {
            Route::get("/{$entity}", EntityIndexPage::class)->name("entities.{$entity}.index")->defaults('entity', $entity);
            Route::get("/{$entity}/create", EntityFormPage::class)->name("entities.{$entity}.create")->defaults('entity', $entity);
            Route::get("/{$entity}/{recordId}/edit", EntityFormPage::class)->name("entities.{$entity}.edit")->defaults('entity', $entity);
        }
    });
