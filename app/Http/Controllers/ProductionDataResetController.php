<?php

namespace App\Http\Controllers;

use App\Support\ProductionDataResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionDataResetController extends Controller
{
    private const CONFIRMATION_TEXT = 'HAPUS DATA TRANSAKSI';

    public function __construct(
        private readonly ProductionDataResetService $resetService,
    ) {
    }

    /**
     * Show the superadmin cleanup page.
     */
    public function index(): View
    {
        return view('settings.production-reset', [
            ...$this->pageData('pengaturan.reset-data-produksi'),
            'summary' => $this->resetService->summary(),
            'groupedTables' => $this->resetService->groupedTables(),
            'preservedScopes' => $this->resetService->preservedScopes(),
            'confirmationText' => self::CONFIRMATION_TEXT,
            'totalRows' => collect($this->resetService->summary())->sum('rows'),
        ]);
    }

    /**
     * Delete all non-master transactional data.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confirmation_text' => ['required', 'string', Rule::in([self::CONFIRMATION_TEXT])],
            'confirmation_acknowledged' => ['required', 'accepted'],
        ], [
            'confirmation_text.in' => 'Ketik konfirmasi persis seperti yang diminta sebelum menghapus data.',
            'confirmation_acknowledged.accepted' => 'Centang persetujuan sebelum menghapus data transaksi.',
        ]);

        unset($validated);

        $deleted = $this->resetService->purge();
        $totalDeleted = array_sum($deleted);

        return redirect()
            ->route('pengaturan.reset-data-produksi')
            ->with('toast', [
                'type' => 'success',
                'message' => $totalDeleted > 0
                    ? 'Reset data transaksi selesai. '.$totalDeleted.' baris data non-master berhasil dibersihkan.'
                    : 'Reset data transaksi selesai. Tidak ada data non-master yang perlu dibersihkan.',
            ]);
    }

    /**
     * Resolve page metadata from the navigation config.
     *
     * @return array{page: array<string, mixed>, section: string}
     */
    private function pageData(string $routeName): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $group): bool => collect($group['children'] ?? [])
                ->contains(fn (array $child): bool => ($child['route'] ?? null) === $routeName));

        $page = collect($section['children'] ?? [])
            ->firstWhere('route', $routeName);

        return [
            'page' => $page ?? ['label' => 'Reset Data Produksi'],
            'section' => $section['label'] ?? 'Pengaturan',
        ];
    }
}
