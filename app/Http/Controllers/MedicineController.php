<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicineRequest;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineUnit;
use App\Models\Principal;
use Illuminate\Contracts\View\View;
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
        $formOptions = $this->formOptions();
        $newMedicine = $this->newMedicine();

        $medicines = Medicine::query()
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
            ->get();

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
            'editFormOptions' => $editingMedicine ? $this->formOptions($editingMedicine->principal_id) : null,
            'selectedPrincipalId' => null,
            'stats' => [
                'total' => Medicine::count(),
                'active' => Medicine::where('is_active', true)->count(),
                'principal_count' => Medicine::query()->whereNotNull('principal_id')->distinct('principal_id')->count('principal_id'),
                'composition_count' => Medicine::query()->whereNotNull('composition')->where('composition', '!=', '')->count(),
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

        $medicine->delete();

        return redirect()
            ->route('master-data.data-obat')
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
        return [
            'principalOptions' => $this->principalOptions($selectedPrincipalId),
            'typeSuggestions' => $this->medicineCategorySuggestions(MedicineCategory::TYPE_MEDICINE_TYPE, 'medicine_type'),
            'categorySuggestions' => $this->medicineCategorySuggestions(MedicineCategory::TYPE_CATEGORY, 'category_name'),
            'groupSuggestions' => $this->medicineCategorySuggestions(MedicineCategory::TYPE_GROUP, 'medicine_group'),
            'largeUnitSuggestions' => $this->medicineUnitSuggestions(MedicineUnit::TYPE_LARGE, 'large_unit'),
            'smallUnitSuggestions' => $this->medicineUnitSuggestions(MedicineUnit::TYPE_SMALL, 'small_unit'),
        ];
    }

    /**
     * Get medicine classifications from master data, with existing values as fallback.
     */
    private function medicineCategorySuggestions(string $classificationType, string $fallbackField): Collection
    {
        return MedicineCategory::query()
            ->forClassificationType($classificationType)
            ->active()
            ->orderBy('name')
            ->pluck('name')
            ->merge($this->medicineFieldSuggestions($fallbackField))
            ->filter()
            ->unique(fn (string $value): string => Str::lower($value))
            ->values();
    }

    /**
     * Get medicine units from master data, with existing values as fallback.
     */
    private function medicineUnitSuggestions(string $unitType, string $fallbackField): Collection
    {
        return MedicineUnit::query()
            ->forUnitType($unitType)
            ->active()
            ->orderBy('name')
            ->pluck('name')
            ->merge($this->medicineFieldSuggestions($fallbackField))
            ->filter()
            ->unique(fn (string $value): string => Str::lower($value))
            ->values();
    }

    /**
     * Get a distinct suggestion list from an existing medicine field.
     */
    private function medicineFieldSuggestions(string $field): Collection
    {
        return Medicine::query()
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->distinct()
            ->orderBy($field)
            ->limit(20)
            ->pluck($field);
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
        $nextNumber = Medicine::query()
            ->pluck('code')
            ->map(function (?string $code): int {
                if (! is_string($code)) {
                    return 0;
                }

                if (! preg_match('/^oba(\d+)$/i', $code, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() + 1;

        do {
            $candidate = sprintf('oba%07d', $nextNumber);
            $nextNumber++;
        } while (Medicine::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
