<?php

namespace App\Http\Requests;

use App\Models\SaleItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Remove untouched rows before validating the sale return.
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
            'sale_id' => ['required', Rule::exists('sales', 'id')],
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', Rule::exists('sale_items', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure additional validation for returnable sale quantities.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $saleId = (int) $this->input('sale_id');
            $items = collect($this->input('items', []));

            if ($items->isEmpty()) {
                return;
            }

            $saleItems = SaleItem::query()
                ->with([
                    'stockBatch:id',
                    'saleReturnItems:id,sale_item_id,quantity',
                ])
                ->whereIn('id', $items->pluck('sale_item_id')->map(fn ($id) => (int) $id)->all())
                ->get()
                ->keyBy('id');

            foreach ($items as $index => $item) {
                $saleItemId = (int) ($item['sale_item_id'] ?? 0);
                $quantity = (float) ($item['quantity'] ?? 0);
                /** @var SaleItem|null $saleItem */
                $saleItem = $saleItems->get($saleItemId);

                if ($saleItem === null) {
                    $validator->errors()->add("items.{$index}.quantity", 'Item penjualan tidak ditemukan untuk retur ini.');
                    continue;
                }

                if ((int) $saleItem->sale_id !== $saleId) {
                    $validator->errors()->add("items.{$index}.quantity", 'Item retur tidak sesuai dengan penjualan yang dipilih.');
                    continue;
                }

                if ($saleItem->stockBatch === null) {
                    $validator->errors()->add("items.{$index}.quantity", 'Batch stok asal tidak ditemukan untuk item retur ini.');
                    continue;
                }

                $returnedQuantity = (float) $saleItem->saleReturnItems->sum(fn ($returnItem) => (float) $returnItem->quantity);
                $remainingQuantity = max(round((float) $saleItem->quantity - $returnedQuantity, 2), 0);

                if ($quantity > $remainingQuantity) {
                    $validator->errors()->add("items.{$index}.quantity", 'Qty retur melebihi sisa qty jual yang belum diretur.');
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
            'items.required' => 'Tambahkan minimal satu item batch untuk retur penjualan.',
            'items.min' => 'Tambahkan minimal satu item batch untuk retur penjualan.',
            'items.*.sale_item_id.required' => 'Item batch retur tidak valid.',
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
                'message' => 'Periksa kembali input retur penjualan. Masih ada data yang perlu diperbaiki.',
            ]);

        throw new ValidationException($validator, $response);
    }
}
