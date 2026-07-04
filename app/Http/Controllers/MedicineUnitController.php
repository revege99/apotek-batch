<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicineUnitRequest;
use App\Models\MedicineUnit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MedicineUnitController extends Controller
{
    /**
     * Display the medicine unit master page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status', 'all'));
        $unitType = trim((string) $request->string('unit_type', 'all'));
        $editId = $request->integer('edit') ?: (int) $request->session()->getOldInput('_edit_id');

        $items = MedicineUnit::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(
                in_array($unitType, $this->typeOptions()->keys()->all(), true),
                fn ($query) => $query->forUnitType($unitType)
            )
            ->orderBy('unit_type')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('medicine-units.index', [
            ...$this->pageData(),
            'items' => $items,
            'search' => $search,
            'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            'unitType' => in_array($unitType, array_merge(['all'], $this->typeOptions()->keys()->all()), true) ? $unitType : 'all',
            'typeOptions' => $this->typeOptions(),
            'editingUnit' => $editId ? MedicineUnit::query()->find($editId) : null,
            'stats' => [
                'total' => MedicineUnit::count(),
                'active' => MedicineUnit::where('is_active', true)->count(),
                'large' => MedicineUnit::forUnitType(MedicineUnit::TYPE_LARGE)->count(),
                'small' => MedicineUnit::forUnitType(MedicineUnit::TYPE_SMALL)->count(),
            ],
        ]);
    }

    /**
     * Store a newly created unit lookup.
     */
    public function store(MedicineUnitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        MedicineUnit::query()->create([
            'code' => $this->nextCode($validated['unit_type']),
            'unit_type' => $validated['unit_type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('master-data.satuan-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Master satuan obat berhasil ditambahkan.',
            ]);
    }

    /**
     * Update the specified unit lookup.
     */
    public function update(MedicineUnitRequest $request, MedicineUnit $medicineUnit): RedirectResponse
    {
        $validated = $request->validated();

        $medicineUnit->update([
            'unit_type' => $validated['unit_type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('master-data.satuan-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Master satuan obat berhasil diperbarui.',
            ]);
    }

    /**
     * Remove the specified unit lookup.
     */
    public function destroy(MedicineUnit $medicineUnit): RedirectResponse
    {
        $medicineUnit->delete();

        return redirect()
            ->route('master-data.satuan-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Master satuan obat berhasil dihapus.',
            ]);
    }

    /**
     * Get page metadata.
     *
     * @return array<string, mixed>
     */
    private function pageData(): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Master Data');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', 'master-data.satuan-obat');

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Master Data',
            'siblings' => $siblings,
        ];
    }

    /**
     * Get the available unit types.
     *
     * @return \Illuminate\Support\Collection<string, string>
     */
    private function typeOptions(): Collection
    {
        return collect([
            MedicineUnit::TYPE_LARGE => 'Satuan Besar',
            MedicineUnit::TYPE_SMALL => 'Satuan Kecil',
        ]);
    }

    /**
     * Generate the next code for the given type.
     */
    private function nextCode(string $unitType): string
    {
        $prefixes = [
            MedicineUnit::TYPE_LARGE => 'SBR',
            MedicineUnit::TYPE_SMALL => 'SKC',
        ];

        $prefix = $prefixes[$unitType] ?? 'SAT';
        $nextNumber = MedicineUnit::query()
            ->forUnitType($unitType)
            ->pluck('code')
            ->map(function (?string $code): int {
                if (! is_string($code) || ! preg_match('/(\d+)$/', $code, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() + 1;

        do {
            $candidate = sprintf('%s%04d', $prefix, $nextNumber);
            $nextNumber++;
        } while (MedicineUnit::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
