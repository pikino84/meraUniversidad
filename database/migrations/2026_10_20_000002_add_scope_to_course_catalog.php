<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alcance del curso: general (para todos) o específico (ciertas áreas o puestos), acordado con
 * Capacitación el 2026-10-19 (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §7.3). Viene de la columna
 * "Alcance" del Excel del catálogo. Solo aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_catalog', function (Blueprint $table) {
            $table->string('scope', 10)->nullable()->after('level'); // general | specific
        });
    }

    public function down(): void
    {
        Schema::table('course_catalog', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
