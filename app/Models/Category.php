<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
    ];


    /**
     * Categoría padre
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }


    /**
     * Categorías hijas directas
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('name');
    }


    /**
     * Árbol completo de categorías hijas
     */
    public function childrenRecursive()
    {
        return $this->children()
            ->with('childrenRecursive');
    }


    /**
     * Todos los niveles inferiores
     */
    public function descendants()
    {
        return $this->children()
            ->with('descendants');
    }


    /**
     * Cursos pertenecientes a esta categoría
     */
    public function courses()
    {
        return $this->hasMany(Course::class);
    }


    /**
     * Ruta completa:
     *
     * Tecnología > Programación > Laravel
     */
    public function getFullPathAttribute()
    {
        $path = collect([$this->name]);

        $parent = $this->parent;


        while ($parent) {

            $path->prepend($parent->name);

            $parent = $parent->parent;
        }


        return $path->implode(' > ');
    }


    /**
     * Obtener árbol principal de categorías
     */
    public static function tree()
    {
        return self::whereNull('parent_id')
            ->with('childrenRecursive')
            ->orderBy('name')
            ->get();
    }

    /**
     * IDs de esta categoría y todos sus descendientes.
     * Útil para filtrar cursos incluyendo subcategorías.
     */
    public function getDescendantIds(): \Illuminate\Support\Collection
    {
        $this->loadMissing('childrenRecursive');

        $ids = collect([$this->id]);

        foreach ($this->childrenRecursive as $child) {
            $ids = $ids->merge($child->getDescendantIds());
        }

        return $ids;
    }
}
