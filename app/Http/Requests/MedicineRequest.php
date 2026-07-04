<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicineRequest extends FormRequest
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
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'principal_id' => filled($this->input('principal_id')) ? (int) $this->input('principal_id') : null,
            'medicine_type' => trim((string) $this->input('medicine_type')),
            'category_name' => trim((string) $this->input('category_name')),
            'medicine_group' => trim((string) $this->input('medicine_group')),
            'large_unit' => trim((string) $this->input('large_unit')),
            'small_unit' => trim((string) $this->input('small_unit')),
            'composition' => trim((string) $this->input('composition')),
            'minimum_stock' => $this->normalizeMinimumStock($this->input('minimum_stock')),
            'purchase_price' => $this->normalizePurchasePrice($this->input('purchase_price')),
        ]);
    }

    private function normalizeMinimumStock(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return is_numeric($normalized)
            ? round((float) $normalized, 2)
            : null;
    }

    private function normalizePurchasePrice(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^-?\d{1,3}(?:\.\d{3})+(?:,\d+)?$/', $normalized) === 1) {
            $isNegative = str_starts_with($normalized, '-');
            $integerPart = explode(',', ltrim($normalized, '-'), 2)[0];
            $digits = preg_replace('/\D+/', '', $integerPart) ?? '';

            if ($digits === '') {
                return null;
            }

            return ($isNegative ? -1 : 1) * (int) $digits;
        }

        if (is_numeric($normalized)) {
            return (int) ((float) $normalized);
        }

        $isNegative = str_starts_with($normalized, '-');

        if (str_contains($normalized, ',')) {
            $normalized = explode(',', $normalized, 2)[0];
        }

        $digits = preg_replace('/\D+/', '', $normalized) ?? '';

        if ($digits === '') {
            return null;
        }

        return ($isNegative ? -1 : 1) * (int) $digits;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $medicine = $this->route('medicine');

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('medicines', 'code')->ignore($medicine)],
            'principal_id' => ['required', 'integer', Rule::exists('principals', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'medicine_type' => ['nullable', 'string', 'max:255'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'medicine_group' => ['nullable', 'string', 'max:255'],
            'large_unit' => ['nullable', 'string', 'max:100'],
            'small_unit' => ['nullable', 'string', 'max:100'],
            'small_unit_per_large_unit' => ['nullable', 'integer', 'min:1'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'composition' => ['nullable', 'string'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode barang wajib diisi.',
            'code.unique' => 'Kode barang sudah dipakai oleh obat lain.',
            'principal_id.required' => 'Industri farmasi wajib dipilih.',
            'principal_id.exists' => 'Pilih industri farmasi dari master yang tersedia.',
            'name.required' => 'Nama barang wajib diisi.',
        ];
    }
}
