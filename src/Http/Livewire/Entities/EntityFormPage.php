<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Livewire\Entities;

use Centrex\Hr\Support\HrEntityRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EntityFormPage extends Component
{
    public string $entity = '';

    public ?int $recordId = null;

    public array $form = [];

    public function mount(string $entity, ?int $recordId = null): void
    {
        $definition = HrEntityRegistry::definition($entity);

        $this->entity = $entity;
        $this->recordId = $recordId;
        $this->form = HrEntityRegistry::defaultFormData($entity);

        if ($recordId !== null) {
            $record = $this->record();

            foreach ($definition['form_fields'] as $field) {
                $this->form[$field['name']] = $record->getAttribute($field['name']);
            }
        }
    }

    public function save(): \Illuminate\Http\RedirectResponse
    {
        $record = $this->record(false);
        $payload = HrEntityRegistry::fillablePayload($this->entity, $this->form);
        $validated = validator($payload, HrEntityRegistry::validationRules($this->entity, $record))->validate();

        if ($record) {
            $record->fill($validated)->save();
        } else {
            $record = HrEntityRegistry::makeModel($this->entity)->newQuery()->create($validated);
        }

        session()->flash('hr.status', HrEntityRegistry::definition($this->entity)['singular'] . ' saved.');

        return redirect()->route("hr.entities.{$this->entity}.edit", ['recordId' => $record->getKey()]);
    }

    public function render(): View
    {
        return view('hr::livewire.entities.form-page', [
            'definition' => HrEntityRegistry::definition($this->entity),
            'options'    => HrEntityRegistry::formOptions($this->entity),
        ]);
    }

    private function record(bool $failIfMissing = true): ?Model
    {
        if ($this->recordId === null) {
            return null;
        }

        $query = HrEntityRegistry::makeModel($this->entity)->newQuery();

        return $failIfMissing
            ? $query->findOrFail($this->recordId)
            : $query->find($this->recordId);
    }
}
