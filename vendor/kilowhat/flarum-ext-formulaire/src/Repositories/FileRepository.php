<?php

namespace Kilowhat\Formulaire\Repositories;

use Carbon\Carbon;
use Flarum\Foundation\Paths;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\File;
use Kilowhat\Formulaire\Form;
use Kilowhat\Formulaire\GroupIdHelper;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileRepository
{
    protected $paths;
    protected $uploader;
    protected $validator;
    protected $settings;
    protected $translator;

    public function __construct(Paths $paths, FileUploader $uploader, Factory $validator, SettingsRepositoryInterface $settings, Translator $translator)
    {
        $this->paths = $paths;
        $this->uploader = $uploader;
        $this->validator = $validator;
        $this->settings = $settings;
        $this->translator = $translator;
    }

    public function query(): Builder
    {
        return File::query();
    }

    public function delete(File $file)
    {
        $this->uploader->remove($file);

        $file->delete();
    }

    /**
     * @param Form $form
     * @param UploadedFileInterface[][] $uploadedFiles
     * @param User $actor
     * @return File[]
     * @throws \Exception
     */
    public function upload(Form $form, array $uploadedFiles, User $actor): array
    {
        $actor->assertCan('uploadFile', $form);

        $maxFileSize = $this->settings->get('formulaire.maxFileSize') ?: 2048;

        $files = [];

        foreach ($uploadedFiles as $key => $uploadedFilesForKey) {
            $fieldDefinition = Arr::first($form->template, function ($field) use ($key) {
                return Arr::get($field, 'key') === $key;
            });

            if (!$fieldDefinition) {
                throw new ValidationException([
                    'upload' => 'Unknown field ' . $key,
                ]);
            }

            if (Arr::get($fieldDefinition, 'type') !== 'upload') {
                throw new ValidationException([
                    'upload' => 'Field ' . $key . ' is not an upload field',
                ]);
            }

            $fillGroupIds = Arr::get($fieldDefinition, 'fillGroupIds');
            if ($fillGroupIds !== null && !GroupIdHelper::userIsInOneOfTheGroups($actor, $fillGroupIds)) {
                throw new ValidationException([
                    'upload' => $this->translator->trans('kilowhat-formulaire.api.not-allowed-to-fill-field'),
                ]);
            }

            /**
             * @var $uploadedFile UploadedFileInterface
             */
            foreach ((array)$uploadedFilesForKey as $uploadedFile) {
                $tmpFile = tempnam($this->paths->storage . '/tmp', 'formulaire');
                $uploadedFile->moveTo($tmpFile);

                try {
                    $validationFile = new UploadedFile(
                        $tmpFile,
                        $uploadedFile->getClientFilename(),
                        $uploadedFile->getClientMediaType(),
                        $uploadedFile->getError(),
                        true
                    );

                    $validator = $this->validator->make([
                        'file' => $validationFile,
                    ], [
                        'file' => [
                            'required',
                            'max:' . $maxFileSize,
                            'mimetypes:' . Arr::get($fieldDefinition, 'mime'),
                        ],
                    ]);
                    $validator->validate();

                    $file = new File();
                    $file->uid = Uuid::uuid4()->toString();
                    $file->user()->associate($actor);
                    $file->validatedForForm()->associate($form);
                    $file->validated_for_field_key = $key;
                    $file->filename = $uploadedFile->getClientFilename();
                    $file->size = $uploadedFile->getSize();

                    $this->uploader->upload($file, $tmpFile);

                    $file->save();

                    $files[] = $file;
                } finally {
                    @unlink($tmpFile);
                }
            }
        }

        return $files;
    }

    public function cleanUnused(): int
    {
        $total = 0;

        $this->query()
            // Clear all files that were never associated with a submission or for which the submission has been deleted
            // No need to check validated_for_form_id because if the form no longer exists, the submission must also no longer exist
            ->whereNull('submission_id')
            // Delete after 24h to leave users some time to associate the file with a submission
            ->where('created_at', '<', Carbon::now()->subDay())
            ->chunk(100, function (Collection $files) use (&$total) {
                $total += $files->count();

                foreach ($files as $file) {
                    $this->delete($file);
                }
            });

        return $total;
    }
}
