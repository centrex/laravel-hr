<?php

declare(strict_types = 1);

use Centrex\Hr\Http\Livewire\Entities\{EntityFormPage, EntityIndexPage};
use Centrex\Hr\Http\Livewire\{AttendanceManagementPage, HrDashboard, LeaveApprovalsPage, MyAttendancePage, MyLeavePage};
use Centrex\Hr\Support\HrEntityRegistry;
use Illuminate\Support\Facades\Route;

Route::middleware(config('hr.web_middleware', ['web', 'auth']))
    ->prefix(config('hr.web_prefix', 'hr'))
    ->as('hr.')
    ->group(function (): void {
        Route::get('/dashboard', HrDashboard::class)->name('dashboard');
        Route::get('/attendance', AttendanceManagementPage::class)->name('attendance');

        foreach (HrEntityRegistry::masterDataEntities() as $entity) {
            Route::get("/{$entity}", EntityIndexPage::class)->name("entities.{$entity}.index")->defaults('entity', $entity);
            Route::get("/{$entity}/create", EntityFormPage::class)->name("entities.{$entity}.create")->defaults('entity', $entity);
            Route::get("/{$entity}/{recordId}/edit", EntityFormPage::class)->name("entities.{$entity}.edit")->defaults('entity', $entity);
        }
    });

// Self-service — open to any authenticated user, not gated behind admin roles: an
// employee checks in/out and requests leave for themselves; a manager approves their
// own direct reports' requests. See config('hr.self_service_middleware').
Route::middleware(config('hr.self_service_middleware', ['web', 'auth']))
    ->prefix(config('hr.web_prefix', 'hr'))
    ->as('hr.')
    ->group(function (): void {
        Route::get('/my-attendance', MyAttendancePage::class)->name('my-attendance');
        Route::get('/my-leave', MyLeavePage::class)->name('my-leave');
        Route::get('/leave-approvals', LeaveApprovalsPage::class)->name('leave-approvals');
    });
