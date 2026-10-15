<?php

namespace App\Http\Requests\Reports;

use App\Models\CourseReport;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Nuevo corte (POST: los 3 Excel obligatorios) y actualización (PUT: los 3 Excel opcionales,
 * pero si se manda uno se mandan los tres, para recalcular el corte completo).
 *
 * No se usa "mimes:xlsx": un .xlsx es un ZIP y, según la herramienta que lo generó, finfo lo
 * reconoce como application/zip y rechazaría un archivo válido. Se valida la extensión y el
 * tamaño; si el contenido no es un Excel, ReportWorkbookReader lo dice con un mensaje claro.
 */
class CourseReportRequest extends FormRequest
{
    public const FILES = [
        'personal' => 'reporte de personal',
        'inscripciones' => 'reporte de inscripciones',
        'encuesta' => 'encuesta de satisfacción',
    ];

    public function authorize(): bool
    {
        $report = $this->route('report');

        return $report instanceof CourseReport
            ? $this->user()->can('update', $report)
            : $this->user()->can('create', CourseReport::class);
    }

    /**
     * Fecha de corte vacía = hoy (en la zona horaria de México). Se sugiere sola con la fecha del
     * nombre del Excel (report-form.js); esto cubre el caso en que el nombre no la trae.
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('cut_date'))) {
            $this->merge(['cut_date' => now(config('mera.display_timezone'))->toDateString()]);
        }
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $maxKb = (int) config('mera.reports.max_upload_kb');
        $rules = [
            'title' => ['required', 'string', 'max:150'],
            'cut_date' => ['required', 'date', 'before_or_equal:'.now(config('mera.display_timezone'))->toDateString()],
        ];

        if ($creating) {
            $rules['survey'] = ['required', 'string', Rule::in(array_keys(config('report_surveys')))];
        }

        foreach (array_keys(self::FILES) as $key) {
            $others = implode(',', array_diff(array_keys(self::FILES), [$key]));
            $rules[$key] = [
                $creating ? 'required' : "required_with:{$others}",
                'nullable',
                'file',
                "max:{$maxKb}",
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value instanceof UploadedFile && strtolower($value->getClientOriginalExtension()) !== 'xlsx') {
                        $fail('El :attribute debe ser un archivo de Excel (.xlsx).');
                    }
                },
            ];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return self::FILES + ['title' => 'título', 'cut_date' => 'fecha de corte', 'survey' => 'encuesta'];
    }

    public function messages(): array
    {
        return [
            '*.required_with' => 'Para recalcular el corte sube los tres archivos (personal, inscripciones y encuesta).',
            'cut_date.before_or_equal' => 'La fecha de corte no puede ser posterior a hoy.',
        ];
    }

    /** @return array{personal: UploadedFile, inscripciones: UploadedFile, encuesta: UploadedFile}|null */
    public function workbooks(): ?array
    {
        $files = [];
        foreach (array_keys(self::FILES) as $key) {
            $files[$key] = $this->file($key);
        }

        return in_array(null, $files, true) ? null : $files;
    }
}
