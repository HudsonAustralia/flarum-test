<?php

namespace Kilowhat\Formulaire\Controllers;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DebugController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // The data is hard-coded, but we return it from a controller to reduce the size of the debug content in the javascript
        return new JsonResponse([
            'configuration' => [
                [
                    'key' => 'aaa',
                    'type' => 'short',
                    'title' => 'An short text',
                ],
                [
                    'key' => 'bbb',
                    'type' => 'short',
                    'title' => 'A short text with regex',
                    'description' => 'Required and must contain an a',
                    'required' => true,
                    'regex' => 'a',
                ],
                [
                    'key' => 'ccc',
                    'type' => 'short',
                    'title' => 'An email',
                    'email' => true,
                ],
                [
                    'key' => 'ddd',
                    'type' => 'long',
                    'description' => 'A long text without a title',
                ],
                [
                    'key' => 'eee',
                    'type' => 'long',
                    'title' => 'Rich text',
                    'rich' => true,
                ],
                [
                    'key' => 'fff',
                    'type' => 'date',
                    'title' => 'A date after 2019',
                    'min' => '2020-01-01',
                ],
                [
                    'key' => 'ggg',
                    'type' => 'number',
                    'title' => 'A number',
                    'max' => 5,
                ],
                [
                    'key' => 'hhh',
                    'type' => 'checkbox',
                    'title' => 'Checkboxes',
                    'options' => [
                        [
                            'key' => 'first',
                            'title' => 'First',
                        ],
                        [
                            'key' => 'second',
                            'title' => 'Second',
                        ],
                    ],
                    'other' => true,
                ],
                [
                    'key' => 'iii',
                    'type' => 'radio',
                    'title' => 'Radio',
                    'options' => [
                        [
                            'key' => 'first',
                            'title' => 'First',
                        ],
                        [
                            'key' => 'second',
                            'title' => 'Second',
                        ],
                    ],
                ],
                [
                    'key' => 'jjj',
                    'type' => 'select',
                    'title' => 'Select',
                    'options' => [
                        [
                            'key' => 'first',
                            'title' => 'First',
                        ],
                        [
                            'key' => 'second',
                            'title' => 'Second',
                        ],
                    ],
                ],
                [
                    'key' => 'kkk',
                    'type' => 'upload',
                    'title' => 'Resume',
                    'description' => 'Upload your file',
                ],
                [
                    'key' => 'lll',
                    'type' => 'items',
                    'title' => 'Qualifications',
                    'fields' => [
                        [
                            'key' => 'aaa',
                            'type' => 'short',
                            'title' => 'First name',
                            'required' => true,
                        ],
                        [
                            'key' => 'bbb',
                            'type' => 'short',
                            'title' => 'Last name',
                            'description' => 'With description',
                        ],
                    ],
                ],
                [
                    'type' => 'content',
                    'content' => 'Hello World',
                ],
            ],
            'submission' => [
                'aaa' => [
                    'value' => 'Hello World',
                ],
                'eee' => [
                    'value' => 'Some **bold** and a [link](https://example.com/)',
                    'html' => '<p>Some <strong>bold</strong> and a <a href="https://example.com/" rel=" nofollow ugc">link</a></p>',
                ],
                'hhh' => [
                    'value' => [
                        'first',
                    ],
                ],
                'iii' => [
                    'value' => [
                        'second',
                    ],
                ],
                'kkk' => [
                    'value' => [
                        'debug-file-uid',
                    ],
                ],
                'lll' => [
                    'value' => [
                        [
                            'aaa' => [
                                'value' => 'Myself',
                            ],
                        ],
                        [
                            'aaa' => [
                                'value' => 'John',
                            ],
                            'bbb' => [
                                'value' => 'Doe',
                            ],
                        ],
                    ],
                ],
            ],
            'errors' => [
                'ccc' => [
                    'errors' => [
                        'The test field is required.',
                    ],
                ],
                'lll' => [
                    '1' => [
                        'bbb' => [
                            'errors' => [
                                'Another test error message.',
                            ],
                        ],
                    ],
                ],
            ],
            'store' => [
                [
                    'id' => 'debug-file-uid',
                    'type' => 'formulaire-files',
                    'attributes' => [
                        'filename' => 'test.pdf',
                        'humanSize' => '32.1kB',
                        'url' => 'https://example.com/',
                    ],
                ],
            ],
        ]);
    }
}
