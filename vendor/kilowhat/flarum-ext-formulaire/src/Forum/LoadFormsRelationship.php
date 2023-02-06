<?php

namespace Kilowhat\Formulaire\Forum;

use Flarum\Api\Controller\ShowForumController;
use Flarum\Http\RequestUtil;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Psr\Http\Message\ServerRequestInterface;

class LoadFormsRelationship
{
    protected $repository;

    public function __construct(FormRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(ShowForumController $controller, &$data, ServerRequestInterface $request)
    {
        $actor = RequestUtil::getActor($request);

        // We want to load up the fields only for guests
        // Though technically everyone can create new users
        // We will also include admins so that users of clarkwinkelmann/create-user-modal get access to the fields
        if ($actor->isGuest() || $actor->isAdmin()) {
            $data['formulaireSignUpForms'] = $this->repository->forSignUp($actor);
        } else {
            $data['formulaireSignUpForms'] = [];
        }

        // startDiscussion will be checked again on each tag
        // This check is mostly for performance and also takes care of skipping the non-tag-scoped forms
        if ($actor->can('startDiscussion')) {
            $data['formulaireComposerForms'] = $this->repository->forComposer($actor);
        } else {
            $data['formulaireComposerForms'] = [];
        }
    }
}
