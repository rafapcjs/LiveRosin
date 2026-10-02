<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        // El "slug" es el nombre en formato de dirección web: "Licores Finos" => "licores-finos".
        // Se usa para filtrar el catálogo: /?categoria=licores-finos
        static::saving(function (Category $category) {
            if ($category->isDirty('name') || ! $category->slug) {
                $category->slug = static::uniqueSlug($category->name, $category->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }
}
