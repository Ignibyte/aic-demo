<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Settings\Form\MemberChecks;
use App\Settings\Form\MemberEntry;
use App\Settings\Form\MemberFields;
use App\Staff\Entity\StaffAccount;
use App\Staff\Repository\AccountRepository;
use App\Staff\Service\StaffNotices;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function trim;

/**
 * A member added and shown (#22), an admin's alone. Adding checks the name, email and role, stores the member with no
 * password, mails them a link and goes to their page (303) with a notice; a refused field or a taken email answers the
 * settings page again as 422. A member's page shows them, or 404 for an id no member has. Their edit form's post is
 * `MemberEdits`'.
 */
final readonly class MemberSaves
{
    /**
     * @param SettingsAccess    $settingsAccess    Admins only; the member the route names; the 404 page.
     * @param SettingsPanels    $settingsPanels    The settings page, for a refused add.
     * @param MemberPanels      $memberPanels      The member's page.
     * @param AccountRepository $accountRepository The members.
     * @param MemberChecks      $memberChecks      Reads and checks a post.
     * @param MemberFields      $memberFields      The add form.
     * @param LinkMails         $linkMails         Mails a new member their link.
     * @param StaffNotices      $staffNotices      Keeps the add's notice.
     */
    public function __construct(
        private SettingsAccess $settingsAccess,
        private SettingsPanels $settingsPanels,
        private MemberPanels $memberPanels,
        private AccountRepository $accountRepository,
        private MemberChecks $memberChecks,
        private MemberFields $memberFields,
        private LinkMails $linkMails,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted add form, with its session.
     *
     * @throws Throwable When the members cannot be read or written, or the link cannot be issued or sent.
     *
     * @return PageResponse 303 to the new member; the settings page again as 422; 403 for anyone but an admin.
     */
    public function add(ServerRequestInterface $serverRequest): PageResponse
    {
        $refusal = $this->settingsAccess->guard($serverRequest);

        if ($refusal !== null) {
            return $refusal;
        }

        $memberEntry = $this->memberChecks->posted($serverRequest);
        $errors = $this->memberChecks->errors($memberEntry, true);

        if ($errors !== []) {
            return $this->refused($serverRequest, $memberEntry, $errors);
        }

        $id = $this->accountRepository->add($memberEntry->email, trim($memberEntry->name), $memberEntry->role);
        $staffAccount = $id === null
            ? null
            : $this->accountRepository->find($id);

        if (!$staffAccount instanceof StaffAccount) {
            return $this->refused($serverRequest, $memberEntry, ['email' => MemberChecks::EMAIL_TAKEN]);
        }

        $this->linkMails->send($staffAccount, true);
        $this->staffNotices->keep($serverRequest, 'Added ' . $staffAccount->name . ' and mailed them a link to set a password.');

        return new PageRedirect($this->memberPanels->path($staffAccount));
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a member's page, with its session.
     *
     * @throws Throwable When the member cannot be read, or the page cannot be built.
     *
     * @return PageResponse The page; 404; 403 for anyone but an admin.
     */
    public function show(ServerRequestInterface $serverRequest): PageResponse
    {
        $staffAccount = $this->settingsAccess->member($serverRequest);

        return $staffAccount instanceof StaffAccount
            ? $this->memberPanels->page($serverRequest, $staffAccount)
            : $this->settingsAccess->refusal($serverRequest);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted add form, with its session.
     * @param MemberEntry            $memberEntry   What was typed.
     * @param array<string, string>  $errors        Why each refused field is refused, by name.
     *
     * @throws Throwable When the page cannot be built.
     *
     * @return PageResponse The settings page as 422, the add form with what was typed and the errors.
     */
    private function refused(
        ServerRequestInterface $serverRequest,
        MemberEntry $memberEntry,
        array $errors,
    ): PageResponse {
        return $this->settingsPanels->withAdd($serverRequest, $this->memberFields->add($serverRequest, $memberEntry, $errors), 422);
    }
}
