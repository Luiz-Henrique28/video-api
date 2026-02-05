<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->user() ? $this->user()->id : null;
        return [
            'name' => [
                'sometimes', 
                'required', 
                'string', 
                'max:50',
                Rule::unique('user', 'name')->ignore($userId),
                'regex:/^(?![._-])(?!.*[._-]$)(?!.*[._-]{2})[A-Za-z0-9._-]+$/'
            ],

            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('user', 'email')->ignore($userId),
            ],

            'avatar' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'password' => [
                'sometimes',
                'nullable', // Pois no banco é nullable
                'string',
                'min:8',    // Boa prática de segurança
                'max:100',  // Respeitando o limite do banco
                'confirmed' 
            ],
        ];
    }
}
