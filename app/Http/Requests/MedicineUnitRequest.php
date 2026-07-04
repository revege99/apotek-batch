<?php

namespace App\Http\Requests;

use App\Models\MedicineUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicineUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'unit_type' => trim((string) $this->input('unit_type')),
            'name' => trim((string) $this->input('name')),
            'description' => trim((string) $this->input('description')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var \App\Models\MedicineUnit|null $medicineUnit */
        $medicineUnit = $this->route('medicineUnit');
        $unitType = (string) $this->input('unit_type');

        return [
            'unit_type' => ['required', Rule::in([
                MedicineUnit::TYPE_LARGE,
                MedicineUnit::TYPE_SMALL,
            ])],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('medicine_units', 'name')
                    ->where(fn ($query) => $query->where('unit_type', $unitType))
                    ->ignore($medicineUnit),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
