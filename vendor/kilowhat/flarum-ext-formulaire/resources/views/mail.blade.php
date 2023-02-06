<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style type="text/css">
        body {
            font-family: 'Open Sans', sans-serif;
            background: white;
            color: #426799;
            margin: 0;
            padding: 0;
        }

        .content {
            box-sizing: border-box;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            padding: 10px 20px;
        }

        .header {
            border-bottom: 1px solid #e8ecf3;
        }

        .header a, .header a:visited {
            color: {{ $settings->get('theme_primary_color') }};
            text-decoration: none;
        }

        .footer {
            background: #e8ecf3;
        }

        dt {
            font-weight: bold;
        }

        {{-- Must include a in the selector to be more specific than Gmail default style. The .Block rule is for clients that remove classes from links --}}
        a.Button, .ButtonBlock a, .ButtonLock a:visited {
            display: block;
            width: 300px;
            max-width: 100%;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            line-height: 20px;
            border-radius: 4px;
            border: 0;
            color: #fff;
            background: {{ $settings->get('theme_primary_color') }};
            font-weight: bold;
            margin: 40px auto;
            padding: 8px 20px;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="header">
    <div class="content">
        <a href="{{ resolve(\Flarum\Foundation\Config::class)->url() }}">{{ $settings->get('forum_title') }}</a>
    </div>
</div>
<div class="content">
    {!! $html !!}

    @foreach ($fields as $field)
        @php ($key = \Illuminate\Support\Arr::get($field, 'key'))
        @php ($value = \Illuminate\Support\Arr::get($submission, $key))
        @if ($key)
            <dl>
                <dt>{{ \Illuminate\Support\Arr::get($field, 'title') }}</dt>
                @if (\Illuminate\Support\Arr::get($field, 'type') === 'items')
                    <dd>{{ $translator->trans('kilowhat-formulaire.mail.sub-entries', ['{count}' => count($value)]) }}</dd>
                @elseif (\Illuminate\Support\Arr::get($field, 'type') === 'upload')
                    @forelse((array)$value as $fileUid)
                        <dd>
                            @php ($file = $files->get($fileUid))
                            @if ($file)
                                <a href="{{ $file->url() }}">{{ $file->filename }} ({{ $file->humanSize() }})</a>
                            @else
                                {{ $fileUid }}
                            @endif
                        </dd>
                    @empty
                        <dd><em>{{ $translator->trans('kilowhat-formulaire.mail.no-answer') }}</em></dd>
                    @endforelse
                @elseif (in_array(\Illuminate\Support\Arr::get($field, 'type'), ['checkbox', 'radio', 'select']))
                    @forelse(\Kilowhat\Formulaire\TemplateRenderer::mapOptionsAnswer($field, $value) as $optionLabel)
                        <dd>{{ $optionLabel }}</dd>
                    @empty
                        <dd><em>{{ $translator->trans('kilowhat-formulaire.mail.no-answer') }}</em></dd>
                    @endforelse
                @elseif ($value)
                    @if (\Illuminate\Support\Arr::get($field, 'rich'))
                        <dd>{!! \Kilowhat\Formulaire\TemplateRenderer::renderRichTextAnswer($field, $value) !!}</dd>
                    @else
                        <dd>{{ $value }}</dd>
                    @endif
                @elseif (\Illuminate\Support\Arr::get($field, 'type') !== 'content')
                    <dd><em>{{ $translator->trans('kilowhat-formulaire.mail.no-answer') }}</em></dd>
                @endif
            </dl>
        @endif
    @endforeach

    @if ($editLink)
        <div class="ButtonBlock">
            <a href="{{ $editLink }}" class="Button">{{ $translator->trans('kilowhat-formulaire.mail.edit') }}</a>
        </div>
    @endif
</div>
<div class="footer">
    <div class="content">
        <p>
            {{ $translator->trans('kilowhat-formulaire.mail.footer', ['{title}' => $settings->get('forum_title')]) }}
        </p>
    </div>
</div>
</body>
</html>
