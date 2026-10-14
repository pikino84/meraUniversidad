<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Página pública por carpeta de curso (CLAUDE.md §10, sacado de stand by el 2026-10-13):
 * cursos.meracorporation.com/<slug> (español) y /<slug>/en (inglés).
 * Nace APAGADA (is_published = false) hasta que alguien la publique. Todo opcional y aditivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_groups', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->text('description_es')->nullable()->after('position');
            $table->text('description_en')->nullable()->after('description_es');
            $table->string('evaluation_url_es', 500)->nullable()->after('description_en');
            $table->string('evaluation_url_en', 500)->nullable()->after('evaluation_url_es');
            $table->string('survey_url_es', 500)->nullable()->after('evaluation_url_en');
            $table->string('survey_url_en', 500)->nullable()->after('survey_url_es');
            $table->boolean('is_published')->default(false)->after('survey_url_en');
            $table->timestamp('published_at')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('course_groups', function (Blueprint $table) {
            $table->dropColumn([
                'name_en', 'description_es', 'description_en',
                'evaluation_url_es', 'evaluation_url_en', 'survey_url_es', 'survey_url_en',
                'is_published', 'published_at',
            ]);
        });
    }
};
