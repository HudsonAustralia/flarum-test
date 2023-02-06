<?php

namespace Kilowhat\Formulaire\Repositories;

use Flarum\User\User;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Export\FlatSubmissionExport;
use Kilowhat\Formulaire\Export\FlatSubmissionExportWithHeadings;
use Kilowhat\Formulaire\Export\JsonSubmissionExport;
use Kilowhat\Formulaire\Export\TemporaryStreamWriter;
use Kilowhat\Formulaire\Form;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\Stream;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ExportRepository
{
    protected $writer;

    public function __construct(TemporaryStreamWriter $writer)
    {
        $this->writer = $writer;
    }

    public function export(Form $form, array $params, User $actor = null): ResponseInterface
    {
        $format = Arr::get($params, 'format');

        $flatWriterType = null;
        $contentType = null;

        if ($format === 'json') {
            $exportClassName = JsonSubmissionExport::class;
        } else {
            switch ($format) {
                case 'xls':
                    $flatWriterType = Excel::XLS;
                    $contentType = 'application/vnd.ms-excel';
                    break;
                case 'ods':
                    $flatWriterType = Excel::ODS;
                    $contentType = 'application/vnd.oasis.opendocument.spreadsheet';
                    break;
                case 'csv':
                    $flatWriterType = Excel::CSV;
                    $contentType = 'text/csv';
                    break;
                default:
                    $flatWriterType = Excel::XLSX;
                    $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                    $format = 'xlsx'; // Force extension
            }

            if (Arr::get($params, 'heading') === 'none') {
                $exportClassName = FlatSubmissionExport::class;
            } else {
                $exportClassName = FlatSubmissionExportWithHeadings::class;
            }
        }

        if (Arr::exists($params, 'fields')) {
            $fields = explode(',', Arr::get($params, 'fields'));
        } else {
            // Take all field keys. Skip null values which are for content blocks
            $fields = Arr::where(Arr::pluck($form->template ?? [], 'key'), function ($value) {
                return $value !== null;
            });
        }

        if (Arr::exists($params, 'meta')) {
            $meta = array_intersect(explode(',', Arr::get($params, 'meta')), [
                'id',
                'uid',
                'user_id',
                'user_username',
                'user_displayname',
                'user_email',
                'user_activated',
                'link_id',
                'locked_at',
                'created_at',
                'updated_at',
                'hidden_at',
            ]);
        } else {
            $meta = ['uid', 'user_username', 'created_at'];
        }

        if ($actor && count(array_intersect($meta, [
                'user_email',
                'user_activated',
            ]))) {
            $actor->assertCan('exportUserDetails', $form);
        }

        // optionKeys is a boolean parameter, defaults to false
        $optionKeys = (bool)Arr::get($params, 'optionKeys');

        $export = new $exportClassName(
            $form,
            $fields,
            $meta,
            Arr::get($params, 'heading') ?? 'title',
            $optionKeys
        );

        if (Arr::get($params, 'preview')) {
            $export->usePreview();

            if ($format === 'json') {
                return new JsonResponse($export->query()->get()->map(function ($model) use ($export) {
                    return $export->map($model);
                }));
            } else if ($format === 'csv') {
                return new TextResponse(new Stream($this->writer->exportTemporaryStream($export, $flatWriterType)));
            } else {
                $headings = $export instanceof WithHeadings ? [$export->headings()] : [];

                // For xls(x)/ods, we manually craft the output as an array so we can preview it in the frontend
                return new JsonResponse(array_merge($headings, $export->query()->get()->map(function ($model) use ($export) {
                    return $export->map($model);
                })->all()));
            }
        }

        $filename = $form->private_title ?? $form->title;

        // Trying to replicate what Laravel's Request::download does
        $headers = [
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, "$filename.$format", "export.$format"),
        ];

        if ($format === 'json') {
            return new JsonResponse($export->query()->get()->map(function ($model) use ($export) {
                return $export->map($model);
            }), 200, $headers);
        }

        $headers['Content-Type'] = $contentType;

        return new Response($this->writer->exportTemporaryStream($export, $flatWriterType), 200, $headers);
    }
}
