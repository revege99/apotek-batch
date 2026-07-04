<?php

namespace App\Http\Requests;

use App\Models\MedicineCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicineCategoryRequest extends FormRequest
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
            'classification_type' => trim((string) $this->input('classification_type')),
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
        /** @var \App\Models\MedicineCategory|null $medicineCategory */
        $medicineCategory = $this->route('medicineCategory');
        $classificationType = (string) $this->input('classification_type');

        return [
            'classification_type' => ['required', Rule::in([
                MedicineCategory::TYPE_MEDICINE_TYPE,
                MedicineCategory::TYPE_CATEGORY,
                MedicineCategory::TYPE_GROUP,
            ])],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('medicine_categories', 'name')
                    ->where(fn ($query) => $query->where('classification_type', $classificationType))
                    ->ignore($medicineCategory),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
