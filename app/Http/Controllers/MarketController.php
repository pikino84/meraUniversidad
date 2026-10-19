<?php

namespace App\Http\Controllers;

use App\Models\CourseGroup;
use App\Models\Market;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pantalla "Versiones": catálogo de versiones por país (México, Latam, EUA…), solo super admin
 * (docs/carpetas/INSTRUCTIVO_VERSIONES.md §4). Agregar una versión (p. ej. Panamá dentro de
 * Latam) no requiere programar. Una versión con módulos o cortes no se borra: se desactiva.
 */
class MarketController extends Controller
{
    public function index(): View
    {
        return view('markets.index', [
            'markets' => Market::with('parent')->withCount('versions')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('markets.create', ['parents' => $this->parents()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $market = Market::create($this->validated($request));

        return redirect()->route('markets.index')->with('success', "Versión «{$market->name}» creada.");
    }

    public function edit(Market $market): View
    {
        return view('markets.edit', ['market' => $market, 'parents' => $this->parents($market)]);
    }

    public function update(Request $request, Market $market): RedirectResponse
    {
        $market->update($this->validated($request, $market));

        return redirect()->route('markets.index')->with('success', "Versión «{$market->name}» actualizada.");
    }

    public function destroy(Market $market): RedirectResponse
    {
        if ($market->versions()->exists()) {
            return back()->with('error', "«{$market->name}» ya se usa en carpetas de curso: no se puede borrar. Desactívala para que no se ofrezca en módulos nuevos.");
        }

        if ($market->children()->exists()) {
            return back()->with('error', "«{$market->name}» tiene versiones que dependen de ella: cámbiales el «Pertenece a» primero.");
        }

        $market->delete();

        return redirect()->route('markets.index')->with('success', "Versión «{$market->name}» eliminada.");
    }

    public function move(Request $request, Market $market): RedirectResponse
    {
        DB::transaction(function () use ($request, $market) {
            $all = Market::ordered()->get()->values();
            $all->each(fn ($m, $i) => $m->position !== $i + 1 && $m->forceFill(['position' => $i + 1])->save());

            $index = $all->search(fn ($m) => $m->id === $market->id);
            $swap = $request->input('direction') === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! isset($all[$swap])) {
                return;
            }

            [$a, $b] = [$all[$index], $all[$swap]];
            [$pa, $pb] = [$a->position, $b->position];
            $a->forceFill(['position' => $pb])->save();
            $b->forceFill(['position' => $pa])->save();
        });

        return back();
    }

    /** Solo un nivel: una versión con padre no puede ser padre de otra. */
    private function parents(?Market $except = null)
    {
        return Market::whereNull('parent_id')->when($except, fn ($q) => $q->where('id', '!=', $except->id))->ordered()->get();
    }

    private function validated(Request $request, ?Market $market = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
            'slug' => Str::lower(trim((string) $request->input('slug'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('markets', 'name')->ignore($market?->id)],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]{2,20}$/', Rule::unique('markets', 'code')->ignore($market?->id)],
            'slug' => [
                'required', 'string', 'max:40', 'regex:/^'.CourseGroup::SLUG_PATTERN.'$/',
                Rule::unique('markets', 'slug')->ignore($market?->id),
                // Primer segmento de /<versión>/<carpeta>: no puede ser una ruta, una carpeta ni "en"/"es".
                function ($attribute, $value, $fail) use ($market) {
                    $taken = array_diff(CourseGroup::reservedSlugs(), Market::slugs());

                    if (in_array($value, $taken, true) || ($market?->slug !== $value && CourseGroup::where('slug', $value)->exists())) {
                        $fail('Ese link ya lo usa el sistema o una carpeta de curso. Elige otro.');
                    }
                },
            ],
            'language' => ['required', Rule::in(['es', 'en'])],
            'hreflang' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2}(-[A-Za-z0-9]{2,3})?$/'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('markets', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$market?->id])),
            ],
            'is_active' => ['boolean'],
        ], [
            'code.regex' => 'El código va en mayúsculas, sin espacios (p. ej. MX, LATAM, US, PA).',
            'slug.regex' => 'El link solo puede llevar minúsculas, números y guiones (p. ej. panama).',
            'hreflang.regex' => 'Formato de idioma y país, p. ej. es-MX, es-419, en-US.',
            'parent_id.exists' => 'Solo se puede depender de una versión principal (sin «Pertenece a»).',
        ], [
            'name' => 'nombre',
            'code' => 'código',
            'slug' => 'link',
            'language' => 'idioma de la página',
            'parent_id' => 'pertenece a',
        ]);

        // Una versión que ya es padre de otras no puede depender de otra (un solo nivel).
        if (filled($data['parent_id'] ?? null) && $market?->children()->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'Esta versión tiene otras que dependen de ella: no puede pertenecer a otra.']);
        }

        return [
            'name' => trim($data['name']),
            'code' => $data['code'],
            'slug' => $data['slug'],
            'language' => $data['language'],
            'hreflang' => filled($data['hreflang'] ?? null) ? $data['hreflang'] : null,
            'parent_id' => $data['parent_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
