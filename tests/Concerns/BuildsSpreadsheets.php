<?php

namespace Tests\Concerns;

use DateTimeInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Genera Excel SINTÉTICOS para las pruebas de reportes (docs/reportes/INSTRUCTIVO_REPORTES.md §8).
 * Nunca usar los Excel reales: traen datos personales.
 */
trait BuildsSpreadsheets
{
    /** @var list<string> */
    private array $spreadsheetDirs = [];

    /**
     * @param  list<list<mixed>>  $rows  la primera fila son los encabezados
     */
    protected function makeXlsx(array $rows, string $name = 'archivo.xlsx'): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mera-xlsx-'.Str::random(8);
        File::ensureDirectoryExists($dir);

        if ($this->spreadsheetDirs === []) {
            $this->beforeApplicationDestroyed(fn () => array_map(fn ($d) => File::deleteDirectory($d), $this->spreadsheetDirs));
        }
        $this->spreadsheetDirs[] = $dir;

        $path = $dir.DIRECTORY_SEPARATOR.$name;
        $writer = new Writer;
        $writer->openToFile($path);

        // Las fechas se escriben con formato de fecha, como en los Excel reales (si no, Excel
        // las guarda como número serial).
        $dateStyle = (new Style)->setFormat('yyyy-mm-dd hh:mm:ss');

        foreach ($rows as $row) {
            $writer->addRow(new Row(array_map(
                fn ($value) => Cell::fromValue($value, $value instanceof DateTimeInterface ? $dateStyle : null),
                $row,
            )));
        }

        $writer->close();

        return $path;
    }
}
