<?php

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::in(array_keys(Payment::SOURCES))],
            'method' => ['nullable', 'string', 'max:60'],
            'exam_id' => ['nullable', 'integer', Rule::exists('exams', 'id')],
            'receipt_status' => ['nullable', Rule::in(['issued', 'unissued'])],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function filters(): array
    {
        return collect($this->validated())
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();
    }
}
