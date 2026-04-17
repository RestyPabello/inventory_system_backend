<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                     => 'required|string|max:255',
            'brand'                    => 'nullable|string|max:255',
            'item_description'         => 'nullable|string',
            'category_id'              => 'required|exists:categories,id',
            'unit_id'                  => 'required|exists:units,id',
            'item_variant_value'       => 'required',
            'price'                    => 'required|decimal:0,2|min:0',
            'item_variant_description' => 'nullable|string',
            'quantity'                 => 'required|integer|min:1',
            'status'                   => 'required',
            'expires_at'               => 'required|date|after:today'
        ];
    }
}
