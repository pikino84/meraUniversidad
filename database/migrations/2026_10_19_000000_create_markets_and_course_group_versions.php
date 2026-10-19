<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carpetas por año y versión por país (docs/carpetas/INSTRUCTIVO_VERSIONES.md §3).
 *
 * Carpeta → Año → Versión (México · Latam · EUA, editable) → Módulos en orden.
 * Migración SOLO aditiva: dos tablas nuevas y una columna opcional en `courses` y en
 * `course_reports`. No se borra ni se mueve nada; `courses.course_group_id`, `language` y
 * `position` se conservan (la API los publica). El acomodo de lo existente lo hace
 * `php artisan mera:versions-from-languages` (con --dry-run y respaldo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();      // MX, LATAM, US (= sufijo del catálogo cuando exista)
            $table->string('name', 60);                // México, Latam, EUA
            $table->string('slug', 40)->unique();      // primer segmento de la URL pública: /eua/<carpeta>
            $table->string('language', 2)->default('es'); // idioma de la página pública
            $table->string('hreflang', 10)->nullable(); // es-MX, es-419, en-US
            $table->foreignId('parent_id')->nullable()->constrained('markets')->nullOnDelete(); // Panamá → Latam
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('markets')->insert([
            ['code' => 'MX', 'name' => 'México', 'slug' => 'mexico', 'language' => 'es', 'hreflang' => 'es-MX', 'position' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'LATAM', 'name' => 'Latam', 'slug' => 'latam', 'language' => 'es', 'hreflang' => 'es-419', 'position' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'US', 'name' => 'EUA', 'slug' => 'eua', 'language' => 'en', 'hreflang' => 'en-US', 'position' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('course_group_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->string('catalog_code', 40)->nullable(); // NRM-HIG-002-EL-26
            $table->string('title', 150)->nullable();       // vacío = nombre de la carpeta
            $table->string('description', 600)->nullable();
            $table->string('evaluation_url', 500)->nullable();
            $table->string('survey_url', 500)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['course_group_id', 'year', 'market_id']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('course_group_version_id')
                ->nullable()
                ->after('course_group_id')
                ->constrained('course_group_versions')
                ->nullOnDelete();
            $table->index(['course_group_version_id', 'position']);
        });

        Schema::table('course_reports', function (Blueprint $table) {
            $table->foreignId('course_group_version_id')
                ->nullable()
                ->after('course_group_id')
                ->constrained('course_group_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_group_version_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['course_group_version_id', 'position']);
            $table->dropConstrainedForeignId('course_group_version_id');
        });

        Schema::dropIfExists('course_group_versions');
        Schema::dropIfExists('markets');
    }
};
