<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina tablas heredadas de otro proyecto (salas lounge, agencias, clientes…) que la app
 * no usa. SOLO borra una tabla si está VACÍA; si tiene datos la conserva y lo registra.
 *
 * ⚠ Igual que cualquier migración: generar backup de la BD antes de correrla (CLAUDE.md, regla 2).
 */
return new class extends Migration
{
    /** Orden: primero las tablas pivote (tienen FKs hacia las demás). */
    private array $tables = ['lounge_user', 'agency_lounge', 'agents', 'lounges', 'agencies', 'clients', 'status'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->exists()) {
                Log::warning("Migración legacy: la tabla [{$table}] tiene datos y NO se eliminó.");

                continue;
            }

            Schema::drop($table);
        }
    }

    public function down(): void
    {
        // Sin reversa: las tablas estaban vacías y su estructura sigue en las migraciones originales.
    }
};
