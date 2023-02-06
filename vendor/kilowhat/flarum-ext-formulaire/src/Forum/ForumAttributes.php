<?php

namespace Kilowhat\Formulaire\Forum;

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Extension\ExtensionManager;
use Flarum\Group\Permission;
use Flarum\Settings\SettingsRepositoryInterface;

class ForumAttributes
{
    protected $settings;
    protected $extensionManager;

    public function __construct(SettingsRepositoryInterface $settings, ExtensionManager $extensionManager)
    {
        $this->settings = $settings;
        $this->extensionManager = $extensionManager;
    }

    public function __invoke(ForumSerializer $serializer): array
    {
        $actor = $serializer->getActor();
        $attributes = [];

        $attributes['formulaireShowHistoryControls'] = (bool)$this->settings->get('formulaire.historyControls');
        $attributes['formulaireShowSideNavOnSubmission'] = (bool)$this->settings->get('formulaire.sideNavOnSubmission');
        $attributes['formulaireCheckboxStyle'] = $this->settings->get('formulaire.checkboxStyle') ?: 'far-square';
        $attributes['formulaireRadioStyle'] = $this->settings->get('formulaire.radioStyle') ?: 'far-circle';

        $horizontalLayout = [];

        foreach ([
                     'StandaloneEdit' => false,
                     'StandaloneView' => false,
                     'ProfileEdit' => false,
                     'ProfileView' => false,
                     'SignUp' => false,
                     'DiscussionEdit' => true,
                     'DiscussionView' => true,
                     'DiscussionComposer' => true,
                 ] as $horizontalLayoutSetting => $defaultValue) {
            $value = $this->settings->get("formulaire.horizontalLayout$horizontalLayoutSetting");
            if ($defaultValue ? ($value !== '0') : ($value === '1')) {
                $horizontalLayout[] = $horizontalLayoutSetting;
            }
        }

        $attributes['formulaireHorizontalLayout'] = $horizontalLayout;
        $attributes['formulaireUniformComposerLayout'] = (bool)$this->settings->get('formulaire.uniformComposerLayout');

        if ($actor->hasPermission('formulaire.create') || $actor->hasPermission('formulaire.moderate')) {
            $attributes['formulaireCanManage'] = true;
            $attributes['formulaireCanSearchSubmissions'] = $this->extensionManager->isEnabled('clarkwinkelmann-scout');
        }

        if ($actor->hasPermission('formulaire.moderate')) {
            $PERMISSIONS = [
                'formulaire.fill',
                'formulaire.seeOwnUser',
                'formulaire.seeAnyUser',
                'formulaire.editOwnUser',
                'formulaire.editAnyUser',
                'discussion.seeOwnFormulaire',
                'discussion.seeAnyFormulaire',
                'discussion.editOwnFormulaire',
                'discussion.editAnyFormulaire',
            ];

            $permissions = [];

            foreach ($PERMISSIONS as $PERMISSION) {
                $permissions[$PERMISSION] = [];
            }

            foreach (Permission::query()->whereIn('permission', $PERMISSIONS)->get() as $permission) {
                $permissions[$permission->permission][] = (string)$permission->group_id;
            }

            $attributes['formulaireGlobalPermissions'] = $permissions;
        }

        return $attributes;
    }
}
