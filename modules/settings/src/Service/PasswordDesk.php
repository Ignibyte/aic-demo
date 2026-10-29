<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageDocument;
use AIC\Controller\Domain\PageRedirect;
use App\Settings\Entity\PasswordLink;
use App\Settings\Form\PasswordChecks;
use App\Settings\Form\SettingsSeals;
use App\Settings\Repository\LinkRepository;
use App\Staff\Entity\StaffAccount;
use App\Staff\Repository\AccountRepository;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffRequests;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The page a password link lands on, `/password/{token}` (#22), open to anyone who holds the link. A link whose token is
 * altered answers 400, and one used, voided by a newer one or past its 24 hours 404, each in the app's look with
 * nothing changed. An open link shows the set-password form; its post sets the password and spends the link in one
 * transaction, keeps "Your password is set." for the sign-in page and goes there (303). The new password ends the
 * member's other sessions, whose stamp of the old one staff's `StaffStanding` no longer accepts (#23); when the session
 * that set it is the member's own, `SettingsAccess::restamp()` gives it the new stamp, so it stays signed in. The page
 * reads no destination from its request: its one redirect is to `/sign-in`.
 */
final readonly class PasswordDesk
{
    /**
     * @param SettingsAccess    $settingsAccess    The page's frame and its refusals.
     * @param LinkRepository    $linkRepository    The links.
     * @param AccountRepository $accountRepository The link's member.
     * @param SettingsSeals     $settingsSeals     Opens the token.
     * @param PasswordChecks    $passwordChecks    The form and its checks.
     * @param StaffRequests     $staffRequests     Reads the route's token.
     * @param StaffNotices      $staffNotices      Keeps the sign-in page's line.
     */
    public function __construct(
        private SettingsAccess $settingsAccess,
        private LinkRepository $linkRepository,
        private AccountRepository $accountRepository,
        private SettingsSeals $settingsSeals,
        private PasswordChecks $passwordChecks = new PasswordChecks(),
        private StaffRequests $staffRequests = new StaffRequests(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a link's page, with its session.
     *
     * @throws Throwable When the link or its member cannot be read, or the page cannot be built.
     *
     * @return PageResponse The form; 400 for an altered link; 404 for one used or expired.
     */
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $id = $this->settingsSeals->linkId($this->token($serverRequest));

        if ($id === 0) {
            return $this->settingsAccess->linkAltered();
        }

        $staffAccount = $this->member($this->linkRepository->find($id));

        return $staffAccount instanceof StaffAccount
            ? $this->form($serverRequest, $staffAccount, [], 200)
            : $this->settingsAccess->linkSpent();
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws Throwable When the link or its member cannot be read or written; a failed set is rolled back.
     *
     * @return PageResponse 303 to sign-in; the form as 422; 400 for an altered link; 404 for one used or expired.
     */
    public function post(ServerRequestInterface $serverRequest): PageResponse
    {
        $id = $this->settingsSeals->linkId($this->token($serverRequest));

        if ($id === 0) {
            return $this->settingsAccess->linkAltered();
        }

        $passwordLink = $this->linkRepository->find($id);

        return $passwordLink instanceof PasswordLink
            ? $this->set($serverRequest, $passwordLink)
            : $this->settingsAccess->linkSpent();
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     * @param PasswordLink           $passwordLink  The link its token names.
     *
     * @throws Throwable When the link or its member cannot be read or written; a failed set is rolled back.
     *
     * @return PageResponse 303 to sign-in; the form as 422; 404 for a link used or expired.
     */
    private function set(ServerRequestInterface $serverRequest, PasswordLink $passwordLink): PageResponse
    {
        $staffAccount = $this->member($passwordLink);

        if (!$staffAccount instanceof StaffAccount) {
            return $this->settingsAccess->linkSpent();
        }

        $errors = $this->passwordChecks->errors($serverRequest);

        if ($errors !== []) {
            return $this->form($serverRequest, $staffAccount, $errors, 422);
        }

        if (!$this->linkRepository->use($passwordLink, $this->passwordChecks->password($serverRequest))) {
            return $this->settingsAccess->linkSpent();
        }

        $this->settingsAccess->restamp($serverRequest, $staffAccount, $this->linkRepository->member($staffAccount->id));
        $this->staffNotices->keep($serverRequest, 'Your password is set. Sign in with it.');

        return new PageRedirect('/sign-in');
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a link's route.
     *
     * @return string The route's token.
     */
    private function token(ServerRequestInterface $serverRequest): string
    {
        return $this->staffRequests->attribute($serverRequest, 'link');
    }

    /**
     * @param PasswordLink|null $passwordLink A link, or null when the token names none.
     *
     * @throws Throwable When the member cannot be read, or their role is no role.
     *
     * @return StaffAccount|null The link's member while the link is open; null when it is used, expired or gone.
     */
    private function member(?PasswordLink $passwordLink): ?StaffAccount
    {
        return $passwordLink instanceof PasswordLink && $passwordLink->open()
            ? $this->accountRepository->find($passwordLink->userId)
            : null;
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param StaffAccount           $staffAccount  The link's member.
     * @param array<string, string>  $errors        Why each refused field is refused, by name.
     * @param int                    $status        200, or 422 after a refusal.
     *
     * @throws Throwable When the request has no session, or a key or the status does not fit the theme.
     *
     * @return PageDocument The set-password page, posting back to the link's own path.
     */
    private function form(
        ServerRequestInterface $serverRequest,
        StaffAccount $staffAccount,
        array $errors,
        int $status,
    ): PageDocument {
        $form = $this->passwordChecks->form($serverRequest, LinkMails::PAGES . $this->token($serverRequest), $errors);
        $description = 'For ' . $staffAccount->name . ', who signs in as ' . $staffAccount->email . '. The link works once.';

        return $this->settingsAccess->panel('Set your password', $description, ['form' => $form], $status);
    }
}
