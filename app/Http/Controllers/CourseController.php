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

        $slug = Str::slug($request->name);

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
        $coverImageName = 'cover.' . $coverImage->getClientOriginalExtension();
        $coverImage->move($finalPath, $coverImageName);

        // Borrar el zip temporal y carpeta temporal
        unlink($zipTempPath);
        File::deleteDirectory($tempExtractPath);

        // Guardar datos en la base
        Course::create([
            'name' => $request->name,
            'description' => $request->description,
            'slug' => $slug,
            'cover_image' => 'cursos/' . $slug . '/' . $coverImageName, // Ruta relativa a storage
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
        ]);

        $course->update([
            'name' => $request->name,
            'description' => $request->description,
            'cover_image' => $request->hasFile('cover_image')
                ? $request->file('cover_image')->store('courses/covers', 'public')
                : $course->cover_image,
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
        if (file_exists($storagePath)) {
            File::deleteDirectory($storagePath);
        }

        // Eliminar registro de la base de datos
        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Curso eliminado exitosamente.');
    }

}
