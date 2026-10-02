<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'current_password' => [
                Rule::requiredIf($this->filled('password') || $this->input('email') !== $this->user()->email),
                'nullable', 'current_password',
            ],
            'password' => ['nullable', 'string', 'confirmed', Password::min(12)],
        ];
    }
}
