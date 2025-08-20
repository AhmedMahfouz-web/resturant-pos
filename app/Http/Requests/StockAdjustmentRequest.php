<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add authorization logic as needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'material_id' => 'required|exists:materials,id',
            'adjustment_type' => 'required|in:increase,decrease,set',
            'quantity' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
            'unit_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'material_id.required' => 'Material selection is required.',
            'material_id.exists' => 'Selected material does not exist.',
            'adjustment_type.required' => 'Adjustment type is required.',
            'adjustment_type.in' => 'Adjustment type must be increase, decrease, or set.',
            'quantity.required' => 'Quantity is required.',
            'quantity.numeric' => 'Quantity must be a number.',
            'quantity.min' => 'Quantity must be greater than or equal to 0.',
            'reason.required' => 'Reason for adjustment is required.',
            'reason.max' => 'Reason cannot exceed 255 characters.',
            'unit_cost.numeric' => 'Unit cost must be a number.',
            'unit_cost.min' => 'Unit cost must be greater than or equal to 0.',
            'notes.max' => 'Notes cannot exceed 500 characters.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'material_id' => 'material',
            'adjustment_type' => 'adjustment type',
            'unit_cost' => 'unit cost'
        ];
    }
}
