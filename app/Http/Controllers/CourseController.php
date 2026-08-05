<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use App\Models\Course;
use App\Models\Category;

use ZipArchive;

class CourseController extends Controller
{
    protected string $storageCoursesPath;

    public function __construct()
    {
        $this->storageCoursesPath = storage_path('app/public/cursos');
    }

    public function index(Request $request)
    {
        $query = Course::with('category');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('category_id')) {
            $category = Category::find($request->category_id);

            if ($category) {
                $query->whereIn('category_id', $category->getDescendantIds());
            }
        }

        $courses = $query->orderBy('created_at', 'desc')->paginate(20);
        $categories = Category::tree();

        return view('courses.index', compact('courses', 'categories'));
    }

    public function create()
    {
        $categories = Category::tree();

        return view('courses.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'nullable|exists:categories,id',
            'cover_image' => 'required|image|max:2048',
            'zip_file' => 'required|file|mimes:zip|max:1024000',
        ]);

        $slug = Str::slug($request->name);

        if (Course::where('slug', $slug)->exists()) {
            return back()->withInput()->with('error', 'Ya existe un curso con este nombre.');
        }

        $this->ensureDirectory($this->storageCoursesPath);

        $zipTempPath = null;
        $tempExtractRoot = null;
        $finalPath = null;

        try {
            [$zipTempPath, $tempExtractRoot, $extractedPath] = $this->extractZip(
                $request->file('zip_file'),
                'temp-' . $slug
            );

            $finalPath = $this->storageCoursesPath . '/' . $slug;

            if (File::exists($finalPath)) {
                throw new \Exception('Ya existe un curso con este nombre.');
            }

            File::copyDirectory($extractedPath, $finalPath);

            $coverImage = $request->file('cover_image');
            $coverImageName = $slug . '-cover.' . $coverImage->getClientOriginalExtension();
            $coverImage->move($this->storageCoursesPath, $coverImageName);

            Course::create([
                'name' => $request->name,
                'description' => $request->description,
                'slug' => $slug,
                'category_id' => $request->category_id,
                'cover_image' => 'cursos/' . $coverImageName,
                'path' => 'cursos/' . $slug,
            ]);

            $this->cleanupTemp($zipTempPath, $tempExtractRoot);

            return redirect()->route('courses.index')->with('success', 'Curso creado exitosamente.');
        } catch (\Throwable $e) {
            Log::error($e);

            $this->cleanupTemp($zipTempPath, $tempExtractRoot);

            if ($finalPath && File::exists($finalPath)) {
                File::deleteDirectory($finalPath);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(Course $course)
    {
        $categories = Category::tree();

        return view('courses.edit', compact('course', 'categories'));
    }

    public function update(Request $request, Course $course)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'nullable|exists:categories,id',
            'cover_image' => 'nullable|image|max:2048',
            'zip_file' => 'nullable|file|mimes:zip|max:1024000',
        ]);

        $oldSlug = $course->slug;
        $newSlug = Str::slug($request->name);

        if ($newSlug !== $oldSlug && Course::where('slug', $newSlug)->where('id', '!=', $course->id)->exists()) {
            return back()->withInput()->with('error', 'Ya existe otro curso con este nombre.');
        }

        $oldFolderPath = $this->storageCoursesPath . '/' . $oldSlug;
        $finalPath = $this->storageCoursesPath . '/' . $newSlug;

        $zipTempPath = null;
        $tempExtractRoot = null;

        try {
            if ($request->hasFile('zip_file')) {
                [$zipTempPath, $tempExtractRoot, $extractedPath] = $this->extractZip(
                    $request->file('zip_file'),
                    'temp-' . $newSlug
                );

                if (File::exists($oldFolderPath)) {
                    File::deleteDirectory($oldFolderPath);
                }

                if (File::exists($finalPath)) {
                    File::deleteDirectory($finalPath);
                }

                File::copyDirectory($extractedPath, $finalPath);

                $this->cleanupTemp($zipTempPath, $tempExtractRoot);
            } elseif ($oldSlug !== $newSlug && File::exists($oldFolderPath)) {
                // Solo cambió el nombre: renombrar la carpeta existente
                File::moveDirectory($oldFolderPath, $finalPath);
            }

            // Portada
            if ($request->hasFile('cover_image')) {
                $oldCoverPath = storage_path('app/public/' . $course->cover_image);

                $coverImage = $request->file('cover_image');
                $coverImageName = $newSlug . '-cover.' . $coverImage->getClientOriginalExtension();
                $coverImage->move($this->storageCoursesPath, $coverImageName);

                if (File::exists($oldCoverPath) && $oldCoverPath !== $this->storageCoursesPath . '/' . $coverImageName) {
                    File::delete($oldCoverPath);
                }

                $course->cover_image = 'cursos/' . $coverImageName;
            } elseif ($oldSlug !== $newSlug) {
                // Renombrar el archivo de portada para que coincida con el nuevo slug
                $oldCoverPath = storage_path('app/public/' . $course->cover_image);

                if (File::exists($oldCoverPath)) {
                    $extension = pathinfo($oldCoverPath, PATHINFO_EXTENSION);
                    $newCoverName = $newSlug . '-cover.' . $extension;

                    File::move($oldCoverPath, $this->storageCoursesPath . '/' . $newCoverName);
                    $course->cover_image = 'cursos/' . $newCoverName;
                }
            }

            $course->update([
                'name' => $request->name,
                'description' => $request->description,
                'slug' => $newSlug,
                'category_id' => $request->category_id,
                'cover_image' => $course->cover_image,
                'path' => 'cursos/' . $newSlug,
            ]);

            return redirect()->route('courses.index')->with('success', 'Curso actualizado exitosamente.');
        } catch (\Throwable $e) {
            Log::error($e);

            $this->cleanupTemp($zipTempPath, $tempExtractRoot);

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function publicIndex()
    {
        $courses = Course::with('category')->latest()->get();

        return view('courses.index', compact('courses'));
    }

    public function destroy(Course $course)
    {
        $storagePath = storage_path('app/public/' . $course->path);
        $coverImagePath = storage_path('app/public/' . $course->cover_image);

        if (File::exists($coverImagePath)) {
            File::delete($coverImagePath);
        }

        if (File::exists($storagePath)) {
            File::deleteDirectory($storagePath);
        }

        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Curso eliminado exitosamente.');
    }

    /* ==================== Helpers privados ==================== */

    private function ensureDirectory(string $path): void
    {
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    /**
     * Sube y descomprime un ZIP en una carpeta temporal, valida que tenga index.html
     * y detecta si el ZIP viene con una carpeta raíz adicional para "aplanarla".
     *
     * @return array{0:string,1:string,2:string} [$zipTempPath, $tempExtractRoot, $extractedPath]
     */
    private function extractZip($zipFile, string $tempFolderName): array
    {
        $tempPath = storage_path('app/temp');
        $this->ensureDirectory($tempPath);

        $tempZipName = Str::uuid() . '.zip';
        $zipFile->move($tempPath, $tempZipName);
        $zipTempPath = $tempPath . '/' . $tempZipName;

        $tempExtractRoot = $this->storageCoursesPath . '/' . $tempFolderName;
        $this->ensureDirectory($tempExtractRoot);

        $zipArchive = new ZipArchive;

        if ($zipArchive->open($zipTempPath) !== true) {
            throw new \Exception('Error al descomprimir el ZIP.');
        }

        // Protección básica contra zip slip (rutas con ../)
        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            if (Str::contains($zipArchive->getNameIndex($i), '..')) {
                $zipArchive->close();
                throw new \Exception('El archivo ZIP contiene rutas no válidas.');
            }
        }

        $zipArchive->extractTo($tempExtractRoot);
        $zipArchive->close();

        $extractedPath = $tempExtractRoot;

        // Si el ZIP tiene una única carpeta raíz, usar esa como contenido real
        $items = File::directories($extractedPath);
        $files = File::files($extractedPath);

        if (count($items) === 1 && count($files) === 0) {
            $extractedPath = $items[0];
        }

        if (!File::exists($extractedPath . '/index.html')) {
            throw new \Exception('El archivo ZIP no contiene un index.html válido.');
        }

        return [$zipTempPath, $tempExtractRoot, $extractedPath];
    }

    private function cleanupTemp(?string $zipTempPath, ?string $tempExtractRoot): void
    {
        if ($zipTempPath && File::exists($zipTempPath)) {
            File::delete($zipTempPath);
        }

        if ($tempExtractRoot && File::exists($tempExtractRoot)) {
            File::deleteDirectory($tempExtractRoot);
        }
    }
}
