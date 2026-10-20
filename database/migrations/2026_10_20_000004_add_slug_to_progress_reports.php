<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link legible del corte del seguimiento: /seguimiento/2026-10-12 en vez de /seguimiento/1 (pedido del
 * usuario 2026-10-20). Se llena para los cortes que ya existen. Solo aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->string('slug', 40)->nullable()->unique()->after('title');
        });

        $used = [];
        foreach (DB::table('progress_reports')->orderBy('id')->get(['id', 'cut_date']) as $report) {
            $base = substr((string) $report->cut_date, 0, 10);
            $slug = $base;
            for ($i = 2; isset($used[$slug]); $i++) {
                $slug = "{$base}-{$i}";
            }
            $used[$slug] = true;
            DB::table('progress_reports')->where('id', $report->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
