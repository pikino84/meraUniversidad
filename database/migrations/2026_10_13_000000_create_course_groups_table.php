<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carpetas de curso (CLAUDE.md §10, alcance aprobado 2026-10-13).
 *
 * Curso (carpeta) → Idioma (es / en) → Módulos en orden.
 * Un "módulo" es un registro existente de `courses`: no se mueve ningún archivo ni cambia
 * ninguna URL. Migración SOLO aditiva: tabla nueva + 3 columnas opcionales en `courses`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('course_group_id')
                ->nullable()
                ->after('category_id')
                ->constrained('course_groups')
                ->nullOnDelete(); // borrar una carpeta regresa sus módulos a "Sin carpeta"
            $table->string('language', 2)->nullable()->after('course_group_id'); // es | en
            $table->unsignedInteger('position')->nullable()->after('language');

            $table->index(['course_group_id', 'language', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['course_group_id', 'language', 'position']);
            $table->dropConstrainedForeignId('course_group_id');
            $table->dropColumn(['language', 'position']);
        });

        Schema::dropIfExists('course_groups');
    }
};
