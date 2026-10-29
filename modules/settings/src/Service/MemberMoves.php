<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Form\Application\PostedFields;
use App\Staff\Entity\AccountWrite;
use App\Staff\Entity\StaffAccount;
use App\Staff\Repository\AccountRepository;
use App\Staff\Service\StaffNotices;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * A member's access changed (#22), an admin's alone: `POST …/active` deactivates them (`active=0`), as of the
 * database's today, or reactivates them (`active=1`); deactivating the last active admin is refused with 422 and the
 * reason. `POST …/link` sends them a new password link, voiding their older ones, kept in their inbox and mailed when
 * their login is an address; an admin who sends one to themselves gets it the same way (#23). Each goes back to the
 * member's page (303) with a notice; a member who is not there answers 404.
 */
final readonly class MemberMoves
{
    /**
     * @param SettingsAccess    $settingsAccess    Admins only; the member the route names; the 404 page.
     * @param MemberPanels      $memberPanels      The member's page.
     * @param AccountRepository $accountRepository Deactivates and reactivates.
     * @param LinkMails         $linkMails         Mails a link.
     * @param PostedFields      $postedFields      Reads `active`.
     * @param StaffNotices      $staffNotices      Keeps a change's notice.
     */
    public function __construct(
        private SettingsAccess $settingsAccess,
        private MemberPanels $memberPanels,
        private AccountRepository $accountRepository,
        private LinkMails $linkMails,
        private PostedFields $postedFields = new PostedFields(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted Deactivate or Reactivate form, with its session.
     *
     * @throws Throwable When the member cannot be read or written; a failed change is rolled back.
     *
     * @return PageResponse 303 to the member; their page as 422 for the last active admin; 404; 403 for anyone but an
     *                      admin.
     */
    public function activate(ServerRequestInterface $serverRequest): PageResponse
    {
        $staffAccount = $this->settingsAccess->member($serverRequest);

        if (!$staffAccount instanceof StaffAccount) {
            return $this->settingsAccess->refusal($serverRequest);
        }

        $active = $this->postedFields->value($serverRequest, 'active') === '1';
        $outcome = $this->accountRepository->activate($staffAccount->id, $active);

        if ($outcome === AccountWrite::MISSING) {
            return $this->settingsAccess->missing();
        }

        if ($outcome === AccountWrite::LAST_ADMIN) {
            return $this->memberPanels->page($serverRequest, $staffAccount, MemberEdits::LAST_ADMIN, 422);
        }

        $notice = $active
            ? 'Reactivated ' . $staffAccount->name . '. They can sign in again.'
            : 'Deactivated ' . $staffAccount->name . '. They are signed out and cannot sign in.';
        $this->staffNotices->keep($serverRequest, $notice);

        return new PageRedirect($this->memberPanels->path($staffAccount));
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted Send a password link form, with its session.
     *
     * @throws Throwable When the member cannot be read, or the link cannot be issued or sent.
     *
     * @return PageResponse 303 to the member; 404; 403 for anyone but an admin.
     */
    public function link(ServerRequestInterface $serverRequest): PageResponse
    {
        $staffAccount = $this->settingsAccess->member($serverRequest);

        if (!$staffAccount instanceof StaffAccount) {
            return $this->settingsAccess->refusal($serverRequest);
        }

        $this->linkMails->send($staffAccount, false);
        $notice = 'Sent ' . $staffAccount->name . ' a link to set a new password. It works once, for 24 hours.';
        $this->staffNotices->keep($serverRequest, $notice);

        return new PageRedirect($this->memberPanels->path($staffAccount));
    }
}
