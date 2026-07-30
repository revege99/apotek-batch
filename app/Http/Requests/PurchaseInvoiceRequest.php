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
        $today = now()->toDateString();
        $items = collect($this->input('items', []))
            ->filter(function ($item): bool {
                if (! is_array($item)) {
                    return false;
                }

                foreach (['batch_number', 'quantity', 'unit_price', 'discount_percentage', 'discount_amount'] as $field) {
                    if (array_key_exists($field, $item) && $item[$field] !== null && $item[$field] !== '') {
                        return true;
                    }
                }

                return false;
            })
            ->map(function (array $item) use ($today): array {
                $item['batch_number'] = trim((string) ($item['batch_number'] ?? ''));
                $item['expiry_date'] = filled($item['expiry_date'] ?? null)
                    ? $item['expiry_date']
                    : $today;

                return $item;
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
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
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
            'invoice_number.required' => 'Nomor faktur wajib diisi.',
            'invoice_number.unique' => 'Nomor faktur sudah pernah digunakan.',
            'invoice_date.required' => 'Tanggal faktur wajib diisi.',
            'invoice_date.date' => 'Tanggal faktur tidak valid.',
            'supplier_id.required' => 'Supplier wajib dipilih.',
            'supplier_id.exists' => 'Supplier yang dipilih tidak valid.',
            'payment_method.required' => 'Tentukan status pembayaran faktur terlebih dahulu.',
            'payment_method.in' => 'Metode pembayaran faktur tidak valid.',
            'tax_percentage.required' => 'Persentase PPN wajib diisi.',
            'tax_percentage.numeric' => 'Persentase PPN harus berupa angka.',
            'tax_percentage.max' => 'Persentase PPN maksimal 100%.',
            'items.required' => 'Tambahkan minimal satu item obat pada faktur pembelian.',
            'items.min' => 'Tambahkan minimal satu item obat pada faktur pembelian.',
            'items.*.medicine_id.required' => 'Pilih obat terlebih dahulu pada setiap baris item.',
            'items.*.medicine_id.exists' => 'Salah satu obat yang dipilih tidak valid.',
            'items.*.storage_location_id.required' => 'Lokasi wajib dipilih untuk setiap item obat.',
            'items.*.storage_location_id.exists' => 'Lokasi yang dipilih pada item obat tidak valid.',
            'items.*.unit_content.gt' => 'Isi harus lebih besar dari nol.',
            'items.*.batch_number.max' => 'Nomor batch maksimal 100 karakter.',
            'items.*.expiry_date.required' => 'Tanggal expired wajib diisi untuk setiap item obat.',
            'items.*.expiry_date.date' => 'Salah satu tanggal expired tidak valid.',
            'items.*.quantity.required' => 'Qty wajib diisi untuk setiap item obat.',
            'items.*.quantity.numeric' => 'Qty harus berupa angka.',
            'items.*.quantity.gt' => 'Qty harus lebih besar dari nol.',
            'items.*.unit_price.required' => 'Harga beli wajib diisi untuk setiap item obat.',
            'items.*.unit_price.numeric' => 'Harga beli harus berupa angka.',
            'items.*.unit_price.min' => 'Harga beli tidak boleh kurang dari nol.',
            'items.*.discount_percentage.max' => 'Diskon persentase maksimal 100%.',
        ];
    }

    /**
     * Flash validation feedback as toast instead of inline page alert.
     */
    protected function failedValidation(Validator $validator): void
    {
        $details = collect($validator->errors()->all())
            ->map(fn (string $message): string => trim($message))
            ->filter()
            ->unique()
            ->take(6)
            ->values()
            ->all();

        $response = redirect($this->getRedirectUrl())
            ->withInput($this->except($this->dontFlash))
            ->withErrors($validator, $this->errorBag)
            ->with('toast', [
                'type' => 'error',
                'message' => count($details) === 1
                    ? 'Faktur pembelian belum dapat disimpan karena ada satu data yang perlu diperbaiki.'
                    : 'Faktur pembelian belum dapat disimpan karena ada beberapa data yang perlu diperbaiki.',
                'details' => $details,
            ]);

        throw new ValidationException($validator, $response);
    }
}
