<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * Historial de cambios (solo Super Admin), legible para personas (CLAUDE.md §12, U13):
 * nombre del registro en vez de "Course #127", campos y valores en español, y filtros por
 * módulo, usuario y fecha.
 */
class ActivityLogController extends Controller
{
    /** log_name → etiqueta del filtro. */
    public const LOGS = [
        'cursos' => 'Cursos (módulos)',
        'carpetas' => 'Carpetas de curso',
        'categorias' => 'Categorías',
        'usuarios' => 'Usuarios',
        'reportes' => 'Reportes de curso',
    ];

    /** Tipo de registro → nombre en singular. */
    private const SUBJECTS = [
        Course::class => 'Módulo',
        CourseGroup::class => 'Carpeta',
        Category::class => 'Categoría',
        User::class => 'Usuario',
        \App\Models\CourseReport::class => 'Reporte',
    ];

    /** Campo técnico → etiqueta en español. */
    private const FIELDS = [
        'name' => 'Nombre',
        'name_en' => 'Nombre en inglés',
        'last_name' => 'Apellido',
        'email' => 'Correo',
        'phone' => 'Teléfono',
        'slug' => 'Link',
        'description' => 'Descripción',
        'description_es' => 'Descripción (español)',
        'description_en' => 'Descripción (inglés)',
        'category_id' => 'Categoría',
        'parent_id' => 'Categoría padre',
        'course_group_id' => 'Carpeta',
        'language' => 'Idioma',
        'position' => 'N.º de módulo',
        'cover_image' => 'Portada',
        'path' => 'Archivos del curso',
        'is_published' => 'Página publicada',
        'evaluation_url_es' => 'Evaluación (español)',
        'evaluation_url_en' => 'Evaluación (inglés)',
        'survey_url_es' => 'Encuesta (español)',
        'survey_url_en' => 'Encuesta (inglés)',
        'title' => 'Título',
        'cut_date' => 'Fecha de corte',
        'shared_at' => 'Link para compartir',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'log' => ['nullable', 'in:'.implode(',', array_keys(self::LOGS))],
            'usuario' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $tz = config('mera.display_timezone');

        $logs = Activity::query()
            ->with(['causer', 'subject'])
            ->when($filters['log'] ?? null, fn ($q, $log) => $q->where('log_name', $log))
            ->when($filters['usuario'] ?? null, fn ($q, $id) => $q->where('causer_type', User::class)->where('causer_id', $id))
            ->when($filters['desde'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d, $tz)->startOfDay()->utc()))
            ->when($filters['hasta'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d, $tz)->endOfDay()->utc()))
            ->latest()
            ->paginate(25)
            ->onEachSide(1)
            ->withQueryString();

        $users = User::query()
            ->whereIn('id', Activity::query()->where('causer_type', User::class)->distinct()->pluck('causer_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('activity_logs.index', [
            'logs' => $logs,
            // Con las categorías ocultas no se ofrece su filtro (su historial se conserva).
            'logNames' => config('mera.categories_enabled') ? self::LOGS : array_diff_key(self::LOGS, ['categorias' => true]),
            'users' => $users,
            'filters' => $filters,
            'describe' => fn (Activity $log) => $this->describe($log),
        ]);
    }

    /**
     * @return array{subject:string,changes:list<array{field:string,old:?string,new:?string}>}
     */
    private function describe(Activity $log): array
    {
        $type = self::SUBJECTS[$log->subject_type] ?? class_basename((string) $log->subject_type);
        $name = $log->subject?->name
            ?? ($log->properties['old']['name'] ?? $log->properties['attributes']['name'] ?? null);

        $subject = $name ? "{$type} «{$name}»" : "{$type} #{$log->subject_id}";

        if (! $log->subject && $log->event !== 'deleted') {
            $subject .= ' (ya no existe)';
        }

        $new = $log->properties['attributes'] ?? [];
        $old = $log->properties['old'] ?? [];
        $changes = [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $field) {
            $changes[] = [
                'field' => self::FIELDS[$field] ?? $field,
                'old' => array_key_exists($field, $old) ? $this->format($field, $old[$field]) : null,
                'new' => array_key_exists($field, $new) ? $this->format($field, $new[$field]) : null,
            ];
        }

        return ['subject' => $subject, 'changes' => $changes];
    }

    private function format(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field) {
            'language' => CourseGroup::LANGUAGES[$value] ?? (string) $value,
            'is_published' => $value ? 'Sí' : 'No',
            'course_group_id' => $this->groupName((int) $value),
            'category_id', 'parent_id' => Category::pathMap()[(int) $value] ?? "#{$value}",
            default => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }

    /** @var array<int,string>|null Nombres de carpetas (por request, no estático). */
    private ?array $groupNames = null;

    private function groupName(int $id): string
    {
        $this->groupNames ??= CourseGroup::pluck('name', 'id')->all();

        return $this->groupNames[$id] ?? "#{$id}";
    }
}
