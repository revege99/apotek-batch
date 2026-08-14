<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicineRequest;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineUnit;
use App\Models\Principal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MedicineController extends Controller
{
    /**
     * Display a listing of medicines.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status', 'all'));
        $editId = $request->integer('edit') ?: (int) $request->session()->getOldInput('_edit_id');
        $editingMedicine = $editId ? Medicine::query()->with('principal:id,name')->find($editId) : null;
        $formOptions = $this->formOptions($editingMedicine?->principal_id);
        $newMedicine = $this->newMedicine();

        $medicines = Medicine::query()
            ->select([
                'id',
                'code',
                'name',
                'medicine_type',
                'category_name',
                'medicine_group',
                'large_unit',
                'small_unit',
                'small_unit_per_large_unit',
                'minimum_stock',
                'composition',
                'purchase_price',
                'principal_id',
                'is_active',
                'created_at',
            ])
            ->with('principal:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('medicine_type', 'like', "%{$search}%")
                        ->orWhere('category_name', 'like', "%{$search}%")
                        ->orWhere('medicine_group', 'like', "%{$search}%")
                        ->orWhere('large_unit', 'like', "%{$search}%")
                        ->orWhere('small_unit', 'like', "%{$search}%")
                        ->orWhere('composition', 'like', "%{$search}%")
                        ->orWhereHas('principal', fn ($principalQuery) => $principalQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $stats = Medicine::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('COUNT(DISTINCT CASE WHEN principal_id IS NOT NULL THEN principal_id END) as principal_count')
            ->selectRaw("SUM(CASE WHEN composition IS NOT NULL AND composition != '' THEN 1 ELSE 0 END) as composition_count")
            ->first();

        return view('medicines.index', [
            ...$this->pageData(),
            'medicines' => $medicines,
            'search' => $search,
            'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            'principalOptions' => $formOptions['principalOptions'],
            'typeSuggestions' => $formOptions['typeSuggestions'],
            'categorySuggestions' => $formOptions['categorySuggestions'],
            'groupSuggestions' => $formOptions['groupSuggestions'],
            'largeUnitSuggestions' => $formOptions['largeUnitSuggestions'],
            'smallUnitSuggestions' => $formOptions['smallUnitSuggestions'],
            'newMedicine' => $newMedicine,
            'editingMedicine' => $editingMedicine,
            'editFormOptions' => $editingMedicine ? $formOptions : null,
            'selectedPrincipalId' => null,
            'stats' => [
                'total' => (int) ($stats?->total ?? 0),
                'active' => (int) ($stats?->active ?? 0),
                'principal_count' => (int) ($stats?->principal_count ?? 0),
                'composition_count' => (int) ($stats?->composition_count ?? 0),
            ],
        ]);
    }

    /**
     * Show the form for creating a new medicine.
     */
    public function create(): View
    {
        return view('medicines.create', [
            ...$this->pageData(),
            'medicine' => $this->newMedicine(),
            ...$this->formOptions(),
            'selectedPrincipalId' => null,
        ]);
    }

    /**
     * Store a newly created medicine in storage.
     */
    public function store(MedicineRequest $request): RedirectResponse
    {
        Medicine::create($this->payload($request));

        return redirect()
            ->route('master-data.data-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data obat berhasil ditambahkan.',
            ]);
    }

    /**
     * Show the form for editing the specified medicine.
     */
    public function edit(Medicine $medicine): RedirectResponse
    {
        return redirect()->route('master-data.data-obat', [
            'edit' => $medicine->id,
        ]);
    }

    /**
     * Update the specified medicine in storage.
     */
    public function update(MedicineRequest $request, Medicine $medicine): RedirectResponse
    {
        $medicine->update($this->payload($request));

        return redirect()
            ->route('master-data.data-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data obat berhasil diperbarui.',
            ]);
    }

    /**
     * Remove the specified medicine from storage.
     */
    public function destroy(Medicine $medicine): RedirectResponse
    {
        $medicineName = $medicine->name;

        try {
            $medicine->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '23000') {
                throw $exception;
            }

            return back()->with('toast', [
                'type' => 'error',
                'message' => "Obat {$medicineName} belum dapat dihapus karena masih ada referensi yang belum memakai snapshot.",
            ]);
        }

        return back()
            ->with('toast', [
                'type' => 'success',
                'message' => "Obat {$medicineName} berhasil dihapus.",
            ]);
    }


    /**
     * Build the page metadata for the medicine module.
     *
     * @return array<string, mixed>
     */
    private function pageData(): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Master Data');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', 'master-data.data-obat');

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Master Data',
            'siblings' => $siblings,
        ];
    }

    /**
     * Get a normalized payload for storing a medicine.
     *
     * @return array<string, mixed>
     */
    private function payload(MedicineRequest $request): array
    {
        $validated = $request->validated();

        return [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'medicine_type' => $validated['medicine_type'] ?: null,
            'category_name' => $validated['category_name'] ?: null,
            'medicine_group' => $validated['medicine_group'] ?: null,
            'large_unit' => $validated['large_unit'] ?: null,
            'small_unit' => $validated['small_unit'] ?: null,
            'small_unit_per_large_unit' => $validated['small_unit_per_large_unit'] ?? null,
            'minimum_stock' => $validated['minimum_stock'] ?? 0,
            'composition' => $validated['composition'] ?: null,
            'purchase_price' => $validated['purchase_price'] ?? 0,
            'principal_id' => $validated['principal_id'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Get active pharmaceutical-industry options for the medicine forms.
     */
    private function principalOptions(?int $selectedPrincipalId = null): Collection
    {
        return Principal::query()
            ->select(['id', 'name', 'is_active'])
            ->where(function ($query) use ($selectedPrincipalId) {
                $query->where('is_active', true);

                if ($selectedPrincipalId !== null) {
                    $query->orWhere('id', $selectedPrincipalId);
                }
            })
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * Build suggestion lists for the medicine forms.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?int $selectedPrincipalId = null): array
    {
        $categoryOptions = MedicineCategory::query()
            ->whereIn('classification_type', [
                MedicineCategory::TYPE_MEDICINE_TYPE,
                MedicineCategory::TYPE_CATEGORY,
                MedicineCategory::TYPE_GROUP,
            ])
            ->active()
            ->orderBy('name')
            ->get(['classification_type', 'name'])
            ->groupBy('classification_type');

        $unitOptions = MedicineUnit::query()
            ->active()
            ->orderBy('name')
            ->pluck('name');

        $medicineValues = Medicine::query()
            ->get(['medicine_type', 'category_name', 'medicine_group', 'large_unit', 'small_unit']);

        $unitSuggestions = $unitOptions
            ->merge($medicineValues->pluck('large_unit'))
            ->merge($medicineValues->pluck('small_unit'))
            ->filter()
            ->unique(fn (string $value): string => Str::lower($value))
            ->values();

        return [
            'principalOptions' => $this->principalOptions($selectedPrincipalId),
            'typeSuggestions' => $this->mergeSuggestions($categoryOptions->get(MedicineCategory::TYPE_MEDICINE_TYPE, collect()), $medicineValues, 'medicine_type'),
            'categorySuggestions' => $this->mergeSuggestions($categoryOptions->get(MedicineCategory::TYPE_CATEGORY, collect()), $medicineValues, 'category_name'),
            'groupSuggestions' => $this->mergeSuggestions($categoryOptions->get(MedicineCategory::TYPE_GROUP, collect()), $medicineValues, 'medicine_group'),
            'largeUnitSuggestions' => $unitSuggestions,
            'smallUnitSuggestions' => $unitSuggestions,
        ];
    }

    /**
     * Merge master values with a small set of values already used by medicines.
     */
    private function mergeSuggestions(Collection $masterOptions, Collection $medicineValues, string $field): Collection
    {
        $fallbackOptions = $medicineValues
            ->pluck($field)
            ->filter()
            ->unique(fn (string $value): string => Str::lower($value))
            ->sortBy(fn (string $value): string => Str::lower($value))
            ->take(20);

        return $masterOptions
            ->pluck('name')
            ->merge($fallbackOptions)
            ->filter()
            ->unique(fn (string $value): string => Str::lower($value))
            ->values();
    }

    /**
     * Build a default medicine instance for create flows.
     */
    private function newMedicine(): Medicine
    {
        return new Medicine([
            'code' => $this->nextMedicineCode(),
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Generate the next medicine code in the oba0000001 format.
     */
    private function nextMedicineCode(): string
    {
        $latestCode = Medicine::query()
            ->where('code', 'like', 'oba%')
            ->orderByDesc('code')
            ->value('code');

        $nextNumber = is_string($latestCode) && preg_match('/^oba(\d+)$/i', $latestCode, $matches)
            ? ((int) $matches[1]) + 1
            : 1;

        do {
            $candidate = sprintf('oba%07d', $nextNumber);
            $nextNumber++;
        } while (Medicine::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
