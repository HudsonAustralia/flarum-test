<?php

namespace Kilowhat\Formulaire\Export;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Kilowhat\Formulaire\Submission;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class FlatSubmissionExport extends AbstractExport implements FromQuery, ShouldAutoSize, WithMapping, WithColumnFormatting, WithStrictNullComparison
{
    use Exportable;

    /**
     * @param Submission $submission
     * @return array
     */
    public function map($submission): array
    {
        $data = [];

        foreach ($this->fields as $field) {
            $value = Arr::get($submission->data, $field['key']);
            $data[] = is_null($value) ? null : $field['excelFormat']($value);
        }

        foreach ($this->meta as $attribute) {
            $value = $this->getMetaValue($submission, $attribute);

            if ($value instanceof Carbon) {
                $value = Date::dateTimeToExcel($value);
            }

            $data[] = $value;
        }

        return $data;
    }

    public function columnFormats(): array
    {
        $format = [];
        $index = 1;

        foreach ($this->fields as $field) {
            if ($field['excelStyle']) {
                $format[Coordinate::stringFromColumnIndex($index)] = $field['excelStyle'];
            }

            $index++;
        }

        foreach ($this->meta as $attribute) {
            if (Str::endsWith($attribute, '_at')) {
                $format[Coordinate::stringFromColumnIndex($index)] = NumberFormat::FORMAT_DATE_YYYYMMDD;
            }

            $index++;
        }

        return $format;
    }
}
