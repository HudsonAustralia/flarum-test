<?php

namespace Kilowhat\Formulaire;

use Flarum\Formatter\Formatter;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Formatter\AnswerContext;
use Kilowhat\Formulaire\Formatter\FieldContext;

class TemplateRenderer
{
    public function prepareTemplate(array $fields, User $actor): array
    {
        return array_map(function ($field) use ($actor) {
            if (Arr::exists($field, 'content')) {
                /**
                 * @var Formatter $formatter
                 */
                $formatter = resolve(Formatter::class);

                $context = new FieldContext($actor, $field);
                $field['contentHtml'] = $formatter->render($field['content'], $context);
                $field['content'] = $formatter->unparse($field['content'], $context);
            }

            $fillGroupIds = Arr::get($field, 'fillGroupIds');

            if ($fillGroupIds !== null && !GroupIdHelper::userIsInOneOfTheGroups($actor, $fillGroupIds)) {
                $field['cannotFill'] = true;
            }

            return $field;
        }, $fields);
    }

    public function prepareSubmission(array $submissions, array $template): array
    {
        $preparedSubmissions = [];

        // Using array_map would be prettier but it's unnecessary complicated to keep the keys
        foreach ($submissions as $key => $value) {
            $field = Arr::first($template, function ($field) use ($key) {
                return Arr::get($field, 'key') === $key;
            });

            $preparedSubmission = [
                'value' => $value,
            ];

            if (Arr::get($field, 'type') === 'items') {
                $preparedSubmission = [
                    'value' => array_map(function ($row) use ($field) {
                        return $this->prepareSubmission($row, Arr::get($field, 'fields', []));
                    }, $value),
                ];
            } else if (Arr::get($field, 'rich')) {
                $preparedSubmission = [
                    'value' => self::unparseRichTextAnswer($field, $value),
                    'html' => self::renderRichTextAnswer($field, $value),
                ];
            }

            $preparedSubmissions[$key] = $preparedSubmission;
        }

        return $preparedSubmissions;
    }

    /**
     * A re-usable method to generate the unparsed version of a rich text field answer
     * @param array $field The field definition from the template
     * @param mixed $value The raw value as stored in the submission data
     * @return string
     */
    public static function unparseRichTextAnswer(array $field, $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        /**
         * @var Formatter $formatter
         */
        $formatter = resolve(Formatter::class);

        return $formatter->unparse($value, new AnswerContext($field));
    }

    /**
     * A re-usable method to generate the HTML version of a rich text field answer
     * @param array $field The field definition from the template
     * @param mixed $value The raw value as stored in the submission data
     * @return string
     */
    public static function renderRichTextAnswer(array $field, $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        /**
         * @var Formatter $formatter
         */
        $formatter = resolve(Formatter::class);

        return $formatter->render($value, new AnswerContext($field));
    }

    /**
     * A re-usable method to generate the list of human-readable option output.
     * The keys will be matched to their labels or kept as-is for "other" input
     * @param array $field The field definition from the template
     * @param mixed $value The raw value as stored in the submission data
     * @return array
     */
    public static function mapOptionsAnswer(array $field, $value): array
    {
        $options = Arr::get($field, 'options');

        // Generally, if this method is called the field would have an options definition
        // But if it's missing we'll just return the raw answers
        if (!is_array($options)) {
            return (array)$value;
        }

        $optionsMap = Arr::pluck($options, 'title', 'key');

        return array_map(function ($value) use ($optionsMap) {
            $title = is_string($value) ? Arr::get($optionsMap, $value) : null;

            return $title ?: $value;
        }, (array)$value);
    }
}
