<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class PharmacyProfileRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'invoice_footer' => ['nullable', 'string'],
        ];
    }

    /**
     * Flash validation feedback as toast instead of inline alert.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = redirect($this->getRedirectUrl())
            ->withInput($this->except($this->dontFlash))
            ->withErrors($validator, $this->errorBag)
            ->with('toast', [
                'type' => 'error',
                'message' => 'Periksa kembali profil apotik. Masih ada data yang perlu diperbaiki.',
            ]);

        throw new ValidationException($validator, $response);
    }
}
