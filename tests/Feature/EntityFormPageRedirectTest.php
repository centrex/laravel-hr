<?php

declare(strict_types = 1);

use Centrex\Hr\Http\Livewire\Entities\EntityFormPage;
use Centrex\Hr\Models\Department;
use Illuminate\Support\Facades\{Artisan, Gate, Route};

beforeEach(function (): void {
    Artisan::call('migrate', ['--database' => 'testing']);
    Gate::define('hr.departments.manage', fn ($user = null): bool => true);

    // hr.web_enabled defaults to false in tests/TestCase.php, so the real
    // "hr.entities.{entity}.edit" route isn't registered — define a stand-in
    // so EntityFormPage::save()'s $this->redirect(route(...)) has somewhere to resolve to.
    Route::get('/test/hr/departments/{recordId}/edit', fn () => 'ok')->name('hr.entities.departments.edit');
});

it('saves a new entity via EntityFormPage::save() without throwing', function (): void {
    // Regression test for a production bug: save() used to `return redirect()->route(...)`
    // declared as `: \Illuminate\Http\RedirectResponse`. Inside a real Livewire component
    // dispatch, Livewire's SupportRedirects hook temporarily rebinds the container's
    // `redirect` entry so the global redirect() helper returns
    // Livewire\Features\SupportRedirects\Redirector instead of Illuminate\Http\RedirectResponse
    // — a mismatch PHP enforces as a fatal TypeError the instant the method returns (this is
    // exactly what production logs showed for the sibling laravel-payroll::EntityFormPage).
    // Fixed by using the component's own $this->redirect() instead of the global helper's
    // return value — this also correctly skips the now-redundant re-render.
    //
    // We instantiate directly (not via Livewire::test()) because even mounting this
    // component through the real Livewire test harness renders its view, which uses
    // <x-tallui-*> components this package doesn't install as a test dependency (they're
    // supplied by the host app in production). Livewire's container-swap that caused the
    // original crash is orthogonal to that — the crash happened inside save() itself,
    // enforced by PHP's own return-type check, before any rendering occurs.
    $component = new EntityFormPage;
    $component->mount('departments');
    $component->form['code'] = 'OPS';
    $component->form['name'] = 'Operations';

    $component->save();

    expect(Department::query()->where('code', 'OPS')->exists())->toBeTrue();
});
