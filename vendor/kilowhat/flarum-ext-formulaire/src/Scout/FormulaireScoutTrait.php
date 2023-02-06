<?php

namespace Kilowhat\Formulaire\Scout;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Kilowhat\Formulaire\Submission;
use Kilowhat\Formulaire\TemplateRenderer;

trait FormulaireScoutTrait
{
    protected function linkedModel(Collection $forms, Collection $submissions): array
    {
        $submissionsByForm = $submissions->keyBy('form_id');

        $formData = [];

        foreach ($forms as $form) {
            /**
             * @var Submission $submission
             */
            $submission = $submissionsByForm->get($form->id);

            if (!$submission) {
                continue;
            }

            $submissionData = $this->formatSubmission($submission->data, $form->template);

            if (count($submissionData)) {
                $formData[$form->uid] = $submissionData;
            }
        }

        if (count($formData)) {
            return [
                'forms' => $formData,
            ];
        }

        return [];
    }

    protected function formatSubmission(array $data, array $template): array
    {
        $output = [];

        foreach ($data as $key => $value) {
            $definition = Arr::first($template, function ($field) use ($key) {
                return Arr::get($field, 'key') === $key;
            });

            // Do not include unknown fields
            if (!$definition) {
                continue;
            }

            if (Arr::get($definition, 'rich')) {
                $output[$key] = strip_tags(TemplateRenderer::renderRichTextAnswer($definition, $value));
            } else if (in_array(Arr::get($definition, 'type'), ['short', 'long', 'number', 'date'])) {
                $output[$key] = $value;
            } else if (Arr::get($definition, 'type') === 'checkbox') {
                $output[$key] = TemplateRenderer::mapOptionsAnswer($definition, $value);
            } else if (in_array(Arr::get($definition, 'type'), ['radio', 'select'])) {
                $output[$key] = Arr::first(TemplateRenderer::mapOptionsAnswer($definition, $value));
            } else if (Arr::get($definition, 'type') === 'items') {
                if (is_array($value) && count($value)) {
                    $output[$key] = array_map(function ($data) use ($definition) {
                        return $this->formatSubmission($data, (array)Arr::get($definition, 'fields'));
                    }, $value);
                }
            }
        }

        return $output;
    }
}
