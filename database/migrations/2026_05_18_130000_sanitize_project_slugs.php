<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Older imports built the slug as raw "owner-repo", so repos with dots
     * (e.g. "apexcharts.js") produced slugs like "apexcharts-apexcharts.js"
     * that break route key matching. Re-slugify every project so the slug
     * matches the importer's current Str::slug output, skipping rows whose
     * sanitized value would collide with an existing slug.
     */
    public function up(): void
    {
        $rows = DB::table('projects')->select('id', 'slug')->get();

        $taken = $rows->pluck('slug')->all();
        $taken = array_combine($taken, $taken);

        foreach ($rows as $row) {
            $clean = Str::slug((string) $row->slug);

            if ($clean === '' || $clean === $row->slug) {
                continue;
            }

            if (isset($taken[$clean])) {
                continue;
            }

            DB::table('projects')->where('id', $row->id)->update(['slug' => $clean]);

            unset($taken[$row->slug]);
            $taken[$clean] = $clean;
        }
    }

    public function down(): void {}
};
