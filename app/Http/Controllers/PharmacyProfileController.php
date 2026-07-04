<?php

namespace App\Http\Controllers;

use App\Http\Requests\PharmacyProfileRequest;
use App\Models\PharmacyProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PharmacyProfileController extends Controller
{
    /**
     * Display the pharmacy profile settings page.
     */
    public function edit(): View
    {
        $profile = $this->currentProfile();

        return view('settings.pharmacy-profile', [
            ...$this->pageData(),
            'profile' => $profile,
        ]);
    }

    /**
     * Update the active pharmacy profile.
     */
    public function update(PharmacyProfileRequest $request): RedirectResponse
    {
        $profile = $this->currentProfile();
        $validated = $request->validated();

        $profile->update([
            'name' => $validated['name'],
            'owner_name' => $validated['owner_name'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'city' => $validated['city'] ?: null,
            'province' => $validated['province'] ?: null,
            'postal_code' => $validated['postal_code'] ?: null,
            'tax_number' => $validated['tax_number'] ?: null,
            'license_number' => $validated['license_number'] ?: null,
            'address' => $validated['address'] ?: null,
            'invoice_footer' => $validated['invoice_footer'] ?: null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('pengaturan.profil-apotik')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Profil apotik berhasil diperbarui.',
            ]);
    }

    /**
     * Build the page metadata for the settings module.
     *
     * @return array<string, mixed>
     */
    private function pageData(): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Pengaturan');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', 'pengaturan.profil-apotik');

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Pengaturan',
            'siblings' => $siblings,
        ];
    }

    /**
     * Resolve the current pharmacy profile or create the default one.
     */
    private function currentProfile(): PharmacyProfile
    {
        return PharmacyProfile::query()->active()->latest('id')->first()
            ?? PharmacyProfile::query()->latest('id')->first()
            ?? PharmacyProfile::query()->create([
                'name' => 'Apotik',
                'owner_name' => null,
                'phone' => null,
                'email' => null,
                'city' => null,
                'province' => null,
                'postal_code' => null,
                'tax_number' => null,
                'license_number' => null,
                'address' => null,
                'invoice_footer' => 'Terima kasih telah berbelanja.',
                'is_active' => true,
            ]);
    }
}
