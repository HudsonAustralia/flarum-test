<?php

namespace Kilowhat\Formulaire\Validators;

use Carbon\Carbon;
use Flarum\Formatter\Formatter;
use Flarum\Locale\Translator;
use Flarum\User\User;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Kilowhat\Formulaire\File;
use Kilowhat\Formulaire\GroupIdHelper;
use Ramsey\Uuid\Uuid;

class TemplateValidator
{
    protected $validator;
    protected $translator;
    public $messages;

    /**
     * @var File[]
     */
    public $files = [];

    public function __construct(Factory $validator, Translator $translator)
    {
        $this->validator = $validator;
        $this->translator = $translator;
        $this->messages = new MessageBag();
    }

    protected function mergeAndPrefixMessages(MessageBag $messages, string $prefix = '')
    {
        $this->messages->merge(collect($messages->messages())->mapWithKeys(function ($message, $key) use ($prefix) {
            return [
                $prefix . $key => $message,
            ];
        })->all());
    }

    protected function trans(string $key, array $parameters = []): string
    {
        return $this->translator->trans('kilowhat-formulaire.api.' . $key, $parameters);
    }

    public function cleanAndValidateTemplate(array $fields, string $errorPrefix = '', int $depth = 0): array
    {
        $this->messages = new MessageBag();

        $uniqueKeys = [];

        return array_map(function ($field) use (&$uniqueKeys, $errorPrefix, $depth) {
            if (!Arr::get($field, 'key')) {
                $field['key'] = Uuid::uuid4()->toString();
            }

            $content = Arr::get($field, 'content');
            if (is_string($content)) {
                /**
                 * @var Formatter $formatter
                 */
                $formatter = resolve(Formatter::class);

                try {
                    $field['content'] = $formatter->parse($content);
                } catch (\Exception $exception) {
                    $this->messages->add($errorPrefix . $field['key'], $this->trans('formatter-parsing-error', [
                        'message' => $exception->getMessage()
                    ]));
                }
            }

            if (Arr::exists($field, 'options')) {
                foreach ((array)$field['options'] as $i => $option) {
                    if (is_array($option) && !Arr::get($option, 'key')) {
                        $field['options'][$i]['key'] = Uuid::uuid4()->toString();
                    }
                }
            }

            $allowedTypes = [
                'checkbox',
                'content',
                'date',
                'long',
                'number',
                'radio',
                'select',
                'short',
            ];

            if ($depth === 0) {
                $allowedTypes[] = 'items';
                $allowedTypes[] = 'upload';
            }

            $rules = [
                'key' => 'required|string',
                'type' => ['required', Rule::in($allowedTypes)],
                'title' => 'sometimes|string',
                'description' => 'sometimes|string',
                'required' => 'sometimes|boolean',
                'fillGroupIds' => 'sometimes|array', //TODO: array of IDs
            ];

            if (in_array(Arr::get($field, 'type'), ['checkbox', 'items', 'long', 'number', 'upload'])) {
                $rules += [
                    'min' => 'sometimes|integer|min:1',
                    'max' => 'sometimes|integer|min:1',
                ];
            }

            if (in_array(Arr::get($field, 'type'), ['checkbox', 'radio', 'select'])) {
                $rules += [
                    'options' => 'array',
                    'options.*.key' => 'required|string',
                    'options.*.title' => 'required|string',
                ];
            }

            if (in_array(Arr::get($field, 'type'), ['checkbox', 'radio'])) {
                $rules += [
                    'other' => 'sometimes|boolean',
                ];
            }

            if (Arr::get($field, 'type') === 'content') {
                $rules += [
                    'content' => 'sometimes|string',
                ];
            }

            if (Arr::get($field, 'type') === 'date') {
                $rules += [
                    'min' => 'sometimes|date_format:Y-m-d',
                    'max' => 'sometimes|date_format:Y-m-d',
                ];
            }

            if (Arr::get($field, 'type') === 'items') {
                $rules += [
                    'fields' => 'sometimes', // We just add it here so it's then whitelisted below
                ];

                $field['fields'] = $this->cleanAndValidateTemplate(Arr::get($field, 'fields', []), $errorPrefix . $field['key'] . '/', $depth + 1);
            }

            if (Arr::get($field, 'type') === 'long') {
                $rules += [
                    'rich' => 'sometimes|boolean',
                ];
            }

            if (Arr::get($field, 'type') === 'number') {
                $rules += [
                    'integer' => 'sometimes|boolean',
                ];
            }

            if (Arr::get($field, 'type') === 'short') {
                $rules += [
                    'email' => 'sometimes|boolean',
                    'regex' => 'sometimes|string',
                ];
            }

            if (Arr::get($field, 'type') === 'upload') {
                $rules += [
                    'mime' => 'sometimes|string',
                ];
            }

            foreach ($rules as $key => $value) {
                // "cast" null/false/empty values to missing keys
                if (Arr::get($field, $key) === null || Arr::get($field, $key) === false || Arr::get($field, $key) === '') {
                    Arr::forget($field, $key);
                }
            }

            $validator = $this->validator->make($field, $rules);

            $this->mergeAndPrefixMessages($validator->errors(), $errorPrefix);

            if (in_array($field['key'], $uniqueKeys)) {
                $this->messages->add($errorPrefix . $field['key'], $this->trans('duplicate-field-key'));
            }

            $uniqueKeys[] = $field['key'];

            return Arr::only($field, array_keys($rules));
        }, $fields);
    }

