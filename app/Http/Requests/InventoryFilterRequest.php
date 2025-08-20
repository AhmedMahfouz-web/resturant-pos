<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'low_stock' => 'nullable|boolean',
            'out_of_stock' => 'nullable|boolean',
            'expiring_within_days' => 'nullable|integer|min:1|max:365',
            'expired' => 'nullable|boolean',
            'available_only' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|string|in:name,quantity,updated_at,created_at',
            'sort_direction' => 'nullable|string|in:asc,desc'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Selected category does not exist.',
            'supplier_id.exists' => 'Selected supplier does not exist.',
            'expiring_within_days.integer' => 'Expiring within days must be a number.',
            'expiring_within_days.min' => 'Expiring within days must be at least 1.',
            'expiring_within_days.max' => 'Expiring within days cannot exceed 365.',
            'per_page.integer' => 'Items per page must be a number.',
            'per_page.min' => 'Items per page must be at least 1.',
            'per_page.max' => 'Items per page cannot exceed 100.',
            'sort_by.in' => 'Sort by must be one of: name, quantity, updated_at, created_at.',
            'sort_direction.in' => 'Sort direction must be asc or desc.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'supplier_id' => 'supplier',
            'per_page' => 'items per page',
            'sort_by' => 'sort field',
            'sort_direction' => 'sort direction'
        ];
    }
}
