<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use ZipArchive;

/**
 * Aísla el almacenamiento de cursos en una carpeta temporal del sistema: las pruebas
 * NUNCA tocan storage/app/public/cursos real.
 */
trait InteractsWithCourseStorage
{
    protected string $sandbox;

    protected function setUpCourseStorage(): void
    {
        $this->sandbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mera-tests-'.Str::random(8);

        config([
            'mera.courses.storage_root' => $this->sandbox.DIRECTORY_SEPARATOR.'public',
            'mera.courses.temp_root' => $this->sandbox.DIRECTORY_SEPARATOR.'tmp',
            'mera.courses.trash_root' => $this->sandbox.DIRECTORY_SEPARATOR.'trash',
        ]);

        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->sandbox));
    }

    protected function coursesRoot(): string
    {
        return $this->sandbox.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'cursos';
    }

    /**
     * @param  array<string,string>  $files  ruta dentro del zip => contenido
     */
    protected function makeZip(array $files, string $name = 'curso.zip'): UploadedFile
    {
        $path = $this->sandbox.DIRECTORY_SEPARATOR.Str::random(6).'-'.$name;
        File::ensureDirectoryExists(dirname($path));

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $entry => $contents) {
            str_ends_with($entry, '/') ? $zip->addEmptyDir(rtrim($entry, '/')) : $zip->addFromString($entry, $contents);
        }

        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    protected function validZip(): UploadedFile
    {
        return $this->makeZip([
            'index.html' => '<!doctype html><title>Curso</title>',
            'js/app.js' => 'console.log(1)',
            'media/intro.mp4' => 'fake-video',
        ]);
    }

    protected function cover(): UploadedFile
    {
        return UploadedFile::fake()->image('portada.png', 640, 360);
    }

    protected function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return tap(User::factory()->create())->assignRole($role);
    }
}
