<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'nama' => $this->method() == 'PUT' || $this->method() == 'PUT' ? ['required','string','max:255', Rule::unique('items')->ignore($this->route('item'))] : 'required|string|max:255|unique:items',
            'taxs' => 'array',
            'taxs.*.nama' => 'required|string',
            'taxs.*.rate' => 'required|numeric|gte:0'
        ];
    }
}
