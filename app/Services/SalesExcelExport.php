<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Crea el archivo de Excel (.xlsx) con las ventas filtradas.
 */
class SalesExcelExport
{
    private const HEADERS = [
        'Fecha', 'Producto', 'Cantidad', 'Precio unitario', 'Costo unitario (promedio)', 'Costo total (real)', 'Total', 'Ganancia', 'Estado', 'Nota',
    ];

    /** Escribe el archivo en $path. */
    public function write(Builder $sales, string $path): void
    {
        $options = new Options;
        $options->setColumnWidth(18, 1);
        $options->setColumnWidth(42, 2);
        $options->setColumnWidth(11, 3);
        $options->setColumnWidth(16, 4, 7, 8);
        $options->setColumnWidth(22, 5, 6);
        $options->setColumnWidth(11, 9);
        $options->setColumnWidth(40, 10);

        $writer = new Writer($options);
        $writer->openToFile($path);

        $header = (new Style)->withFontBold(true)->withBackgroundColor('E8EAF0');
        $text = new Style;
        $date = (new Style)->withFormat('dd/mm/yyyy hh:mm');
        $int = (new Style)->withFormat('#,##0');
        $money = (new Style)->withFormat('"$" #,##0');

        $writer->addRow(Row::fromValuesWithStyle(self::HEADERS, $header));

        $totals = ['quantity' => 0, 'cost' => 0, 'total' => 0, 'profit' => 0];

        $sales->orderBy('sold_at')->orderBy('id')->chunk(500, function ($chunk) use ($writer, $text, $date, $int, $money, &$totals) {
            foreach ($chunk as $sale) {
                /** @var Sale $sale */
                $writer->addRow(Row::fromValuesWithStyles([
                    $sale->sold_at->toDateTimeImmutable(),
                    $sale->product_name,
                    $sale->quantity,
                    $sale->unit_price,
                    $sale->unit_cost,
                    $sale->total_cost,
                    $sale->total,
                    $sale->profit,
                    $sale->isVoided() ? 'Anulada' : 'Válida',
                    $sale->note ?? '',
                ], [$date, $text, $int, $money, $money, $money, $money, $money, $text, $text]));

                if (! $sale->isVoided()) {
                    $totals['quantity'] += $sale->quantity;
                    $totals['cost'] += $sale->total_cost;
                    $totals['total'] += $sale->total;
                    $totals['profit'] += $sale->profit;
                }
            }
        });

        $bold = fn (Style $s) => $s->withFontBold(true);
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyles(
            ['Totales (sin anuladas)', '', $totals['quantity'], '', '', $totals['cost'], $totals['total'], $totals['profit'], '', ''],
            [$bold($text), $text, $bold($int), $text, $text, $bold($money), $bold($money), $bold($money), $text, $text],
        ));

        $writer->close();
    }
}
