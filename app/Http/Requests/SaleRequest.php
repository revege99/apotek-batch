<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Remove untouched rows before validating the sale.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(function ($item): bool {
                if (! is_array($item)) {
                    return false;
                }

                return array_key_exists('quantity', $item) && $item['quantity'] !== null && $item['quantity'] !== '';
            })
            ->values()
            ->all();
        $paymentKind = (string) $this->input(
            'payment_kind',
            ((string) $this->input('payment_method', 'cash')) === 'credit' ? 'credit' : 'cash'
        );

        $this->merge([
            'items' => $items,
            'payment_kind' => $paymentKind,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sale = $this->route('sale');

        return [
            'sale_number' => ['required', 'string', 'max:100', Rule::unique('sales', 'sale_number')->ignore($sale)],
            'sale_date' => ['required', 'date'],
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'payment_kind' => ['required', Rule::in(['cash', 'social', 'credit'])],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'debit', 'credit'])],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'other_cost_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', Rule::exists('medicines', 'id')],
            'items.*.stock_batch_id' => ['required'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.markup_percentage' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
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
            'customer_id.required' => 'Pilih pelanggan terlebih dahulu.',
            'payment_kind.required' => 'Pilih jenis pembayaran terlebih dahulu.',
            'items.required' => 'Tambahkan minimal satu item obat pada transaksi penjualan.',
            'items.min' => 'Tambahkan minimal satu item obat pada transaksi penjualan.',
            'items.*.stock_batch_id.required' => 'Pilih batch obat terlebih dahulu.',
            'items.*.quantity.gt' => 'Qty jual harus lebih besar dari nol.',
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
                'message' => 'Periksa kembali input kasir penjualan. Masih ada data yang perlu diperbaiki.',
            ]);

        throw new ValidationException($validator, $response);
    }
}