    public function cleanAndValidateSubmission(array $submissions, array $template, User $actor, array $previousSubmissions = [], string $errorPrefix = ''): array
    {
        $this->messages = new MessageBag();

        $removeValueWrappers = array_map(function ($submission) {
            if (is_array($submission) && Arr::exists($submission, 'value')) {
                return $submission['value'];
            }

            return $submission;
        }, $submissions);

        // Using a different array for keys to keep and rules, because we want to validate only those that changed, but keep all those that exist
        $keysToKeep = [];
        $rules = [];
        $customAttributes = [];

        foreach ($template as $field) {
            $key = Arr::get($field, 'key');
            $rulesForField = [];

            if (Arr::get($field, 'required')) {
                $rulesForField[] = 'required';
            } else {
                $rulesForField[] = 'sometimes';
            }

            $keysToKeep[] = $key;

            $newValue = Arr::get($removeValueWrappers, $key);

            if (Arr::get($field, 'rich') && is_string($newValue)) {
                /**
                 * @var Formatter $formatter
                 */
                $formatter = resolve(Formatter::class);

                try {
                    $removeValueWrappers[$key] = $formatter->parse($newValue);
                } catch (\Exception $exception) {
                    $this->messages->add($errorPrefix . $key, $this->trans('formatter-parsing-error', [
                        'message' => $exception->getMessage()
                    ]));
                }
            }

            // If a value has not changed since the previous save, we don't validate it again
            // This allows us to easily skip permission checks when a value was not modified by the current actor
            // Arr::exists check is important for null values, otherwise the fields wouldn't validate on new entries
            if (
                (is_string($newValue) || is_numeric($newValue) || is_null($newValue) || is_array($newValue)) &&
                Arr::exists($previousSubmissions, $key) &&
                json_encode($newValue) === json_encode(Arr::get($previousSubmissions, $key))
            ) {
                continue;
            }

            $fillGroupIds = Arr::get($field, 'fillGroupIds');
            if ($fillGroupIds !== null && !GroupIdHelper::userIsInOneOfTheGroups($actor, $fillGroupIds)) {
                $this->messages->add($errorPrefix . $key, $this->trans('not-allowed-to-fill-field'));
            }

            switch (Arr::get($field, 'type')) {
                case 'items':
                    $cleanedRows = [];

                    foreach (Arr::get($removeValueWrappers, $key, []) as $index => $submissionRow) {
                        $cleanedRows[$index] = $this->cleanAndValidateSubmission(
                            $submissionRow,
                            Arr::get($field, 'fields', []),
                            $actor,
                            Arr::get($previousSubmissions, $key . '.' . $index, []),
                            $errorPrefix . $key . '/' . $index . '/'
                        );
                    }

                    $removeValueWrappers[$key] = $cleanedRows;
                    $rulesForField[] = 'array';

                    break;
                case 'upload':
                    foreach ((array)$newValue as $fileUid) {
                        /**
                         * @var $file File
                         */
                        $file = File::where('uid', $fileUid)->first();
                        if (!$file) {
                            $this->messages->add($errorPrefix . $key, 'File ' . $fileUid . ' not found');
                        } else if ($file->validated_for_field_key !== $key) {
                            // The check for validated_for_form_id has to happen in SubmissionRepository because this function doesn't know the form ID
                            $this->messages->add($errorPrefix . $key, 'File ' . $fileUid . ' not validated for field');
                        } else {
                            $this->files[] = $file;
                        }
                    }

                    $rulesForField[] = 'array';

                    break;
                case 'checkbox':
                    $rulesForField[] = 'array';

                    break;
                case 'radio':
                case 'select':
                    $rulesForField[] = 'array';
                    $rulesForField[] = 'max:1';

                    break;
                case 'date':
                    if ($newValue) {
                        // Try fixing the date format if it can be parsed by Carbon
                        // Just in case some browser decides to handle them differently or shows a text field
                        try {
                            $date = Carbon::parse($newValue);

                            $removeValueWrappers[$key] = $date->format('Y-m-d');
                        } catch (\Exception $exception) {
                            // Silence errors
                        }
                    }

                    if (Arr::get($field, 'min')) {
                        $rulesForField[] = 'after_or_equal:' . Arr::get($field, 'min');
                    }

                    if (Arr::get($field, 'max')) {
                        $rulesForField[] = 'before_or_equal:' . Arr::get($field, 'max');
                    }

                    $rulesForField[] = 'date_format:Y-m-d';
                    break;
                case 'number':
                    // TODO: cast numbers before save
                    if (Arr::get($field, 'integer')) {
                        $rulesForField[] = 'integer';
                    } else {
                        $rulesForField[] = 'numeric';
                    }
                    break;
                default:
                    $rulesForField[] = 'string';

                    if (Arr::get($field, 'email')) {
                        $rulesForField[] = 'email';
                    }

                    if ($regex = Arr::get($field, 'regex')) {
                        $rulesForField[] = 'regex:' . $regex;
                    }

                    // We only apply default max values if none is provided
                    // So it's possible to exceed the default with a custom max
                    if (!Arr::exists($field, 'max')) {
                        if (Arr::get($field, 'type') === 'long') {
                            $rulesForField[] = 'max:25000';
                        } else {
                            $rulesForField[] = 'max:255';
                        }
                    }
            }

            if (in_array(Arr::get($field, 'type'), ['checkbox', 'items', 'long', 'number', 'upload'])) {
                if (Arr::exists($field, 'min')) {
                    $rulesForField[] = 'min:' . Arr::get($field, 'min');
                }

                if (Arr::exists($field, 'max')) {
                    $rulesForField[] = 'max:' . Arr::get($field, 'max');
                }
            }

            // For the values that are array-based, we want to remove duplicates so the min/max checks can't be cheated
            if (Arr::exists($field, 'options') || Arr::get($field, 'type') === 'upload') {
                // If it's not an array, it won't work, and the validation will fail anyway
                if (is_array($newValue)) {
                    $removeValueWrappers[$key] = array_unique($newValue);
                }
            }

            $rules[$key] = $rulesForField;

            if ($title = Arr::get($field, 'title')) {
                $customAttributes[$key] = $title;
            }

            // We can do this after the other rules because it uses a separate validator
            // And also needs access to the custom field names
            if (Arr::exists($field, 'options')) {
                $allowOther = Arr::get($field, 'other');
                $optionRule = Rule::in(Arr::pluck((array)Arr::get($field, 'options'), 'key'));
                $lastIndex = count((array)$newValue) - 1;

                foreach ((array)$newValue as $index => $option) {
                    $rule = $optionRule;

                    // Apply a different validation rule to the last item, which might be the "other" value
                    if ($index === $lastIndex && $allowOther) {
                        $rule = 'required|string|max:255';
                    }

                    $validator = $this->validator->make([
                        $key => $option
                    ], [
                        $key => $rule,
                    ], [], $customAttributes);

                    $this->mergeAndPrefixMessages($validator->errors(), $errorPrefix);
                }
            }
        }

        $validator = $this->validator->make($removeValueWrappers, $rules, [], $customAttributes);

        $this->mergeAndPrefixMessages($validator->errors(), $errorPrefix);

        return Arr::only($removeValueWrappers, $keysToKeep);
    }
}
