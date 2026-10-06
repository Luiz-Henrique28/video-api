<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
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
            'caption' => 'nullable|string|max:200',
            'visibility' => 'required|in:public,private',
            'tags' => 'nullable|array',
            'tags.*' => ['required', 'string'],
            'files' => 'required|array|min:1',
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,mp4', 'max:51200'],
        ];
    }
}
