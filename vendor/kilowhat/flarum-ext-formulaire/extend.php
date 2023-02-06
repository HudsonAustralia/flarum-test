<?php

namespace Kilowhat\Formulaire;

use ClarkWinkelmann\Scout\Extend\Scout as ScoutExtender;
use Flarum\Api\Controller;
use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Discussion\Discussion as DiscussionModel;
use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\Extend;
use Flarum\Forum\Content\User as UserContent;
use Flarum\Foundation\ErrorHandling\ExceptionHandler\ValidationExceptionHandler;
use Flarum\User\Event\Saving as UserSaving;
use Flarum\User\User as UserModel;
use Kilowhat\Formulaire\Discussion\Event\SubmissionChanged as DiscussionChanged;
use Kilowhat\Formulaire\Form\Event as FormEvent;
use Kilowhat\Formulaire\Form\Search as FormSearch;
use Kilowhat\Formulaire\Serializers\FormSerializer;
use Kilowhat\Formulaire\Serializers\SubmissionSerializer;
use Kilowhat\Formulaire\Submission\Event as SubmissionEvent;
use Kilowhat\Formulaire\Submission\Search as SubmissionSearch;
use Kilowhat\Formulaire\User\Event\SubmissionChanged as UserChanged;

$extenders = [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less')
        ->route('/formulaire', 'formulaire.forms.index')
        ->route('/formulaire/discussion', 'formulaire.forms.index.discussion')
        ->route('/formulaire/user', 'formulaire.forms.index.index')
        ->route('/forms/{id:[A-Za-z0-9_-]+}', 'formulaire.forms.show')
        ->route('/u/{username}/forms/{id:[A-Za-z0-9_-]+}', 'formulaire.profile', UserContent::class)
        ->route('/formulaire/{id:[a-f0-9-]+}', 'formulaire.forms.edit')
        ->route('/formulaire/{id:[a-f0-9-]+}/submissions', 'formulaire.submissions.index')
        ->route('/submissions/{id:[a-f0-9-]+}', 'formulaire.submissions.show')
        ->route('/formulaire-debug', 'formulaire.debug'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    // UID routes are defined with a-z instead of a-f so that more flexible UIDs can be used in the integration tests
    (new Extend\Routes('api'))
        ->get('/formulaire/debug', 'formulaire.debug', Controllers\DebugController::class)
        ->post('/formulaire/forms/{id:[a-z0-9-]+}/upload', 'formulaire.files.upload', Controllers\FileUploadController::class)
        //
        ->get('/formulaire/forms', 'formulaire.forms.index', Controllers\FormIndexController::class)
        ->post('/formulaire/forms', 'formulaire.forms.store', Controllers\FormStoreController::class)
        ->get('/formulaire/forms/{id:[A-Za-z0-9_-]+}', 'formulaire.forms.show', Controllers\FormShowController::class)
        ->patch('/formulaire/forms/{id:[a-z0-9-]+}', 'formulaire.forms.update', Controllers\FormUpdateController::class)
        ->delete('/formulaire/forms/{id:[a-z0-9-]+}', 'formulaire.forms.delete', Controllers\FormDeleteController::class)
        ->get('/formulaire/forms/{id:[a-z0-9-]+}/export.{format:[a-z0-9]+}', 'formulaire.forms.export', Controllers\SubmissionExportController::class)
        //
        ->get('/formulaire/submissions', 'formulaire.submissions.index', Controllers\SubmissionIndexController::class)
        ->post('/formulaire/forms/{id:[a-z0-9-]+}/submissions', 'formulaire.submissions.store', Controllers\SubmissionStoreController::class)
        ->get('/formulaire/submissions/{id:[a-z0-9-]+}', 'formulaire.submissions.show', Controllers\SubmissionShowController::class)
        ->patch('/formulaire/submissions/{id:[a-z0-9-]+}', 'formulaire.submissions.update', Controllers\SubmissionUpdateController::class)
        ->delete('/formulaire/submissions/{id:[a-z0-9-]+}', 'formulaire.submissions.delete', Controllers\SubmissionDeleteController::class),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    (new Extend\Console())
        ->command(Commands\CleanUnusedFilesCommand::class),

    // Workaround because Flarum tries to eager load any discussion json:api include as a relationship
    // But it's way too complicated to load this data via a relationship, so this code is only to make that eager loading not fail
    // We load some data here with a garbage relationship, and override it later in the Serializing event
    // It's quite possible 1=0 is not even used by the eager loading, resulting in actual results, but we override them later anyway
    (new Extend\Model(DiscussionModel::class))
        ->relationship('formulaireForms', function (DiscussionModel $discussion) {
            return $discussion->hasMany(Form::class, 'link_id')->whereRaw('1=0');
        })
        ->relationship('formulaireSubmissions', function (DiscussionModel $discussion) {
            return $discussion->hasMany(Submission::class, 'link_id')->whereRaw('1=0');
        }),

    (new Extend\View())
        ->namespace('kilowhat-formulaire', __DIR__ . '/resources/views'),

    (new Extend\ModelVisibility(Form::class))
        ->scope(Form\Scopes\View::class)
        ->scope(Form\Scopes\Enumerate::class, 'viewEnumerate')
        ->scope(Form\Scopes\ViewLinked::class, 'viewLinked'),

    (new Extend\ModelVisibility(Submission::class))
        ->scope(Submission\Scopes\View::class)
        ->scope(Submission\Scopes\Enumerate::class, 'viewEnumerate'),

    (new Extend\Policy())
        ->modelPolicy(DiscussionModel::class, Discussion\DiscussionPolicy::class)
        ->modelPolicy(Form::class, Form\FormPolicy::class)
        ->modelPolicy(Submission::class, Submission\SubmissionPolicy::class)
        ->modelPolicy(UserModel::class, User\UserPolicy::class),

    (new Extend\ApiSerializer(DiscussionSerializer::class))
        ->hasMany('formulaireForms', FormSerializer::class)
        ->hasMany('formulaireSubmissions', SubmissionSerializer::class)
        ->attributes(Discussion\DiscussionAttributes::class),
    (new Extend\ApiController(Controller\ShowDiscussionController::class))
        ->addInclude('formulaireForms')
        ->addInclude('formulaireSubmissions')
        ->addInclude('formulaireSubmissions.form')
        ->addInclude('formulaireSubmissions.files'),
    (new Extend\ApiController(Controller\CreateDiscussionController::class))
        ->addInclude('formulaireForms')
        ->addInclude('formulaireSubmissions')
        ->addInclude('formulaireSubmissions.form')
        ->addInclude('formulaireSubmissions.files'),
    (new Extend\ApiController(Controller\UpdateDiscussionController::class))
        ->addInclude('formulaireForms')
        ->addInclude('formulaireSubmissions')
        ->addInclude('formulaireSubmissions.form')
        ->addInclude('formulaireSubmissions.files'),

    (new Extend\ApiSerializer(UserSerializer::class))
        ->hasMany('formulaireForms', FormSerializer::class)
        ->hasMany('formulaireSubmissions', SubmissionSerializer::class)
        ->attributes(User\UserAttributes::class),
    (new Extend\ApiController(Controller\ShowUserController::class))
        ->addInclude('formulaireForms')
        ->addInclude('formulaireSubmissions')
        ->addInclude('formulaireSubmissions.form')
        ->addInclude('formulaireSubmissions.files'),
    (new Extend\ApiController(Controller\CreateUserController::class))
        ->addInclude('formulaireForms')
        ->addInclude('formulaireSubmissions')
        ->addInclude('formulaireSubmissions.form')
        ->addInclude('formulaireSubmissions.files'),
    (new Extend\ApiController(Controller\UpdateUserController::class))
        ->addInclude('formulaireForms')
        ->addInclude('formulaireSubmissions')
        ->addInclude('formulaireSubmissions.form')
        ->addInclude('formulaireSubmissions.files'),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->hasMany('formulaireComposerForms', FormSerializer::class)
        ->hasMany('formulaireSignUpForms', FormSerializer::class)
        ->attributes(Forum\ForumAttributes::class),
    (new Extend\ApiController(Controller\ShowForumController::class))
        ->addInclude('formulaireComposerForms')
        ->addInclude('formulaireSignUpForms')
        ->prepareDataForSerialization(Forum\LoadFormsRelationship::class),

    (new Extend\Event())
        ->listen(DiscussionSaving::class, Discussion\SaveDiscussion::class)
        ->listen(UserSaving::class, User\SaveUser::class),

    (new Extend\ErrorHandling())
        // Our exception extends the Flarum ValidationException so it can use the existing handler
        ->handler(ValidationExceptionFromErrorBag::class, ValidationExceptionHandler::class),

    (new Extend\SimpleFlarumSearch(FormSearch\FormSearcher::class))
        ->setFullTextGambit(FormSearch\Gambits\FullTextGambit::class)
        ->addGambit(FormSearch\Gambits\UserGambit::class)
        ->addGambit(FormSearch\Gambits\TypeGambit::class),

    (new Extend\SimpleFlarumSearch(SubmissionSearch\SubmissionSearcher::class))
        ->setFullTextGambit(SubmissionSearch\Gambits\FullTextGambit::class)
        ->addGambit(SubmissionSearch\Gambits\FormGambit::class)
        ->addGambit(SubmissionSearch\Gambits\UserGambit::class),

    (new Extend\ServiceProvider())
        ->register(Providers\ExcelServiceProvider::class)
        ->register(Providers\RelationServiceProvider::class)
        ->register(Providers\StorageServiceProvider::class),

    (new Extend\Formatter())
        ->render(Formatter\RenderTrustedContentLinks::class),
];

if (class_exists(ScoutExtender::class)) {
    $extenders = array_merge($extenders, [
        (new ScoutExtender(DiscussionModel::class))
            ->listenSaved(DiscussionChanged::class, function (DiscussionChanged $event) {
                return $event->discussion;
            })
            ->attributes(Scout\ScoutDiscussionAttributes::class),
        (new ScoutExtender(Form::class))
            ->listenSaved(FormEvent\Created::class, function (FormEvent\Created $event) {
                return $event->form;
            })
            ->listenSaved(FormEvent\Updated::class, function (FormEvent\Updated $event) {
                return $event->form;
            })
            ->listenDeleted(FormEvent\Deleted::class, function (FormEvent\Deleted $event) {
                return $event->form;
            })
            ->attributes(Scout\ScoutFormAttributes::class),
        (new ScoutExtender(Submission::class))
            ->listenSaved(SubmissionEvent\Created::class, function (SubmissionEvent\Created $event) {
                return $event->submission;
            })
            ->listenSaved(SubmissionEvent\DataChanged::class, function (SubmissionEvent\DataChanged $event) {
                return $event->submission;
            })
            ->listenDeleted(SubmissionEvent\Deleted::class, function (SubmissionEvent\Deleted $event) {
                return $event->submission;
            })
            ->attributes(Scout\ScoutSubmissionAttributes::class),
        (new ScoutExtender(UserModel::class))
            ->listenSaved(UserChanged::class, function (UserChanged $event) {
                return $event->user;
            })
            ->attributes(Scout\ScoutUserAttributes::class),
    ]);
}

return $extenders;
