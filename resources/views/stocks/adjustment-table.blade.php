@foreach ($forms as $formData)
    @php
        $formItem = $formData['item'];
    @endphp
    <form id="adjustment-form-{{ $formItem->id }}" method="POST" action="{{ route('stok-batch.penyesuaian-stok.follow-up.store', $formItem->id) }}">
        @csrf
        <input type="hidden" name="opname_item_id" value="{{ $formItem->id }}">
        <input type="hidden" name="adjustment_number" value="{{ $formData['defaultAdjustmentNumber'] }}">
        <input type="hidden" name="adjustment_date" value="{{ $formData['defaultAdjustmentDate'] }}">
        <input type="hidden" name="settlement_type" value="{{ $formItem->followUp?->settlement_type ?? ((float) $formItem->difference_quantity < 0 ? 'writeoff' : 'stock_found') }}">
        <input type="hidden" name="employee_name" value="{{ $formItem->followUp?->employee_name }}">
        <input type="hidden" name="replacement_batch_number" value="{{ $formItem->followUp?->replacement_batch_number }}">
        <input type="hidden" name="replacement_expiry_date" value="{{ $formItem->followUp?->replacement_expiry_date?->toDateString() }}">
        <input type="hidden" name="replacement_purchase_price" value="{{ $formItem->followUp?->replacement_purchase_price }}">
        <input type="hidden" name="replacement_storage_location_id" value="{{ $formItem->followUp?->replacement_storage_location_id }}">
    </form>
@endforeach

