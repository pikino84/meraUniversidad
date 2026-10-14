<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Papelera de módulos (CLAUDE.md §12, F2). Solo aditiva:
 *  - deleted_at: borrado suave; el registro se conserva para poder restaurarlo.
 *  - trash_items: qué archivos se movieron a la papelera y con qué nombre, para devolverlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->softDeletes();
            $table->json('trash_items')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['trash_items', 'deleted_at']);
        });
    }
};
