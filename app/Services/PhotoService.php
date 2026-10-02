<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Guarda las fotos de los productos: las reduce a máximo 1200 px,
 * crea una miniatura y les pone un nombre aleatorio.
 */
class PhotoService
{
    public const MAX_SIDE = 1200;

    public const THUMB_SIDE = 480;

    private const DIR = 'productos';

    public function store(UploadedFile $file): string
    {
        $manager = new ImageManager(new Driver);
        $name = Str::random(40).'.webp';
        $path = self::DIR.'/'.$name;

        $image = $manager->read($file->getRealPath());
        $image->scaleDown(self::MAX_SIDE, self::MAX_SIDE);
        Storage::disk('public')->put($path, (string) $image->toWebp(82));

        $image->scaleDown(self::THUMB_SIDE, self::THUMB_SIDE);
        Storage::disk('public')->put(self::thumbPathFor($path), (string) $image->toWebp(78));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete([$path, self::thumbPathFor($path)]);
        }
    }

    public static function thumbPathFor(string $path): string
    {
        return dirname($path).'/miniaturas/'.basename($path);
    }
}
