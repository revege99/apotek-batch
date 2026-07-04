<?php

namespace App\Http\Requests;

use App\Models\StockBatch;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Remove untouched return rows before validating the return.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item): bool => is_array($item) && ($item['quantity'] ?? null) !== null && ($item['quantity'] ?? '') !== '')
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
        return [
            'purchase_invoice_id' => ['required', Rule::exists('purchase_invoices', 'id')],
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_invoice_item_id' => ['required', Rule::exists('purchase_invoice_items', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure additional validation for returnable stock.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $invoiceId = (int) $this->input('purchase_invoice_id');
            $items = collect($this->input('items', []));

            if ($items->isEmpty()) {
                return;
            }

            $stockBatches = StockBatch::query()
                ->with('purchaseInvoiceItem:id,purchase_invoice_id')
                ->whereIn('purchase_invoice_item_id', $items->pluck('purchase_invoice_item_id')->map(fn ($id) => (int) $id)->all())
                ->get()
                ->keyBy('purchase_invoice_item_id');

            foreach ($items as $index => $item) {
                $purchaseInvoiceItemId = (int) ($item['purchase_invoice_item_id'] ?? 0);
                $quantity = (float) ($item['quantity'] ?? 0);
                $stockBatch = $stockBatches->get($purchaseInvoiceItemId);

                if ($stockBatch === null) {
                    $validator->errors()->add("items.{$index}.quantity", 'Batch pembelian tidak ditemukan untuk retur ini.');
                    continue;
                }

                if ((int) $stockBatch->purchaseInvoiceItem?->purchase_invoice_id !== $invoiceId) {
                    $validator->errors()->add("items.{$index}.quantity", 'Item retur tidak sesuai dengan faktur pembelian yang dipilih.');
                    continue;
                }

                if ($quantity > (float) $stockBatch->quantity_balance) {
                    $validator->errors()->add("items.{$index}.quantity", 'Qty retur melebihi stok batch yang tersedia.');
                }
            }
        });
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Tambahkan minimal satu item batch untuk retur pembelian.',
            'items.min' => 'Tambahkan minimal satu item batch untuk retur pembelian.',
            'items.*.purchase_invoice_item_id.required' => 'Item batch retur tidak valid.',
            'items.*.quantity.gt' => 'Qty retur harus lebih besar dari nol.',
        ];
    }

    /**
     * Flash validation feedback as toast.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = redirect($this->getRedirectUrl())
            ->withInput($this->except($this->dontFlash))
            ->withErrors($validator, $this->errorBag)
            ->with('toast', [
                'type' => 'error',
                'message' => 'Periksa kembali input retur pembelian. Masih ada data yang perlu diperbaiki.',
            ]);

        throw new ValidationException($validator, $response);
    }
}
