<?php

namespace App\Http\Controllers;

use App\Models\StockOpname;
use App\Models\StockAdjustmentRecovery;
use App\Models\StockAdjustmentRecoveryPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class InternalBillingController extends Controller
{
    /**
     * Display internal stock-adjustment billing history.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'all'));
        $status = in_array($status, ['all', 'unpaid', 'partial', 'paid'], true) ? $status : 'all';
        $documentRows = $this->documentRows($search, $status);
        $rows = $this->paginateDocumentRows($documentRows, $request, 12);

        return view('internal-billings.index', [
            ...$this->pageData('keuangan.riwayat-tagihan-internal'),
            'rows' => $rows,
            'search' => $search,
            'status' => $status,
            'detailPayloads' => $this->detailPayloads($rows),
            'reopenDetailKey' => session('reopen_internal_billing_detail'),
            'todayDate' => now()->toDateString(),
            'stats' => [
                'total' => $documentRows->count(),
                'outstanding_total' => (float) $documentRows->sum('outstanding_amount_value'),
                'paid_total' => (float) $documentRows->sum('paid_amount_value'),
            ],
        ]);
    }

    /**
     * Store one internal-billing payment at document level and split it across item recoveries.
     */
    public function storeDocumentPayment(Request $request, StockOpname $stockOpname): RedirectResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'payment_date' => ['required', 'date'],
                'payment_method' => ['required', 'in:cash,transfer,qris,debit'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'reference_number' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            [
                'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
                'payment_method.required' => 'Pilih metode pembayaran terlebih dahulu.',
                'amount.required' => 'Nominal pembayaran wajib diisi.',
                'amount.min' => 'Nominal pembayaran harus lebih besar dari nol.',
            ],
        );

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('reopen_internal_billing_detail', 'opname:'.$stockOpname->id)
                ->with('toast', [
                    'type' => 'error',
                    'message' => $validator->errors()->first(),
                ]);
        }

        $validated = $validator->validated();

        try {
            $paymentCount = DB::transaction(function () use ($request, $stockOpname, $validated): int {
                $recoveries = StockAdjustmentRecovery::query()
                    ->whereHas('followUp.opnameItem', fn (Builder $query) => $query->where('stock_opname_id', $stockOpname->id))
                    ->with('followUp:id,stock_opname_item_id')
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();

                $targets = $recoveries
                    ->map(function (StockAdjustmentRecovery $recovery): array {
                        $outstanding = max(round((float) $recovery->replacement_amount - (float) $recovery->paid_amount, 2), 0);

                        return [
                            'recovery' => $recovery,
                            'outstanding' => $outstanding,
                        ];
                    })
                    ->filter(fn (array $row): bool => (float) $row['outstanding'] > 0.001)
                    ->values();

                if ($targets->isEmpty()) {
                    throw new RuntimeException('Semua tagihan internal pada dokumen ini sudah lunas.');
                }

                $totalOutstanding = round((float) $targets->sum('outstanding'), 2);
                $amount = round((float) $validated['amount'], 2);

                if ($amount > $totalOutstanding + 0.001) {
                    throw new RuntimeException('Nominal pembayaran melebihi sisa tagihan internal dokumen ini.');
                }

                $remaining = $amount;
                $createdCount = 0;

                foreach ($targets as $target) {
                    if ($remaining <= 0.001) {
                        break;
                    }

                    /** @var StockAdjustmentRecovery $recovery */
                    $recovery = $target['recovery'];
                    $outstanding = round((float) $target['outstanding'], 2);
                    $allocated = round(min($remaining, $outstanding), 2);

                    if ($allocated <= 0.001) {
                        continue;
                    }

                    StockAdjustmentRecoveryPayment::query()->create([
                        'stock_adjustment_recovery_id' => $recovery->id,
                        'payment_number' => $this->nextPaymentNumber(),
                        'payment_date' => $validated['payment_date'],
                        'payment_method' => (string) $validated['payment_method'],
                        'reference_number' => filled($validated['reference_number'] ?? null) ? trim((string) $validated['reference_number']) : null,
                        'amount_paid' => $allocated,
                        'notes' => filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
                        'created_by' => $request->user()?->id,
                    ]);

                    $newPaidAmount = round((float) $recovery->paid_amount + $allocated, 2);

                    $recovery->update([
                        'paid_amount' => $newPaidAmount,
                        'paid_at' => $validated['payment_date'],
                        'status' => $this->resolveRecoveryStatus((float) $recovery->replacement_amount, $newPaidAmount),
                    ]);

                    $remaining = round($remaining - $allocated, 2);
                    $createdCount++;
                }

                return $createdCount;
            });
        } catch (RuntimeException $exception) {
            return back()
                ->with('reopen_internal_billing_detail', 'opname:'.$stockOpname->id)
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return back()
            ->with('reopen_internal_billing_detail', 'opname:'.$stockOpname->id)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pembayaran tagihan internal dokumen '.$stockOpname->opname_number.' berhasil disimpan dan dibagi ke '.$paymentCount.' item.',
            ]);
    }

    /**
     * Store one internal-billing payment.
     */
    public function storePayment(Request $request, StockAdjustmentRecovery $stockAdjustmentRecovery): RedirectResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'payment_date' => ['required', 'date'],
                'payment_method' => ['required', 'in:cash,transfer,qris,debit'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'reference_number' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            [
                'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
                'payment_method.required' => 'Pilih metode pembayaran terlebih dahulu.',
                'amount.required' => 'Nominal pembayaran wajib diisi.',
                'amount.min' => 'Nominal pembayaran harus lebih besar dari nol.',
            ],
        );

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('reopen_internal_billing_detail', $this->detailKeyFromRecovery($stockAdjustmentRecovery))
                ->with('toast', [
                    'type' => 'error',
                    'message' => $validator->errors()->first(),
                ]);
        }

        $validated = $validator->validated();

        try {
            $payment = DB::transaction(function () use ($request, $stockAdjustmentRecovery, $validated): StockAdjustmentRecoveryPayment {
                $lockedRecovery = StockAdjustmentRecovery::query()
                    ->lockForUpdate()
                    ->findOrFail($stockAdjustmentRecovery->id);

                $outstandingAmount = max(round((float) $lockedRecovery->replacement_amount - (float) $lockedRecovery->paid_amount, 2), 0);

                if ($outstandingAmount <= 0.001) {
                    throw new RuntimeException('Tagihan internal ini sudah lunas.');
                }

                $amount = round((float) $validated['amount'], 2);

                if ($amount > $outstandingAmount + 0.001) {
                    throw new RuntimeException('Nominal pembayaran melebihi sisa tagihan internal.');
                }

                $payment = StockAdjustmentRecoveryPayment::query()->create([
                    'stock_adjustment_recovery_id' => $lockedRecovery->id,
                    'payment_number' => $this->nextPaymentNumber(),
                    'payment_date' => $validated['payment_date'],
                    'payment_method' => (string) $validated['payment_method'],
                    'reference_number' => filled($validated['reference_number'] ?? null) ? trim((string) $validated['reference_number']) : null,
                    'amount_paid' => $amount,
                    'notes' => filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
                    'created_by' => $request->user()?->id,
                ]);

                $newPaidAmount = round((float) $lockedRecovery->paid_amount + $amount, 2);
                $status = $this->resolveRecoveryStatus((float) $lockedRecovery->replacement_amount, $newPaidAmount);

                $lockedRecovery->update([
                    'paid_amount' => $newPaidAmount,
                    'paid_at' => $validated['payment_date'],
                    'status' => $status,
                ]);

                return $payment;
            });
        } catch (RuntimeException $exception) {
            return back()
                ->with('reopen_internal_billing_detail', $this->detailKeyFromRecovery($stockAdjustmentRecovery))
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return back()
            ->with('reopen_internal_billing_detail', $this->detailKeyFromRecovery($stockAdjustmentRecovery))
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pembayaran tagihan internal '.$payment->payment_number.' berhasil disimpan.',
            ]);
    }

    /**
     * Delete one internal-billing payment and restore the outstanding balance.
     */
    public function destroyPayment(StockAdjustmentRecoveryPayment $stockAdjustmentRecoveryPayment): RedirectResponse
    {
        $paymentNumber = $stockAdjustmentRecoveryPayment->payment_number;
        $stockAdjustmentRecoveryPayment->load('recovery.followUp.opnameItem.stockOpname');
        $reopenDetailKey = $stockAdjustmentRecoveryPayment->recovery
            ? $this->detailKeyFromRecovery($stockAdjustmentRecoveryPayment->recovery)
            : null;

        try {
            DB::transaction(function () use ($stockAdjustmentRecoveryPayment): void {
                $lockedPayment = StockAdjustmentRecoveryPayment::query()
                    ->lockForUpdate()
                    ->findOrFail($stockAdjustmentRecoveryPayment->id);

                $lockedRecovery = StockAdjustmentRecovery::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedPayment->stock_adjustment_recovery_id);

                $newPaidAmount = round((float) $lockedRecovery->paid_amount - (float) $lockedPayment->amount_paid, 2);

                if ($newPaidAmount < -0.001) {
                    throw new RuntimeException('Saldo pembayaran tagihan internal sudah tidak sinkron, jadi pembayaran belum bisa dihapus.');
                }

                $status = $this->resolveRecoveryStatus((float) $lockedRecovery->replacement_amount, max($newPaidAmount, 0));

                $lockedRecovery->update([
                    'paid_amount' => max($newPaidAmount, 0),
                    'paid_at' => max($newPaidAmount, 0) > 0.001
                        ? StockAdjustmentRecoveryPayment::query()
                            ->where('stock_adjustment_recovery_id', $lockedRecovery->id)
                            ->where('id', '!=', $lockedPayment->id)
                            ->latest('payment_date')
                            ->latest('id')
                            ->value('payment_date')
                        : null,
                    'status' => $status,
                ]);

                $lockedPayment->delete();
            });
        } catch (RuntimeException $exception) {
            return back()
                ->with('reopen_internal_billing_detail', $reopenDetailKey)
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return back()
            ->with('reopen_internal_billing_detail', $reopenDetailKey)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pembayaran tagihan internal '.$paymentNumber.' berhasil dihapus.',
            ]);
    }

    /**
     * Build the base internal-billing query.
     */
    private function recoveryBaseQuery(string $search, string $status): Builder
    {
        return StockAdjustmentRecovery::query()
            ->with([
                'followUp:id,stock_opname_item_id,adjustment_number,status',
                'followUp.opnameItem:id,stock_opname_id,medicine_id',
                'followUp.opnameItem.medicine:id,code,name',
                'followUp.opnameItem.stockOpname:id,opname_number,opname_date',
                'payments:id,stock_adjustment_recovery_id,payment_number,payment_date,payment_method,amount_paid',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $recoveryQuery) use ($search) {
                    $recoveryQuery
                        ->where('employee_name', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('followUp', fn (Builder $followUpQuery) => $followUpQuery->where('adjustment_number', 'like', "%{$search}%"))
                        ->orWhereHas('followUp.opnameItem.medicine', fn (Builder $medicineQuery) => $medicineQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status));
    }

    /**
     * Build document-level internal billing rows grouped by stock opname.
     */
    private function documentRows(string $search, string $status): \Illuminate\Support\Collection
    {
        $recoveries = StockAdjustmentRecovery::query()
            ->with([
                'followUp:id,stock_opname_item_id,adjustment_number,status,adjustment_date',
                'followUp.opnameItem:id,stock_opname_id,medicine_id',
                'followUp.opnameItem.medicine:id,code,name',
                'followUp.opnameItem.stockOpname:id,opname_number,opname_date',
                'payments:id,stock_adjustment_recovery_id,payment_number,payment_date,payment_method,amount_paid',
            ])
            ->latest('id')
            ->get();

        return $recoveries
            ->groupBy(function (StockAdjustmentRecovery $recovery): string {
                $stockOpnameId = $recovery->followUp?->opnameItem?->stockOpname?->id;

                return $stockOpnameId ? 'opname:'.$stockOpnameId : 'recovery:'.$recovery->id;
            })
            ->map(function (\Illuminate\Support\Collection $group, string $key): object {
                /** @var StockAdjustmentRecovery $first */
                $first = $group->first();
                $stockOpname = $first->followUp?->opnameItem?->stockOpname;
                $employeeNames = $group->pluck('employee_name')->filter()->unique()->values();
                $adjustmentNumbers = $group
                    ->map(fn (StockAdjustmentRecovery $recovery): ?string => $recovery->followUp?->adjustment_number)
                    ->filter()
                    ->unique()
                    ->values();

                $replacementAmount = round((float) $group->sum(fn (StockAdjustmentRecovery $recovery): float => (float) $recovery->replacement_amount), 2);
                $paidAmount = round((float) $group->sum(fn (StockAdjustmentRecovery $recovery): float => (float) $recovery->paid_amount), 2);
                $outstandingAmount = max(round($replacementAmount - $paidAmount, 2), 0);
                $statusValue = $this->resolveRecoveryStatus($replacementAmount, $paidAmount);

                return (object) [
                    'key' => $key,
                    'stock_opname_id' => $stockOpname?->id,
                    'opname_number' => $stockOpname?->opname_number ?: ($first->followUp?->adjustment_number ?: '-'),
                    'opname_date' => $stockOpname?->opname_date,
                    'employee_names' => $employeeNames->implode(', ') ?: '-',
                    'employee_names_array' => $employeeNames->all(),
                    'adjustment_numbers' => $adjustmentNumbers->all(),
                    'item_count' => $group->count(),
                    'replacement_amount_value' => $replacementAmount,
                    'paid_amount_value' => $paidAmount,
                    'outstanding_amount_value' => $outstandingAmount,
                    'replacement_amount' => $this->formatCurrency($replacementAmount),
                    'paid_amount' => $this->formatCurrency($paidAmount),
                    'outstanding_amount' => $this->formatCurrency($outstandingAmount),
                    'status' => $statusValue,
                    'group' => $group->values(),
                ];
            })
            ->filter(function (object $row) use ($search, $status): bool {
                $statusMatch = $status === 'all' || $row->status === $status;

                if (! $statusMatch) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', array_filter([
                    (string) $row->opname_number,
                    implode(' ', $row->employee_names_array),
                    implode(' ', $row->adjustment_numbers),
                    $row->group->map(fn (StockAdjustmentRecovery $recovery): string => (string) ($recovery->followUp?->opnameItem?->medicine?->name ?? ''))->implode(' '),
                    $row->group->map(fn (StockAdjustmentRecovery $recovery): string => (string) ($recovery->followUp?->opnameItem?->medicine?->code ?? ''))->implode(' '),
                ])));

                return str_contains($haystack, mb_strtolower($search));
            })
            ->sortByDesc(function (object $row): int {
                $timestamp = $row->opname_date?->getTimestamp();

                return is_int($timestamp) ? $timestamp : 0;
            })
            ->values();
    }

    /**
     * Build modal/detail payloads per document row.
     *
     * @param  LengthAwarePaginator<int, object>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function detailPayloads(LengthAwarePaginator $rows): array
    {
        return $rows->getCollection()
            ->mapWithKeys(function (object $row): array {
                $payments = $row->group
                    ->flatMap(fn (StockAdjustmentRecovery $recovery) => $recovery->payments->map(function (StockAdjustmentRecoveryPayment $payment) use ($recovery): array {
                        return [
                            'id' => $payment->id,
                            'payment_number' => $payment->payment_number,
                            'payment_date' => $payment->payment_date?->translatedFormat('d M Y') ?? '-',
                            'payment_method' => strtoupper((string) $payment->payment_method),
                            'amount_paid' => $this->formatCurrency((float) $payment->amount_paid),
                            'adjustment_number' => $recovery->followUp?->adjustment_number ?: '-',
                            'medicine_name' => $recovery->followUp?->opnameItem?->medicine?->name ?: '-',
                            'delete_url' => route('keuangan.riwayat-tagihan-internal.destroy-payment', $payment),
                        ];
                    }))
                    ->sortByDesc('payment_date')
                    ->values();

                $items = $row->group
                    ->map(function (StockAdjustmentRecovery $recovery): array {
                        $outstanding = max(round((float) $recovery->replacement_amount - (float) $recovery->paid_amount, 2), 0);

                        return [
                            'recovery_id' => $recovery->id,
                            'adjustment_number' => $recovery->followUp?->adjustment_number ?: '-',
                            'medicine_name' => $recovery->followUp?->opnameItem?->medicine?->name ?: '-',
                            'medicine_code' => $recovery->followUp?->opnameItem?->medicine?->code ?: '-',
                            'replacement_amount' => $this->formatCurrency((float) $recovery->replacement_amount),
                            'paid_amount' => $this->formatCurrency((float) $recovery->paid_amount),
                            'outstanding_value' => $outstanding,
                            'outstanding_amount' => $this->formatCurrency($outstanding),
                            'status_label' => $this->recoveryStatusLabel((string) $recovery->status),
                            'status_class' => $this->recoveryStatusClass((string) $recovery->status),
                            'payment_target' => [
                                'id' => $recovery->id,
                                'action' => route('keuangan.riwayat-tagihan-internal.bayar', $recovery),
                                'employee_name' => $recovery->employee_name ?: '-',
                                'adjustment_number' => $recovery->followUp?->adjustment_number ?: '-',
                                'medicine_name' => $recovery->followUp?->opnameItem?->medicine?->name ?: '-',
                                'outstanding_value' => $outstanding,
                                'outstanding_label' => $this->formatCurrency($outstanding),
                            ],
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    $row->key => [
                        'key' => $row->key,
                        'employee_name' => $row->employee_names,
                        'opname_number' => $row->opname_number,
                        'opname_date' => $row->opname_date?->translatedFormat('d M Y') ?? '-',
                        'replacement_amount' => $row->replacement_amount,
                        'paid_amount' => $row->paid_amount,
                        'outstanding_amount' => $row->outstanding_amount,
                        'status_label' => $this->recoveryStatusLabel((string) $row->status),
                        'status_class' => $this->recoveryStatusClass((string) $row->status),
                        'notes' => $row->group->pluck('notes')->filter()->implode(' | ') ?: '-',
                        'items' => $items,
                        'payments' => $payments->all(),
                        'payment_target' => [
                            'id' => $row->stock_opname_id,
                            'action' => $row->stock_opname_id ? route('keuangan.riwayat-tagihan-internal.bayar-dokumen', $row->stock_opname_id) : '',
                            'employee_name' => $row->employee_names,
                            'adjustment_number' => $row->opname_number,
                            'medicine_name' => number_format((int) $row->item_count).' item tagihan',
                            'outstanding_value' => $row->outstanding_amount_value,
                            'outstanding_label' => $row->outstanding_amount,
                        ],
                    ],
                ];
            })
            ->all();
    }

    /**
     * Paginate grouped document rows.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function paginateDocumentRows(\Illuminate\Support\Collection $rows, Request $request, int $perPage = 12): LengthAwarePaginator
    {
        $currentPage = max((int) $request->query('page', 1), 1);
        $items = $rows->forPage($currentPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function nextPaymentNumber(): string
    {
        $prefix = 'TI-'.now()->format('Ymd').'-';
        $latestTodayNumber = StockAdjustmentRecoveryPayment::query()
            ->where('payment_number', 'like', $prefix.'%')
            ->latest('id')
            ->value('payment_number');

        if (! is_string($latestTodayNumber) || ! str_starts_with($latestTodayNumber, $prefix)) {
            return $prefix.'001';
        }

        $lastSequence = (int) substr($latestTodayNumber, -3);

        return $prefix.str_pad((string) ($lastSequence + 1), 3, '0', STR_PAD_LEFT);
    }

    private function resolveRecoveryStatus(float $replacementAmount, float $paidAmount): string
    {
        if ($paidAmount <= 0.001) {
            return 'unpaid';
        }

        if ($paidAmount + 0.001 < $replacementAmount) {
            return 'partial';
        }

        return 'paid';
    }

    private function pageData(string $routeName): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Keuangan');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Keuangan',
            'siblings' => $siblings,
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function recoveryStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Lunas',
            'partial' => 'Sebagian',
            default => 'Belum Lunas',
        };
    }

    private function recoveryStatusClass(string $status): string
    {
        return match ($status) {
            'paid' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
            'partial' => 'border-sky-100 bg-sky-50 text-sky-700',
            default => 'border-amber-100 bg-amber-50 text-amber-700',
        };
    }

    private function detailKeyFromRecovery(StockAdjustmentRecovery $recovery): string
    {
        $stockOpnameId = $recovery->followUp?->opnameItem?->stockOpname?->id;

        return $stockOpnameId ? 'opname:'.$stockOpnameId : 'recovery:'.$recovery->id;
    }
}
