<?php

namespace Kilowhat\Formulaire\Formatter;

use s9e\TextFormatter\Renderer;
use s9e\TextFormatter\Utils;

/**
 * We need to change the target links to open in a new tab, otherwise you might quit the form you are currently filling.
 * We also want to remove the UGC+nofollow attributes because this content is not written by the users.
 */
class RenderTrustedContentLinks
{
    public function __invoke(Renderer $renderer, $context, string $xml): string
    {
        if ($context instanceof FieldContext) {
            return Utils::replaceAttributes($xml, 'URL', function ($attributes) {
                // This will automatically make Flarum skip the "ugc nofollow" part which we intentionally want to remove
                $attributes['rel'] = 'noopener';
                $attributes['target'] = '_blank';

                return $attributes;
            });
        }

        return $xml;
    }
}
