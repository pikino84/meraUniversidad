<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Course extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_image',
        'path',
        'category_id'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($course) {
            $course->slug = Str::slug($course->name);
        });

        static::updating(function ($course) {
            $course->slug = Str::slug($course->name);
        });
    }

    /**
     * Categoría a la que pertenece el curso
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }


    // public function categories()
    // {
    //     return $this->belongsToMany(Category::class);
    // }
}