<section class="panel-surface overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="min-w-[900px] w-full divide-y divide-slate-200/80 text-[0.72rem]">
            <thead class="bg-slate-50/90">
                <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.12em] text-slate-400">
                    <th class="px-3 py-2.5">Kode</th><th class="px-2.5 py-2.5">Obat</th><th class="px-2.5 py-2.5">Batch</th><th class="px-2.5 py-2.5">Lokasi</th>
                    <th class="px-2.5 py-2.5 text-center">Stok sistem</th><th class="px-2.5 py-2.5 text-center">Stok fisik batch</th>
                    <th class="px-2.5 py-2.5">Jenis penyesuaian</th>
                </tr>
            </thead>
            @forelse ($forms as $formData)
                @php
                    $item = $formData['item'];
                    $batches = $formData['batches'];
                    $selectionMap = $formData['selectionMap'];
                    $differenceType = (float) $item->difference_quantity < 0 ? 'loss' : 'gain';
                    $settlementType = $item->followUp?->settlement_type ?? ($differenceType === 'loss' ? 'writeoff' : 'stock_found');
                    $isCurrentError = (string) old('opname_item_id') === (string) $item->id;
                    $fieldValue = fn (string $key, mixed $default = null) => $isCurrentError ? old($key, $default) : $default;
                    $quantityValue = function (mixed $value): string {
                        if ($value === null || $value === '') return '';

                        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
                    };
                    $formId = 'adjustment-form-'.$item->id;
                @endphp
                <tbody
                    x-data="{
                        saving: false, saved: false, pending: false, timer: null, settlementType: @js($settlementType), toastMessage: '', toastError: false, currentBatch: '',
                        autoSave(batchName = '') { clearTimeout(this.timer); this.saved = false; this.currentBatch = batchName; this.timer = setTimeout(() => this.submitRow(), 200); },
                        async submitRow(batchName = '') {
                            clearTimeout(this.timer);
                            if (batchName) this.currentBatch = batchName;
                            if (this.saving) { this.pending = true; return; }
                            const form = document.getElementById(this.$root.dataset.formId);
                            form.querySelector('[name=settlement_type]').value = this.settlementType;
                            this.saving = true;
                            try {
                                const response = await fetch(form.action, { method: 'POST', body: new FormData(form), keepalive: true, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                                const result = await response.json();
                                this.saved = response.ok;
                                this.toastError = ! response.ok;
                                this.toastMessage = response.ok ? `Batch ${this.currentBatch} berhasil disimpan.` : (result.message || 'Stok fisik gagal disimpan.');
                                setTimeout(() => this.toastMessage = '', 2500);
                            } catch (error) {
                                this.saved = false;
                                this.toastError = true;
                                this.toastMessage = 'Stok fisik gagal disimpan.';
                                setTimeout(() => this.toastMessage = '', 2500);
                            } finally {
                                this.saving = false;
                                if (this.pending) { this.pending = false; this.submitRow(); }
                            }
                        },
                        focusNext(event) {
                            const inputs = [...document.querySelectorAll('[data-adjustment-physical-input]')];
                            const next = inputs[inputs.indexOf(event.currentTarget) + 1];
                            if (next) { next.focus(); next.select(); }
                            this.submitRow(event.currentTarget.dataset.batchName);
                        },
                    }"
                    data-form-id="{{ $formId }}" data-required-quantity="{{ (float) $item->physical_quantity }}"
                    class="divide-y divide-slate-200/80 border-t-2 border-slate-200 bg-white"
                >
                    <template x-teleport="body">
                        <div x-cloak x-show="toastMessage" x-transition class="fixed right-6 top-6 z-[90] rounded-xl border bg-white px-4 py-3 text-sm font-semibold shadow-xl" :class="toastError ? 'border-rose-200 text-rose-700' : 'border-emerald-200 text-emerald-700'" x-text="toastMessage"></div>
                    </template>
                    @forelse ($batches as $index => $batch)
                        @php($savedSelection = $selectionMap->get((string) $batch->batch_number))
                        <tr class="align-middle">
                            <td class="px-3 py-1.5 font-semibold text-slate-700">{{ $item->medicine?->code ?: '-' }}</td>
                            <td class="px-2.5 py-1.5 font-semibold text-slate-900">{{ $item->medicine?->name ?: '-' }}</td>
                            <td class="px-2.5 py-1.5 font-semibold text-slate-900">{{ $batch->batch_number ?: '-' }}</td>
                            <td class="px-2.5 py-1.5 text-slate-600">{{ $batch->location_label }}</td>
                            <td class="px-2.5 py-1.5 text-center font-semibold">{{ number_format((float) $batch->quantity_balance, 0, ',', '.') }}</td>
                            <td class="px-2.5 py-1.5 text-center">
                                <input form="{{ $formId }}" type="hidden" name="batches[{{ $index }}][batch_number]" value="{{ $batch->batch_number }}">
                                <input form="{{ $formId }}" type="number" min="0" step="0.01" name="batches[{{ $index }}][quantity]"
                                    value="{{ $quantityValue($fieldValue('batches.'.$index.'.quantity', $savedSelection?->quantity)) }}"
                                    data-physical-input data-adjustment-physical-input data-batch-name="{{ $batch->batch_number }}"
                                    @disabled($item->followUp?->status === 'applied')
                                    @input="autoSave(@js($batch->batch_number))" @change="submitRow(@js($batch->batch_number))" @blur="submitRow(@js($batch->batch_number))" @keydown.enter.prevent="focusNext($event)"
                                    :class="saved ? 'border-emerald-300 bg-emerald-50/40' : ''"
                                    class="ui-control number-input-no-spinner mx-auto h-8 w-24 px-2 text-center text-[0.72rem]">
                            </td>
                            <td class="px-2.5 py-1.5 align-middle">
                                <div class="flex min-h-9 items-center gap-2">
                                <select x-model="settlementType" @change="submitRow('jenis penyesuaian')" @disabled($item->followUp?->status === 'applied') class="ui-select-control h-9 w-44 px-3 text-[0.78rem] font-medium text-slate-700">
                                    @if ($differenceType === 'loss')
                                        <option value="writeoff">Hilang biasa</option>
                                        @if ($item->followUp?->settlement_type === 'replace_goods')
                                            <option value="replace_goods">Ganti barang</option>
                                        @endif
                                        @if ($item->followUp?->settlement_type === 'replace_cash')
                                            <option value="replace_cash">Ganti uang</option>
                                        @endif
                                    @else
                                        <option value="stock_found">Stok lebih ditemukan</option>
                                    @endif
                                </select>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Batch obat {{ $item->medicine?->name }} tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            @empty
                <tbody><tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Tidak ada item selisih pada dokumen ini.</td></tr></tbody>
            @endforelse
        </table>
    </div>
</section>
