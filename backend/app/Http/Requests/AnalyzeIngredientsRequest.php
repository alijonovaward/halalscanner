<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyzeIngredientsRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_name' => ['nullable', 'string', 'max:255'],
            'product_type' => [
                'nullable',
                'required_without_all:label_text,ingredients_text',
                'string',
                'max:255',
            ],
            'product_type_basis' => ['nullable', Rule::in(['label', 'visual', 'unknown'])],
            'barcode' => ['nullable', 'string', 'max:32', 'regex:/^[0-9A-Za-z._-]+$/'],
            'label_text' => [
                'nullable',
                'required_without_all:ingredients_text,product_type',
                'string',
                'min:3',
                'max:10000',
            ],
            'ingredients_text' => [
                'nullable',
                'required_without_all:label_text,product_type',
                'string',
                'min:3',
                'max:5000',
            ],
            'source_quality' => ['nullable', Rule::in(['complete', 'partial', 'unreadable'])],
        ];
    }
}
