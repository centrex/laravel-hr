<?php

declare(strict_types = 1);

namespace Centrex\Hr\Support;

use Centrex\Hr\Models\{Department, Designation, Employee, LeaveType};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Arr, Str};
use Illuminate\Validation\Rule;

class HrEntityRegistry
{
    public static function entities(): array
    {
        return [
            'departments' => [
                'label'         => 'Departments',
                'singular'      => 'Department',
                'model'         => Department::class,
                'search'        => ['code', 'name', 'description'],
                'index_columns' => ['code', 'name', 'is_active'],
                'form_fields'   => [
                    self::field('code', 'text', ['required', 'string', 'max:30']),
                    self::field('name', 'text', ['required', 'string', 'max:200']),
                    self::field('description', 'textarea', ['nullable', 'string']),
                    self::field('manager_id', 'select', ['nullable', 'integer', 'exists:' . self::table(Employee::class) . ',id'], null, Employee::class, 'name'),
                    self::field('is_active', 'checkbox', ['boolean'], true),
                ],
            ],
            'designations' => [
                'label'         => 'Designations',
                'singular'      => 'Designation',
                'model'         => Designation::class,
                'search'        => ['code', 'name', 'description'],
                'index_columns' => ['code', 'name', 'salary_min', 'salary_max', 'is_active'],
                'form_fields'   => [
                    self::field('code', 'text', ['required', 'string', 'max:30']),
                    self::field('name', 'text', ['required', 'string', 'max:200']),
                    self::field('department_id', 'select', ['nullable', 'integer', 'exists:' . self::table(Department::class) . ',id'], null, Department::class, 'name'),
                    self::field('description', 'textarea', ['nullable', 'string']),
                    self::field('salary_min', 'number', ['nullable', 'numeric', 'min:0'], 0),
                    self::field('salary_max', 'number', ['nullable', 'numeric', 'min:0'], 0),
                    self::field('is_active', 'checkbox', ['boolean'], true),
                ],
            ],
            'employees' => [
                'label'         => 'Employees',
                'singular'      => 'Employee',
                'model'         => Employee::class,
                'search'        => ['code', 'name', 'email', 'phone'],
                'index_columns' => ['code', 'name', 'email', 'employment_type', 'status', 'monthly_salary', 'currency', 'is_active'],
                'form_fields'   => [
                    self::field('code', 'text', ['required', 'string', 'max:30']),
                    self::field('name', 'text', ['required', 'string', 'max:300']),
                    self::field('email', 'email', ['nullable', 'email', 'max:200']),
                    self::field('phone', 'text', ['nullable', 'string', 'max:50']),
                    self::field('address', 'textarea', ['nullable', 'string']),
                    self::field('city', 'text', ['nullable', 'string', 'max:100']),
                    self::field('country', 'text', ['nullable', 'string', 'max:100']),
                    self::field('department_id', 'select', ['nullable', 'integer', 'exists:' . self::table(Department::class) . ',id'], null, Department::class, 'name'),
                    self::field('designation_id', 'select', ['nullable', 'integer', 'exists:' . self::table(Designation::class) . ',id'], null, Designation::class, 'name'),
                    self::field('manager_id', 'select', ['nullable', 'integer', 'exists:' . self::table(Employee::class) . ',id'], null, Employee::class, 'name'),
                    self::field('employment_type', 'text', ['required', 'string', 'max:50'], 'full_time'),
                    self::field('status', 'text', ['required', 'string', 'max:50'], 'active'),
                    self::field('joining_date', 'date', ['nullable', 'date']),
                    self::field('termination_date', 'date', ['nullable', 'date', 'after_or_equal:joining_date']),
                    self::field('monthly_salary', 'number', ['nullable', 'numeric', 'min:0'], 0),
                    self::field('currency', 'text', ['required', 'string', 'size:3'], config('hr.currency', 'BDT')),
                    self::field('bank_account_name', 'text', ['nullable', 'string', 'max:200']),
                    self::field('bank_account_number', 'text', ['nullable', 'string', 'max:100']),
                    self::field('tax_id', 'text', ['nullable', 'string', 'max:50']),
                    self::field('emergency_contact_name', 'text', ['nullable', 'string', 'max:200']),
                    self::field('emergency_contact_phone', 'text', ['nullable', 'string', 'max:50']),
                    self::field('is_active', 'checkbox', ['boolean'], true),
                ],
            ],
            'leave-types' => [
                'label'         => 'Leave Types',
                'singular'      => 'Leave Type',
                'model'         => LeaveType::class,
                'search'        => ['code', 'name'],
                'index_columns' => ['code', 'name', 'annual_allowance', 'is_paid', 'requires_approval', 'is_active'],
                'form_fields'   => [
                    self::field('code', 'text', ['required', 'string', 'max:30']),
                    self::field('name', 'text', ['required', 'string', 'max:200']),
                    self::field('annual_allowance', 'number', ['nullable', 'integer', 'min:0'], 0),
                    self::field('is_paid', 'checkbox', ['boolean'], true),
                    self::field('requires_approval', 'checkbox', ['boolean'], true),
                    self::field('is_active', 'checkbox', ['boolean'], true),
                ],
            ],
        ];
    }

