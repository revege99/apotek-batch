<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
        });

        $taken = [];

        DB::table('users')
            ->orderBy('id')
            ->select('id', 'name', 'email')
            ->get()
            ->each(function ($user) use (&$taken): void {
                $base = Str::of((string) ($user->email ?: $user->name))
                    ->before('@')
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '')
                    ->value();

                if ($base === '') {
                    $base = 'user';
                }

                $candidate = $base;
                $suffix = 1;

                while (in_array($candidate, $taken, true) || DB::table('users')->where('username', $candidate)->where('id', '!=', $user->id)->exists()) {
                    $candidate = $base.$suffix;
                    $suffix++;
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $candidate]);

                $taken[] = $candidate;
            });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
