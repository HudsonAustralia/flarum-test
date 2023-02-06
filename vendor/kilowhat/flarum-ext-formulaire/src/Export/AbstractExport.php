<?php

namespace Kilowhat\Formulaire\Export;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Form;
use Kilowhat\Formulaire\Submission;
use Kilowhat\Formulaire\TemplateRenderer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * An abstract class we will use as the base of both Excel-based export and JSON export
 */
abstract class AbstractExport
{
    protected $form;
    protected $fields = [];
    protected $meta;
    protected $preview = false;
    protected $heading;
    protected $optionKeys;

    public function __construct(Form $form, array $fields, array $meta, string $heading, bool $optionKeys)
    {
        $this->form = $form;
        $this->meta = $meta;
        $this->heading = $heading;
        $this->optionKeys = $optionKeys;

        foreach ($fields as $key) {
            // Extra check against null values which could cause content blocks to be returned below
            // And content block create issues because they have no keys
            if (!$key) {
                continue;
            }

            $definition = Arr::first($this->form->template, function ($field) use ($key) {
                return Arr::get($field, 'key') === $key;
            });

            // Do not export unknown fields
            if (!$definition) {
                continue;
            }

            $style = null;

            if (Arr::get($definition, 'type') === 'items') {
                $format = function ($value) {
                    return json_encode($value);
                };
            } else if (Arr::get($definition, 'type') === 'checkbox') {
                $format = function ($value) use ($definition) {
                    return implode(',', $this->mapOptionValues($definition, $value));
                };
            } else if (Arr::get($definition, 'type') === 'upload') {
                $format = function ($value) {
                    return implode(',', $value);
                };
            } else if (Arr::get($definition, 'type') === 'date') {
                $format = function ($value) {
                    return Date::stringToExcel($value);
                };
                $style = NumberFormat::FORMAT_DATE_YYYYMMDD;
            } else if (in_array(Arr::get($definition, 'type'), ['radio', 'select'])) {
                $format = function ($value) use ($definition) {
                    return Arr::first($this->mapOptionValues($definition, (array)$value));
                };
            } else if (Arr::get($definition, 'rich')) {
                $format = function ($value) use ($definition) {
                    return TemplateRenderer::unparseRichTextAnswer($definition, $value);
                };
            } else {
                $format = function ($value) {
                    return $value;
                };
            }

            $this->fields[] = [
                'key' => $key,
                'excelStyle' => $style,
                'excelFormat' => $format,
                'title' => Arr::get($definition, 'title'), // Used for headings
            ];
        }
    }

    protected function mapOptionValues($definition, $values, bool $isWrapped = false)
    {
        if ($this->optionKeys) {
            return $values;
        }

        // Try to replace keys with their corresponding titles
        if (Arr::exists($definition, 'options') && is_array($values)) {
            if ($isWrapped) {
                $values['options'] = TemplateRenderer::mapOptionsAnswer($definition, Arr::get($values, 'value') ?: []);
            } else {
                return TemplateRenderer::mapOptionsAnswer($definition, $values);
            }
        }

        return $values;
    }

    public function usePreview()
    {
        $this->preview = true;
    }

    public function query(): HasMany
    {
        $query = $this->form->submissions()->with([
            'user',
        ])->orderBy('created_at');

        if ($this->preview) {
            $query->limit(5);
        }

        return $query;
    }

    protected function getMetaValue(Submission $submission, string $attribute)
    {
        /**
         * @var $user User
         */
        $user = optional($submission->user);

        if ($attribute === 'user_username') {
            return $user->username;
        }

        if ($attribute === 'user_displayname') {
            return $user->display_name;
        }

        if ($attribute === 'user_email') {
            return $user->email;
        }

        if ($attribute === 'user_activated') {
            $activated = $user->is_email_confirmed;

            if (!is_null($activated)) {
                $activated = (bool)$activated;
            }

            return $activated;
        }

        return $submission->$attribute;
    }

    protected function getMetaHeading(string $attribute): string
    {
        if ($this->heading === 'title') {
            return resolve('translator')->trans('kilowhat-formulaire.api.export.meta.' . $attribute);
        }

        return $attribute;
    }

    protected function getFieldHeading(array $field): string
    {
        $value = $this->heading === 'title' ? $field['title'] : '';

        // Use key when heading setting is not title, but also if there's no title
        if (!$value) {
            $value = $field['key'];
        }

        return $value;
    }
}
