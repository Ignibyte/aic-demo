<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Component\Domain\Form;
use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\PageDocument;
use App\Settings\Form\MemberEntry;
use App\Settings\Form\MemberFields;
use App\Settings\Form\ProfileFields;
use App\Settings\Repository\ProfileRepository;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPages;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * The settings page, `/app/settings` (#22): in the staff frame, three panels. The dealership's profile (the notice a
 * save kept, then the form filled from the stored profile), the staff (the table of members) and adding a member (the
 * blank form). A refused or stale post shows the page again with its own form in its state and the other as it starts.
 */
final readonly class SettingsPanels
{
    /**
     * @param StaffPages        $staffPages        The staff pages' document.
     * @param ProfileRepository $profileRepository The stored profile.
     * @param ProfileFields     $profileFields     The profile form.
     * @param MemberFields      $memberFields      The add form.
     * @param MemberTable       $memberTable       The staff table.
     * @param StaffNotices      $staffNotices      The notice a save kept.
     */
    public function __construct(
        private StaffPages $staffPages,
        private ProfileRepository $profileRepository,
        private ProfileFields $profileFields,
        private MemberFields $memberFields,
        private MemberTable $memberTable,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws RuntimeException When the profile or the members cannot be read.
     * @throws LogicException   When the request has no session, an envelope cannot be sealed, or a key or the status
     *                          does not fit the theme.
     *
     * @return PageDocument The page, every form as it starts.
     */
    public function page(ServerRequestInterface $serverRequest): PageDocument
    {
        return $this->document($serverRequest, $this->profileForm($serverRequest), $this->addForm($serverRequest), 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted profile, with its session.
     * @param Form                   $profileForm   The profile form as the post left it.
     * @param int                    $status        422 or 409.
     *
     * @throws RuntimeException When the members cannot be read.
     * @throws LogicException   When the request has no session, or a key or the status does not fit the theme.
     *
     * @return PageDocument The page, the profile form as given.
     */
    public function withProfile(ServerRequestInterface $serverRequest, Form $profileForm, int $status): PageDocument
    {
        return $this->document($serverRequest, $profileForm, $this->addForm($serverRequest), $status);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted member, with its session.
     * @param Form                   $addForm       The add form as the post left it.
     * @param int                    $status        422.
     *
     * @throws RuntimeException When the profile or the members cannot be read.
     * @throws LogicException   When the request has no session, an envelope cannot be sealed, or a key or the status
     *                          does not fit the theme.
     *
     * @return PageDocument The page, the add form as given.
     */
    public function withAdd(ServerRequestInterface $serverRequest, Form $addForm, int $status): PageDocument
    {
        return $this->document($serverRequest, $this->profileForm($serverRequest), $addForm, $status);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws RuntimeException When the profile cannot be read.
     * @throws LogicException   When the request has no session, the envelope cannot be sealed, or a key is malformed.
     *
     * @return Form The profile form, filled from the stored profile and sealing it.
     */
    private function profileForm(ServerRequestInterface $serverRequest): Form
    {
        return $this->profileFields->stored($serverRequest, $this->profileRepository->profile());
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws LogicException When the request has no session, or a key is malformed.
     *
     * @return Form The blank add form.
     */
    private function addForm(ServerRequestInterface $serverRequest): Form
    {
        return $this->memberFields->add($serverRequest, new MemberEntry('', '', ''), []);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param Form                   $profileForm   The profile form.
     * @param Form                   $addForm       The add form.
     * @param int                    $status        The page's status.
     *
     * @throws RuntimeException When the members cannot be read.
     * @throws LogicException   When the request has no session, or a key or the status does not fit the theme.
     *
     * @return PageDocument The page.
     */
    private function document(
        ServerRequestInterface $serverRequest,
        Form $profileForm,
        Form $addForm,
        int $status,
    ): PageDocument {
        $profile = [];

        foreach ($this->staffNotices->take($serverRequest, 'settings-notice') as $key => $notice) {
            $profile[] = new KeyedComponent($key, $notice);
        }

        $profile[] = new KeyedComponent('form', $profileForm);
        $profileLine = 'Its name heads the public pages and signs the mails; its hours hold the test drives.';
        $staff = 'Who signs in to Lotline. Open a member to change their role, deactivate them or send them a password link.';
        $table = [new KeyedComponent('table', $this->memberTable->table($serverRequest))];
        $add = 'They are mailed a link to set their password. It works once, for 24 hours.';
        $panels = [
            new KeyedComponent('profile', new Panel('profile', "The dealership's profile", $profileLine, true, $profile)),
            new KeyedComponent('staff', new Panel('staff', 'Staff', $staff, true, $table)),
            new KeyedComponent('add', new Panel('add', 'Add a member', $add, true, [new KeyedComponent('form', $addForm)])),
        ];

        return $this->staffPages->page('Settings', $panels, $status);
    }
}
