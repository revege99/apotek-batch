<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Remove untouched medicine rows before validating the invoice.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(function ($item): bool {
                if (! is_array($item)) {
                    return false;
                }

                foreach (['batch_number', 'expiry_date', 'quantity', 'unit_price', 'discount_percentage', 'discount_amount'] as $field) {
                    if (array_key_exists($field, $item) && $item[$field] !== null && $item[$field] !== '') {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $purchaseInvoice = $this->route('purchaseInvoice');

        return [
            'invoice_number' => ['required', 'string', 'max:100', Rule::unique('purchase_invoices', 'invoice_number')->ignore($purchaseInvoice)],
            'invoice_date' => ['required', 'date'],
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'debit', 'credit'])],
            'tax_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', Rule::exists('medicines', 'id')],
            'items.*.storage_location_id' => ['required', Rule::exists('storage_locations', 'id')],
            'items.*.unit_content' => ['nullable', 'numeric', 'gt:0'],
            'items.*.batch_number' => ['required', 'string', 'max:100'],
            'items.*.expiry_date' => ['required', 'date'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_mode' => ['nullable', Rule::in(['percent', 'amount'])],
            'items.*.update_master_purchase_price' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Tentukan status pembayaran faktur terlebih dahulu.',
            'items.required' => 'Tambahkan minimal satu item obat pada faktur pembelian.',
            'items.min' => 'Tambahkan minimal satu item obat pada faktur pembelian.',
            'items.*.medicine_id.required' => 'Pilih obat terlebih dahulu pada setiap baris item.',
            'items.*.storage_location_id.required' => 'Lokasi wajib dipilih untuk setiap item obat.',
            'items.*.storage_location_id.exists' => 'Lokasi yang dipilih pada item obat tidak valid.',
            'items.*.unit_content.gt' => 'Isi harus lebih besar dari nol.',
            'items.*.batch_number.required' => 'No batch wajib diisi untuk setiap item obat.',
            'items.*.expiry_date.required' => 'Tanggal expired wajib diisi untuk setiap item obat.',
            'items.*.quantity.gt' => 'Qty harus lebih besar dari nol.',
        ];
    }

    /**
     * Flash validation feedback as toast instead of inline page alert.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = redirect($this->getRedirectUrl())
            ->withInput($this->except($this->dontFlash))
            ->withErrors($validator, $this->errorBag)
            ->with('toast', [
                'type' => 'error',
                'message' => 'Periksa kembali input faktur pembelian. Masih ada data yang perlu diperbaiki.',
            ]);

        throw new ValidationException($validator, $response);
    }
}
