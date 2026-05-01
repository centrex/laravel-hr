<?php

declare(strict_types = 1);

namespace Centrex\Hr\Http\Controllers\Api;

use Centrex\Hr\Support\HrEntityRegistry;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class EntityCrudController extends Controller
{
    public function index(Request $request, string $entity): JsonResponse
    {
        $definition = HrEntityRegistry::definition($entity);
        $model = HrEntityRegistry::makeModel($entity);
        $query = $model->newQuery()->latest($model->getKeyName());

        if ($request->search && $definition['search'] !== []) {
            $search = $request->search;
            $query->where(function ($builder) use ($definition, $search): void {
                foreach ($definition['search'] as $column) {
                    $builder->orWhere($column, 'like', '%' . $search . '%');
                }
            });
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, string $entity): JsonResponse
    {
        $payload = HrEntityRegistry::fillablePayload($entity, $request->all());
        $validated = validator($payload, HrEntityRegistry::validationRules($entity))->validate();
        $record = HrEntityRegistry::makeModel($entity)->newQuery()->create($validated);

        return response()->json(['data' => $record], 201);
    }

    public function show(string $entity, int $recordId): JsonResponse
    {
        $record = HrEntityRegistry::makeModel($entity)->newQuery()->findOrFail($recordId);

        return response()->json(['data' => $record]);
    }

    public function update(Request $request, string $entity, int $recordId): JsonResponse
    {
        $record = HrEntityRegistry::makeModel($entity)->newQuery()->findOrFail($recordId);
        $payload = HrEntityRegistry::fillablePayload($entity, $request->all());
        $validated = validator($payload, HrEntityRegistry::validationRules($entity, $record))->validate();
        $record->fill($validated)->save();

        return response()->json(['data' => $record->fresh()]);
    }

    public function destroy(string $entity, int $recordId): JsonResponse
    {
        HrEntityRegistry::makeModel($entity)->newQuery()->findOrFail($recordId)->delete();

        return response()->json(null, 204);
    }
}
