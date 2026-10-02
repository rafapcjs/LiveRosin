<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Rango de fechas para filtrar ventas, siempre en hora de Colombia.
 * La semana va de lunes a domingo.
 */
final class DateRange
{
    public const PRESETS = [
        'hoy' => 'Hoy',
        'semana' => 'Esta semana',
        'mes' => 'Este mes',
        'mes-pasado' => 'Mes pasado',
    ];

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?string $preset,
        public readonly ?string $error = null,
    ) {}

    public static function preset(string $preset): self
    {
        $now = CarbonImmutable::now();

        [$from, $to] = match ($preset) {
            'hoy' => [$now, $now],
            'semana' => [$now->startOfWeek(CarbonImmutable::MONDAY), $now->endOfWeek(CarbonImmutable::SUNDAY)],
            'mes-pasado' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };

        return new self($from->startOfDay(), $to->endOfDay(), array_key_exists($preset, self::PRESETS) ? $preset : 'mes');
    }

    /** Lee ?periodo=… o ?desde=AAAA-MM-DD&hasta=AAAA-MM-DD. Por defecto: este mes. */
    public static function fromRequest(Request $request): self
    {
        $from = self::parse($request->query('desde'));
        $to = self::parse($request->query('hasta'));

        if ($request->filled('desde') || $request->filled('hasta')) {
            if (! $from || ! $to) {
                $range = self::preset('mes');

                return new self($range->from, $range->to, 'mes', 'Las fechas no son válidas. Se muestra este mes.');
            }

            if ($from->greaterThan($to)) {
                [$from, $to] = [$to, $from];
            }

            $range = new self($from->startOfDay(), $to->endOfDay(), null);

            // Si el rango escrito coincide con un atajo, se marca ese atajo.
            foreach (array_keys(self::PRESETS) as $key) {
                $p = self::preset($key);
                if ($p->from->equalTo($range->from) && $p->to->equalTo($range->to)) {
                    return $p;
                }
            }

            return $range;
        }

        return self::preset((string) $request->query('periodo', 'mes'));
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** @return array{desde: string, hasta: string} */
    public function query(): array
    {
        return ['desde' => $this->from->format('Y-m-d'), 'hasta' => $this->to->format('Y-m-d')];
    }

    public function label(): string
    {
        if ($this->preset) {
            return self::PRESETS[$this->preset];
        }

        if ($this->from->isSameDay($this->to)) {
            return $this->from->translatedFormat('j \d\e F \d\e Y');
        }

        return 'Del '.$this->from->translatedFormat('j M Y').' al '.$this->to->translatedFormat('j M Y');
    }
}
