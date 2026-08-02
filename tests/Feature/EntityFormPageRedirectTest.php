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
    $component = new EntityFormPage();
    $component->mount('departments');
    $component->form['code'] = 'OPS';
    $component->form['name'] = 'Operations';

    $component->save();

    expect(Department::query()->where('code', 'OPS')->exists())->toBeTrue();
});

it('exposes validation errors under the plain field name, not a "form." prefix', function (): void {
    // Regression test for a silent-failure bug: the view checks
    // $errors->first($field['name']) (e.g. $errors->first('code')), but save()'s validator
    // call validates a plain $payload array keyed by unprefixed field names — so the
    // resulting ValidationException's error bag is ALSO keyed unprefixed ('code'), not
    // 'form.code'. The view previously checked $errors->first('form.' . $field['name']),
    // which never matched, so a failed save (e.g. duplicate code) showed no error at all —
    // the form just silently didn't save, with nothing telling the user why.
    Department::query()->create(['code' => 'DUP', 'name' => 'Existing']);

    $component = new EntityFormPage();
    $component->mount('departments');
    $component->form['code'] = 'DUP';
    $component->form['name'] = 'Another';

    try {
        $component->save();
        $this->fail('Expected a ValidationException for the duplicate code.');
    } catch (Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('code');
    }

    expect(Department::query()->where('name', 'Another')->exists())->toBeFalse();
});

it('form-page.blade.php checks errors under the plain field name', function (): void {
    $view = file_get_contents(__DIR__ . '/../../resources/views/livewire/entities/form-page.blade.php');

    expect($view)->not->toContain("errors->first('form.'")
        ->not->toContain("errors->has('form.'");
});
