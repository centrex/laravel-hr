<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Livewire\Entities;

use Centrex\Hr\Support\HrEntityRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\{Component, WithPagination};

#[Layout('layouts.app')]
class EntityIndexPage extends Component
{
    use WithPagination;

    public string $entity = '';

    public string $search = '';

    public string $filterMin = '';

    public string $filterMax = '';

    public function mount(string $entity): void
    {
        HrEntityRegistry::definition($entity);

        Gate::authorize(HrEntityRegistry::permissionsFor($entity)['view']);

        $this->entity = $entity;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterMin(): void
    {
        $this->resetPage();
    }

    public function updatingFilterMax(): void
    {
        $this->resetPage();
    }

    public function delete(int $recordId): void
    {
        HrEntityRegistry::makeModel($this->entity)->newQuery()->findOrFail($recordId)->delete();

        session()->flash('hr.status', 'Record deleted.');
        $this->resetPage();
    }

    public function render(): View
    {
        $definition = HrEntityRegistry::definition($this->entity);
        $model = HrEntityRegistry::makeModel($this->entity);
        $query = $model->newQuery()->latest($model->getKeyName());

        if ($this->search !== '' && $definition['search'] !== []) {
            $search = $this->search;
            $query->where(function ($builder) use ($definition, $search): void {
                foreach ($definition['search'] as $column) {
                    $builder->orWhere($column, 'like', '%' . $search . '%');
                }
            });
        }

        $qtyFilter = $definition['quantity_filter'] ?? null;

        if ($qtyFilter !== null) {
            $col = $qtyFilter['column'];

            if ($this->filterMin !== '') {
                $query->where($col, '>=', (float) $this->filterMin);
            }

            if ($this->filterMax !== '') {
                $query->where($col, '<=', (float) $this->filterMax);
            }
        }

        return view('hr::livewire.entities.index-page', [
            'definition' => $definition,
            'columns'    => HrEntityRegistry::indexColumns($this->entity),
            'records'    => $query->paginate(config("hr.per_page.{$this->entity}", 15)),
            'qtyFilter'  => $qtyFilter,
        ]);
    }
}
