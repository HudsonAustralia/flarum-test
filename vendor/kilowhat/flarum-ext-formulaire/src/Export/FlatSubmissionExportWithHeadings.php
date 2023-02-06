<?php

namespace Kilowhat\Formulaire\Export;

use Maatwebsite\Excel\Concerns\WithHeadings;

class FlatSubmissionExportWithHeadings extends FlatSubmissionExport implements WithHeadings
{
    public function headings(): array
    {
        $headings = [];

        foreach ($this->fields as $field) {
            $headings[] = $this->getFieldHeading($field);
        }

        foreach ($this->meta as $attribute) {
            $headings[] = $this->getMetaHeading($attribute);
        }

        return $headings;
    }
}
