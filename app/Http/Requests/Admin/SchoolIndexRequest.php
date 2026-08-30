<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'has_coordinators' => ['nullable', Rule::in(['yes', 'no'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['created_at', 'name', 'school_code', 'state', 'city', 'coordinators'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $filters = collect($this->validated())
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();

        $filters['sort'] ??= 'created_at';
        $filters['direction'] ??= 'desc';
        $filters['per_page'] ??= 20;

        return $filters;
    }
}
