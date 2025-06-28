<?php
namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use ZipArchive;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::all();
        return view('courses.index', compact('courses'));
    }

    public function create()
    {
        return view('courses.create');
    }

  

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'cover_image' => 'required|image|max:2048',
            'zip_file' => 'required|file|mimes:zip|max:1024000',
        ]);

        $slug = 'curso-' . Str::random(8);

        // RUTA destino final: storage/app/public/cursos/
        $storageCoursesPath = storage_path('app/public/cursos');

        if (!file_exists($storageCoursesPath)) {
            mkdir($storageCoursesPath, 0755, true);
        }

        // Guardar ZIP temporalmente
        $zip = $request->file('zip_file');
        $zipTempPath = storage_path('app/temp/' . $zip->getClientOriginalName());
        $zip->move(storage_path('app/temp'), $zip->getClientOriginalName());

        // Extraer ZIP en carpeta temporal (storage/app/public/cursos/temp-slug)
        $tempExtractPath = $storageCoursesPath . '/temp-' . $slug;
        if (!file_exists($tempExtractPath)) {
            mkdir($tempExtractPath, 0755, true);
        }

        $zipArchive = new ZipArchive;
        if ($zipArchive->open($zipTempPath) === true) {
            Log::info('Descomprimiendo ZIP en temp: ' . $zipTempPath);
            $zipArchive->extractTo($tempExtractPath);
            $zipArchive->close();
            Log::info('ZIP descomprimido exitosamente en: ' . $tempExtractPath);
        } else {
            return back()->with('error', 'Error al descomprimir el ZIP.');
        }

        // Crear carpeta final con el slug
        $finalPath = $storageCoursesPath . '/' . $slug;

        if (file_exists($finalPath)) {
            return back()->with('error', 'Ya existe un curso con este nombre.');
        }

        mkdir($finalPath, 0755, true);

        // Mover todo el contenido de temp-slug a slug
        $files = File::allFiles($tempExtractPath);
        foreach ($files as $file) {
            $relativePath = $file->getRelativePath();
            $destinationFolder = $finalPath . ($relativePath ? '/' . $relativePath : '');

            if (!file_exists($destinationFolder)) {
                mkdir($destinationFolder, 0755, true);
            }

            File::copy($file->getRealPath(), $destinationFolder . '/' . $file->getFilename());
        }

        // Mover carpetas (subdirectorios)
        $subFolders = File::directories($tempExtractPath);
        foreach ($subFolders as $subFolder) {
            File::copyDirectory($subFolder, $finalPath . '/' . basename($subFolder));
        }

        // Guardar portada dentro de la carpeta final
        $coverImage = $request->file('cover_image');
        $coverImageName = $slug . '-cover.' . $coverImage->getClientOriginalExtension();
        $coverImage->move($storageCoursesPath, $coverImageName);

        // Borrar el zip temporal y carpeta temporal
        unlink($zipTempPath);
        File::deleteDirectory($tempExtractPath);

        // Guardar datos en la base
        Course::create([
            'name' => $request->name,
            'description' => $request->description,
            'slug' => $slug,
            'cover_image' => 'cursos/' . $coverImageName, // Ruta relativa a storage
            'path' => 'cursos/' . $slug, // Para apuntar al index.html
        ]);

        return redirect()->route('courses.index')->with('success', 'Curso creado exitosamente.');
    }




    public function edit(Course $course)
    {
        return view('courses.edit', compact('course'));
    }

    public function update(Request $request, Course $course)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'cover_image' => 'nullable|image|max:2048',
            'zip_file' => 'nullable|file|mimes:zip|max:1024000',
        ]);

        $oldSlug = $course->slug;
        $newSlug = Str::slug($request->name);
        $storageCoursesPath = storage_path('app/public/cursos');
        $finalPath = $storageCoursesPath . '/' . $newSlug;

        // Cambiar nombre de carpeta si cambia el slug y no hay nuevo zip
        /*if (!$request->hasFile('zip_file') && $oldSlug !== $newSlug) {
            $oldPath = $storageCoursesPath . '/' . $oldSlug;
            if (file_exists($oldPath)) {
                rename($oldPath, $finalPath);
            }
        }*/

        // Si viene un ZIP nuevo, reemplaza contenido
        if ($request->hasFile('zip_file')) {
            // ZIP temporal
            $zip = $request->file('zip_file');
            $zipTempPath = storage_path('app/temp/' . $zip->getClientOriginalName());
            $zip->move(storage_path('app/temp'), $zip->getClientOriginalName());

            // Extraer ZIP a temp
            $tempExtractPath = $storageCoursesPath . '/temp-' . $newSlug;
            if (!file_exists($tempExtractPath)) {
                mkdir($tempExtractPath, 0755, true);
            }

            $zipArchive = new ZipArchive;
            if ($zipArchive->open($zipTempPath) === true) {
                $zipArchive->extractTo($tempExtractPath);
                $zipArchive->close();
            } else {
                return back()->with('error', 'Error al descomprimir el ZIP.');
            }

            // Eliminar carpeta anterior (si existe)
            if (file_exists($storageCoursesPath . '/' . $oldSlug)) {
                File::deleteDirectory($storageCoursesPath . '/' . $oldSlug);
            }

            // Crear carpeta nueva
            mkdir($finalPath, 0755, true);

            // Mover contenido descomprimido a la carpeta final
            $files = File::allFiles($tempExtractPath);
            foreach ($files as $file) {
                $relativePath = $file->getRelativePath();
                $destinationFolder = $finalPath . ($relativePath ? '/' . $relativePath : '');

                if (!file_exists($destinationFolder)) {
                    mkdir($destinationFolder, 0755, true);
                }

                File::copy($file->getRealPath(), $destinationFolder . '/' . $file->getFilename());
            }

            // Mover subdirectorios
            $subFolders = File::directories($tempExtractPath);
            foreach ($subFolders as $subFolder) {
                File::copyDirectory($subFolder, $finalPath . '/' . basename($subFolder));
            }

            // Eliminar archivos temporales
            unlink($zipTempPath);
            File::deleteDirectory($tempExtractPath);
        }

        // Imagen de portada
        if ($request->hasFile('cover_image')) {
            $coverImage = $request->file('cover_image');
            $coverImageName = $newSlug . '-cover.' . $coverImage->getClientOriginalExtension();
            $coverImage->move($storageCoursesPath, $coverImageName);
            $course->cover_image = 'cursos/' . $coverImageName;
        } elseif ($oldSlug !== $newSlug && !$request->hasFile('zip_file')) {
            // Renombrar imagen si se cambió el slug pero no se subió imagen ni zip
            $oldImagePath = $storageCoursesPath . '/' . $oldSlug . '/cover.*';
            $oldImageFiles = glob($oldImagePath);
            if (!empty($oldImageFiles)) {
                $oldImage = $oldImageFiles[0];
                $extension = pathinfo($oldImage, PATHINFO_EXTENSION);
                $newImageName = 'cover.' . $extension;
                rename($oldImage, $storageCoursesPath . '/' . $newImageName);
                $course->cover_image = 'cursos/' . $newImageName;
            }
        }

        // Actualizar curso
        $course->update([
            'name' => $request->name,
            'description' => $request->description,
            'slug' => $newSlug,
            'cover_image' => $course->cover_image,
            'path' => 'cursos/' . $newSlug,
        ]);

        return redirect()->route('courses.index')->with('success', 'Curso actualizado exitosamente.');
    }



    public function publicIndex()
    {
        $courses = Course::all();
        return view('courses.index', compact('courses'));
    }

    public function destroy(Course $course)
    {
        // Eliminar carpeta del curso
        $storagePath = storage_path('app/public/' . $course->path);
        $coverImagePath = storage_path('app/public/' . $course->cover_image);
        if (file_exists($storagePath)) {
            File::deleteDirectory($storagePath);
        }

        // Eliminar registro de la base de datos
        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Curso eliminado exitosamente.');
    }

}
