<?php

namespace App\Http\Middleware;

use App\Models\PharmacyProfile;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperationalLicenseIsActive
{
    /**
     * Ensure the current pharmacy license is still active for operational modules.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $profile = PharmacyProfile::query()->active()->latest('id')->first()
            ?? PharmacyProfile::query()->latest('id')->first();

        $expiresAt = $profile?->app_license_expires_at?->copy();

        if ($expiresAt === null || $expiresAt->lte(now())) {
            return redirect()
                ->route('pengaturan.lisensi')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Lisensi berakhir, silahkan lakukan perpanjangan lisensi di menu profile lisensi.',
                ]);
        }

        return $next($request);
    }
}
