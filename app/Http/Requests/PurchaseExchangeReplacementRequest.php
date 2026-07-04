<?php

namespace App\Http\Requests;

use App\Models\PurchaseExchangeItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseExchangeReplacementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Remove untouched replacement rows before validating the request.
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
            'purchase_return_id' => ['required', Rule::exists('purchase_exchanges', 'id')],
            'replacement_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_return_item_id' => ['required', Rule::exists('purchase_exchange_items', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * Configure additional validation for remaining replacement quantity.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $purchaseExchangeId = (int) $this->input('purchase_return_id');
            $items = collect($this->input('items', []));

            if ($items->isEmpty()) {
                return;
            }

            $returnItems = PurchaseExchangeItem::query()
                ->with('replacementItems:id,purchase_exchange_item_id,quantity')
                ->whereIn('id', $items->pluck('purchase_return_item_id')->map(fn ($id) => (int) $id)->all())
                ->get()
                ->keyBy('id');

            foreach ($items as $index => $item) {
                $purchaseReturnItemId = (int) ($item['purchase_return_item_id'] ?? 0);
                $quantity = (float) ($item['quantity'] ?? 0);
                /** @var PurchaseExchangeItem|null $returnItem */
                $returnItem = $returnItems->get($purchaseReturnItemId);

                if ($returnItem === null) {
                    $validator->errors()->add("items.{$index}.quantity", 'Item tukar barang tidak ditemukan untuk realisasi ini.');
                    continue;
                }

                if ((int) $returnItem->purchase_exchange_id !== $purchaseExchangeId) {
                    $validator->errors()->add("items.{$index}.quantity", 'Item tidak sesuai dengan nomor tukar barang yang dipilih.');
                    continue;
                }

                $realizedQuantity = (float) $returnItem->replacementItems->sum(fn ($replacementItem) => (float) $replacementItem->quantity);
                $remainingQuantity = round((float) $returnItem->quantity - $realizedQuantity, 2);

                if ($quantity > $remainingQuantity) {
                    $validator->errors()->add("items.{$index}.quantity", 'Qty realisasi melebihi sisa tukar barang yang belum direalisasikan.');
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
            'items.required' => 'Tambahkan minimal satu item untuk realisasi tukar barang.',
            'items.min' => 'Tambahkan minimal satu item untuk realisasi tukar barang.',
            'items.*.purchase_return_item_id.required' => 'Item tukar barang tidak valid.',
            'items.*.quantity.gt' => 'Qty realisasi harus lebih besar dari nol.',
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
                'message' => 'Periksa kembali input realisasi tukar barang. Masih ada data yang perlu diperbaiki.',
            ]);

        throw new ValidationException($validator, $response);
    }
}