    public static function masterDataEntities(): array
    {
        return array_keys(self::entities());
    }

    public static function definition(string $entity): array
    {
        $definition = self::entities()[$entity] ?? null;

        if (!$definition) {
            throw new \InvalidArgumentException("Unknown HR entity [{$entity}].");
        }

        return $definition;
    }

    public static function makeModel(string $entity): Model
    {
        $modelClass = self::definition($entity)['model'];

        return new $modelClass();
    }

    public static function validationRules(string $entity, ?Model $record = null): array
    {
        $definition = self::definition($entity);
        $rules = [];
        $model = self::makeModel($entity);
        $table = $model->getTable();

        foreach ($definition['form_fields'] as $field) {
            $fieldRules = $field['rules'];

            if ($field['name'] === 'code') {
                $fieldRules[] = Rule::unique($table, 'code')->ignore($record?->getKey());
            }

            $rules[$field['name']] = $fieldRules;
        }

        return $rules;
    }

    public static function fillablePayload(string $entity, array $payload): array
    {
        $output = [];

        foreach (self::definition($entity)['form_fields'] as $field) {
            $name = $field['name'];
            $value = Arr::get($payload, $name, $field['default']);

            if ($field['type'] === 'checkbox') {
                $value = (bool) $value;
            }

            if ($value === '' && str_contains(implode('|', $field['rules']), 'nullable')) {
                $value = null;
            }

            if (is_string($value) && in_array($field['type'], ['text', 'email'], true)) {
                $value = trim($value);
                $value = $name === 'currency' ? Str::upper($value) : $value;
            }

            $output[$name] = $value;
        }

        return $output;
    }

    public static function defaultFormData(string $entity): array
    {
        $defaults = [];

        foreach (self::definition($entity)['form_fields'] as $field) {
            $defaults[$field['name']] = $field['default'];
        }

        return $defaults;
    }

    public static function formOptions(string $entity): array
    {
        $options = [];

        foreach (self::definition($entity)['form_fields'] as $field) {
            if (($field['type'] ?? null) !== 'select' || empty($field['related_model'])) {
                continue;
            }

            $related = new $field['related_model']();
            $options[$field['name']] = $related->newQuery()
                ->orderBy($field['related_label'])
                ->get(['id', $field['related_label']])
                ->map(fn (Model $model): array => [
                    'value' => (string) $model->getKey(),
                    'label' => (string) $model->getAttribute($field['related_label']),
                ])
                ->all();
        }

        return $options;
    }

    public static function indexColumns(string $entity): array
    {
        return self::definition($entity)['index_columns'];
    }

    private static function field(
        string $name,
        string $type,
        array $rules,
        mixed $default = null,
        ?string $relatedModel = null,
        ?string $relatedLabel = null,
    ): array {
        return [
            'name'          => $name,
            'label'         => Str::of($name)->replace('_', ' ')->title()->toString(),
            'type'          => $type,
            'rules'         => $rules,
            'default'       => $default,
            'related_model' => $relatedModel,
            'related_label' => $relatedLabel,
        ];
    }

    private static function table(string $modelClass): string
    {
        return (new $modelClass())->getTable();
    }
}
