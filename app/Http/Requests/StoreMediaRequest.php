<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
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
            'post_id' => 'required|integer',
            'user_id' => 'required|integer',
            'files' => 'required|array',
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,mp4', 'max:51200'],
        ];
    }
}
