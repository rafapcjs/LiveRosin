<?php

/*
 * Funciones de ayuda usadas en las vistas.
 */

if (! function_exists('cop')) {
    /** Formatea pesos colombianos: 1250000 => "$ 1.250.000". */
    function cop(int|float|null $amount): string
    {
        $amount = (int) round((float) $amount);
        $sign = $amount < 0 ? '-' : '';

        return $sign.'$ '.number_format(abs($amount), 0, ',', '.');
    }
}

if (! function_exists('pct')) {
    /** Formatea un porcentaje con un decimal: 33.333 => "33,3 %". Null => "—". */
    function pct(int|float|null $value): string
    {
        return $value === null ? '—' : number_format((float) $value, 1, ',', '.').' %';
    }
}

if (! function_exists('num')) {
    /** Formatea un número entero con puntos de miles: 1500 => "1.500". */
    function num(int|float|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }
}

if (! function_exists('asset_v')) {
    /**
     * Enlace a un archivo de /public con una versión basada en su fecha de modificación,
     * para que el navegador descargue la copia nueva cuando el archivo cambie.
     */
    function asset_v(string $path): string
    {
        $full = public_path($path);
        $version = is_file($full) ? filemtime($full) : 0;

        return asset($path).'?v='.$version;
    }
}
