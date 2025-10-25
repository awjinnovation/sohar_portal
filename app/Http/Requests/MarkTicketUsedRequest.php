<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkTicketUsedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'validated_by' => ['nullable', 'string', 'max:255'],
            'validation_notes' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'validated_by.string' => 'Validated by must be a string',
            'validated_by.max' => 'Validated by is too long',
            'validation_notes.string' => 'Validation notes must be a string',
            'validation_notes.max' => 'Validation notes is too long',
            'location.string' => 'Location must be a string',
            'location.max' => 'Location is too long',
        ];
    }
}
