<?php

namespace App\Services;

use App\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Crea el archivo de Excel (.xlsx) con las compras filtradas.
 */
class PurchasesExcelExport
{
    private const HEADERS = ['Fecha', 'Producto', 'Proveedor', 'Cantidad', 'Costo unitario', 'Total', 'Estado'];

    public function write(Builder $purchases, string $path): void
    {
        $options = new Options;
        $options->setColumnWidth(18, 1);
        $options->setColumnWidth(42, 2);
        $options->setColumnWidth(24, 3);
        $options->setColumnWidth(11, 4);
        $options->setColumnWidth(16, 5, 6);
        $options->setColumnWidth(11, 7);

        $writer = new Writer($options);
        $writer->openToFile($path);

        $header = (new Style)->withFontBold(true)->withBackgroundColor('E8EAF0');
        $text = new Style;
        $date = (new Style)->withFormat('dd/mm/yyyy hh:mm');
        $int = (new Style)->withFormat('#,##0');
        $money = (new Style)->withFormat('"$" #,##0');

        $writer->addRow(Row::fromValuesWithStyle(self::HEADERS, $header));

        $totals = ['quantity' => 0, 'total' => 0];

        $purchases->orderBy('purchased_at')->orderBy('id')->chunk(500, function ($chunk) use ($writer, $text, $date, $int, $money, &$totals) {
            foreach ($chunk as $p) {
                /** @var Purchase $p */
                $writer->addRow(Row::fromValuesWithStyles([
                    $p->purchased_at->toDateTimeImmutable(),
                    $p->product_name,
                    $p->supplier ?? '',
                    $p->quantity,
                    $p->unit_cost,
                    $p->total,
                    $p->isVoided() ? 'Anulada' : 'Válida',
                ], [$date, $text, $text, $int, $money, $money, $text]));

                if (! $p->isVoided()) {
                    $totals['quantity'] += $p->quantity;
                    $totals['total'] += $p->total;
                }
            }
        });

        $bold = fn (Style $s) => $s->withFontBold(true);
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyles(
            ['Totales (sin anuladas)', '', '', $totals['quantity'], '', $totals['total'], ''],
            [$bold($text), $text, $text, $bold($int), $text, $bold($money), $text],
        ));

        $writer->close();
    }
}
