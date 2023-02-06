<?php

namespace Kilowhat\Formulaire\Export;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Form;
use Kilowhat\Formulaire\Submission;
use Kilowhat\Formulaire\TemplateRenderer;

class JsonSubmissionExport extends AbstractExport
{
    /**
     * @var TemplateRenderer $renderer
     */
    protected $renderer;

    public function __construct(Form $form, array $fields, array $meta, string $heading, bool $optionKeys)
    {
        parent::__construct($form, $fields, $meta, $heading, $optionKeys);

        $this->renderer = resolve(TemplateRenderer::class);
    }

    public function map(Submission $submission): array
    {
        $data = [];

        // Use same formatting as the web
        $formattedSubmission = $this->renderer->prepareSubmission($submission->data ?? [], $this->form->template ?? []);

        foreach ($this->fields as $field) {
            $key = $this->ensureUniqueKey($data, $this->getFieldHeading($field));
            $definition = Arr::first($this->form->template, function ($fieldDefinition) use ($field) {
                return Arr::get($fieldDefinition, 'key') === $field['key'];
            });

            $data[$key] = $this->mapOptionValues($definition ?? [], Arr::get($formattedSubmission, $field['key']), true);
        }

        foreach ($this->meta as $attribute) {
            $value = $this->getMetaValue($submission, $attribute);

            if ($value instanceof Carbon) {
                $value = $value->toIso8601String();
            }

            $key = $this->ensureUniqueKey($data, $this->getMetaHeading($attribute));
            $data[$key] = $value;
        }

        return $data;
    }

    protected function ensureUniqueKey($data, $key): string
    {
        $suffix = '';

        while (Arr::exists($data, $key . $suffix)) {
            $suffix = ($suffix || 1) + 1;
        }

        return $key . $suffix;
    }
}
