<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\EnvelopeCodec;
use App\Settings\Form\MemberFields;
use App\Settings\Form\ProfileFields;
use App\Settings\Form\SettingsSeals;
use App\Settings\Repository\ProfileRepository;
use App\Staff\Repository\AccountRepository;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The settings module's page factory (#22): who may change the settings and their frame (`SettingsAccess`, the staff
 * panel as `settings`), the settings page, the profile's post and a member's page. The settings page and the profile's
 * post take its products, so the generated wiring makes one call apiece rather than spelling out the pages' graph
 * (PHPMD's coupling limit); `MemberServices` builds one for the member and link desks.
 */
final readonly class SettingsServices
{
    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param EnvelopeCodec $envelopeCodec Seals the forms' envelopes.
     * @param PageDocuments $pageDocuments The theme's documents.
     */
    public function __construct(
        private EntityStore $entityStore,
        private EnvelopeCodec $envelopeCodec,
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @return SettingsAccess Admins only, the member a route names, the refusals and the one-panel frame.
     */
    public function settingsAccess(): SettingsAccess
    {
        $staffPanels = new StaffPanels(new StaffPages($this->pageDocuments), 'settings');

        return new SettingsAccess($staffPanels, new AccountRepository($this->entityStore));
    }

    /**
     * @return SettingsPanels The settings page: the profile, the staff and the add form.
     */
    public function settingsPanels(): SettingsPanels
    {
        $settingsSeals = new SettingsSeals($this->envelopeCodec);

        return new SettingsPanels(
            new StaffPages($this->pageDocuments),
            new ProfileRepository($this->entityStore),
            new ProfileFields($settingsSeals),
            new MemberFields($settingsSeals),
            new MemberTable(new AccountRepository($this->entityStore)),
        );
    }

    /**
     * @return ProfileSaves The profile's post.
     */
    public function profileSaves(): ProfileSaves
    {
        $settingsSeals = new SettingsSeals($this->envelopeCodec);
        $profileRepository = new ProfileRepository($this->entityStore);

        $profileFields = new ProfileFields($settingsSeals);

        return new ProfileSaves($this->settingsAccess(), $this->settingsPanels(), $profileRepository, $profileFields, $settingsSeals);
    }

    /**
     * @return SettingsSeals The forms' envelopes and the links' tokens.
     */
    public function settingsSeals(): SettingsSeals
    {
        return new SettingsSeals($this->envelopeCodec);
    }

    /**
     * @return MemberFields The member forms.
     */
    public function memberFields(): MemberFields
    {
        return new MemberFields($this->settingsSeals());
    }

    /**
     * @return MemberPanels A member's page.
     */
    public function memberPanels(): MemberPanels
    {
        return new MemberPanels(new StaffPages($this->pageDocuments), new MemberFields(new SettingsSeals($this->envelopeCodec)));
    }
}
