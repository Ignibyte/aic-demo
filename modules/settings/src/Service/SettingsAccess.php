<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Component\Contracts\Component;
use AIC\Controller\Domain\PageDocument;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffAccount;
use App\Staff\Entity\StaffMember;
use App\Staff\Repository\AccountRepository;
use App\Staff\Service\StaffPanels;
use App\Staff\Service\StaffRequests;
use App\Staff\Service\StaffSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function preg_match;

/**
 * Who may change the settings, and the settings' one-panel pages (#22). An admin alone: a sales manager or a
 * salesperson is refused with 403 in the staff page frame at every settings route, a GET or a POST, before anything is
 * read or written. The session's role is the users table's, refreshed by staff's `StaffStanding` on each request, so a
 * demoted admin is refused on their next request. Also the member a route's id names, and the other refusals: a member
 * that is not there (404), a form whose envelope is altered or another's (400), and a password link altered (400) or
 * spent (404); and the frame the set-password page shows in.
 */
final readonly class SettingsAccess
{
    /**
     * The settings page, where a refusal's link goes.
     */
    public const string PAGE = '/app/settings';

    /**
     * Where a password link's refusal sends its holder.
     */
    private const string SIGN_IN = '/sign-in';

    /**
     * @param StaffPanels       $staffPanels       The staff pages' frame, as the panel `settings`.
     * @param AccountRepository $accountRepository The members.
     * @param StaffSession      $staffSession      Who is signed in.
     * @param StaffRequests     $staffRequests     Reads the route's id.
     */
    public function __construct(
        private StaffPanels $staffPanels,
        private AccountRepository $accountRepository,
        private StaffSession $staffSession = new StaffSession(),
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws RuntimeException When the request carries no session, or the kept role is no role.
     * @throws LogicException   When the status does not fit the theme.
     *
     * @return PageDocument|null Null for an admin, who may go on; the 403 page for anyone else.
     */
    public function guard(ServerRequestInterface $serverRequest): ?PageDocument
    {
        $staffMember = $this->staffSession->member($serverRequest);

        return $staffMember instanceof StaffMember && $staffMember->role === Role::Admin
            ? null
            : $this->refused();
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a member's route, with its session.
     *
     * @throws RuntimeException When the member cannot be read, or a role is no role.
     * @throws LogicException   When the request carries no session.
     *
     * @return StaffAccount|null The member its `id` names, for an admin; null for anyone else, or when the id is not 1
     *                           to 999,999,999 or no member has it. `refusal()` then says which.
     */
    public function member(ServerRequestInterface $serverRequest): ?StaffAccount
    {
        $id = $this->staffRequests->attribute($serverRequest, 'id');

        return $this->guard($serverRequest) === null && preg_match('/^[1-9][0-9]{0,8}$/', $id) === 1
            ? $this->accountRepository->find((int) $id)
            : null;
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     * @param StaffAccount           $staffAccount  A member.
     *
     * @throws RuntimeException When the session cannot be read, or the kept role is no role.
     * @throws LogicException   When the request carries no session.
     *
     * @return bool Whether the member is the one signed in.
     */
    public function self(ServerRequestInterface $serverRequest, StaffAccount $staffAccount): bool
    {
        $staffMember = $this->staffSession->member($serverRequest);

        return $staffMember instanceof StaffMember && $staffMember->email === $staffAccount->email;
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a member's route that `member()` answered null.
     *
     * @throws RuntimeException When the session cannot be read, or the kept role is no role.
     * @throws LogicException   When the status does not fit the theme.
     *
     * @return PageDocument 403 for anyone but an admin; else 404, as no member has the id.
     */
    public function refusal(ServerRequestInterface $serverRequest): PageDocument
    {
        return $this->guard($serverRequest) ?? $this->missing();
    }

    /**
     * @param string                   $title       The top bar's title and the panel's heading.
     * @param string                   $description The line under the heading.
     * @param array<string, Component> $body        What the panel shows, in order, by its key.
     * @param int                      $status      The page's status.
     *
     * @throws LogicException When a key or the status does not fit the theme.
     *
     * @return PageDocument A page of one wide panel in the staff frame.
     */
    public function panel(string $title, string $description, array $body, int $status): PageDocument
    {
        return $this->staffPanels->page($title, $title, $description, $body, $status);
    }

    /**
     * @throws LogicException When the status does not fit the theme.
     *
     * @return PageDocument 404: no member of staff has the id.
     */
    public function missing(): PageDocument
    {
        $message = 'No member of staff has that number. They may have been removed.';

        return $this->staffPanels->warning('Member not found', $message, 'member-missing', 'Back to the settings', self::PAGE, 404);
    }

    /**
     * @throws LogicException When the status does not fit the theme.
     *
     * @return PageDocument 400: the form's envelope was altered, missing, or issued for another record.
     */
    public function altered(): PageDocument
    {
        $message = 'This form was altered or belongs to another page, so it was not saved. Open the page again and save once more.';

        return $this->staffPanels->warning('Form not accepted', $message, 'settings-altered', 'Back to the settings', self::PAGE, 400);
    }

    /**
     * @throws LogicException When the status does not fit the theme.
     *
     * @return PageDocument 400: a password link's token was altered.
     */
    public function linkAltered(): PageDocument
    {
        $message = 'This link was altered, so it cannot set a password. Open the link as your mail gave it, or ask an admin for another.';

        return $this->staffPanels->warning('Link not accepted', $message, 'password-link-altered', 'Go to sign in', self::SIGN_IN, 400);
    }

    /**
     * @throws LogicException When the status does not fit the theme.
     *
     * @return PageDocument 404: a password link was used, voided by a newer one, or is past its 24 hours.
     */
    public function linkSpent(): PageDocument
    {
        $message = 'This link has been used, replaced by a newer one, or is more than 24 hours old. Ask an admin to send you a new one.';

        return $this->staffPanels->warning('Link used or expired', $message, 'password-link-spent', 'Go to sign in', self::SIGN_IN, 404);
    }

    /**
     * @throws LogicException When the status does not fit the theme.
     *
     * @return PageDocument 403: only an admin changes the settings.
     */
    private function refused(): PageDocument
    {
        $message = 'Only an admin can change the settings and the staff.';

        return $this->staffPanels->warning('Not allowed', $message, 'settings-refused', 'Back to the dashboard', '/app', 403);
    }
}
