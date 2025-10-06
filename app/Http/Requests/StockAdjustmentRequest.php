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
            'materials' => 'required|array|min:1',
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.quantity' => 'required|numeric|min:0',
            'materials.*.unit_cost' => 'nullable|numeric|min:0',
            'adjustment_type' => 'nullable|in:increase,decrease,set',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'materials.required' => 'Materials selection is required.',
            'materials.array' => 'Materials must be an array.',
            'materials.min' => 'At least one material is required.',
            'materials.*.material_id.required' => 'Material selection is required.',
            'materials.*.material_id.exists' => 'Selected material does not exist.',
            'materials.*.quantity.required' => 'Quantity is required.',
            'materials.*.quantity.numeric' => 'Quantity must be a number.',
            'materials.*.quantity.min' => 'Quantity must be greater than or equal to 0.',
            'materials.*.unit_cost.numeric' => 'Unit cost must be a number.',
            'materials.*.unit_cost.min' => 'Unit cost must be greater than or equal to 0.',
            'adjustment_type.in' => 'Adjustment type must be increase, decrease, or set.',
            'reason.required' => 'Reason for adjustment is required.',
            'reason.max' => 'Reason cannot exceed 255 characters.',
            'notes.max' => 'Notes cannot exceed 500 characters.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'materials' => 'materials',
            'materials.*.material_id' => 'material',
            'materials.*.unit_cost' => 'unit cost',
            'adjustment_type' => 'adjustment type'
        ];
    }
}
