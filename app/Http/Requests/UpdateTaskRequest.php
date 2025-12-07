<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string'],
            'description' => ['sometimes', 'required', 'string'],
            'employee_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'estimate_until' => ['sometimes', 'nullable', 'date'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*.file' => ['sometimes', 'file'],
            'attachments.*.url' => ['sometimes', 'url'],
            'attachments.*.id' => ['sometimes', 'integer', 'exists:media,id'],
            'attachments.*.order' => ['sometimes', 'string'],
        ];
    }
}
