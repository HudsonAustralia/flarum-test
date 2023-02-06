<?php

namespace Kilowhat\Formulaire\Export;

use Maatwebsite\Excel\Cache\CacheManager;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\BeforeWriting;
use Maatwebsite\Excel\Factories\WriterFactory;
use Maatwebsite\Excel\Writer;

/**
 * Same as Writer but add ability to use tmpfile() as the storage method
 */
class TemporaryStreamWriter extends Writer
{
    public function exportTemporaryStream($export, string $writerType)
    {
        $this->open($export);

        $sheetExports = [$export];
        if ($export instanceof WithMultipleSheets) {
            $sheetExports = $export->sheets();
        }

        foreach ($sheetExports as $sheetExport) {
            $this->addNewSheet()->export($sheetExport);
        }

        return $this->writeTemporary($export, tmpfile(), $writerType);
    }

    public function writeTemporary($export, $temporaryFile, string $writerType)
    {
        $this->exportable = $export;

        $this->spreadsheet->setActiveSheetIndex(0);

        $this->raise(new BeforeWriting($this, $this->exportable));

        $writer = WriterFactory::make(
            $writerType,
            $this->spreadsheet,
            $export
        );

        $writer->save($temporaryFile);

        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);
        resolve(CacheManager::class)->flush();

        return $temporaryFile;
    }
}
