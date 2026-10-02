<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajustes de la tienda (una sola fila).
 */
class Setting extends Model
{
    protected $fillable = ['store_name', 'tagline', 'whatsapp', 'low_stock_threshold'];

    protected function casts(): array
    {
        return ['low_stock_threshold' => 'integer'];
    }

    private static ?self $current = null;

    /** Los ajustes vigentes. Se leen una vez por visita. */
    public static function current(): self
    {
        return static::$current ??= static::query()->first()
            ?? static::create(['store_name' => 'Mi tienda', 'low_stock_threshold' => 3]);
    }

    public static function lowStockThreshold(): int
    {
        return static::current()->low_stock_threshold;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::$current = null);
    }

    /** Solo para pruebas: olvida los ajustes leídos. */
    public static function forget(): void
    {
        static::$current = null;
    }
}
