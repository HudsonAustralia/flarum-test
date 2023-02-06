<?php

namespace Kilowhat\Formulaire\Submission\Search\Gambits;

use Flarum\Search\AbstractRegexGambit;
use Flarum\Search\SearchState;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Kilowhat\Formulaire\Repositories\FormRepository;

class FormGambit extends AbstractRegexGambit
{
    protected $forms;

    public function __construct(FormRepository $forms)
    {
        $this->forms = $forms;
    }

    protected function getGambitPattern(): string
    {
        return 'form:(.+)';
    }

    protected function conditions(SearchState $search, array $matches, $negate)
    {
        try {
            $form = $this->forms->findOrFail($matches[1], $search->getActor());

            $search->getQuery()->where('formulaire_submissions.form_id', $negate ? '!=' : '=', $form->id);
        } catch (ModelNotFoundException $exception) {
            $search->getQuery()->whereRaw('1=0');
        }
    }
}
