<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Settings\Form\MemberChecks;
use App\Settings\Form\MemberEntry;
use App\Settings\Form\MemberFields;
use App\Settings\Form\SettingsSeals;
use App\Staff\Entity\AccountWrite;
use App\Staff\Entity\StaffAccount;
use App\Staff\Repository\AccountRepository;
use App\Staff\Service\StaffNotices;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function array_key_exists;
use function trim;

/**
 * A member's edit form saved, `POST /app/settings/staff/{id}` (#22), an admin's alone. The name and role change when
 * the stored ones are still those the form showed (else 409 with what changed, nothing written, the typed values kept
 * and the stored ones sealed) and while an active admin remains (else 422 with the reason); a refused field answers
 * 422; an envelope that is not this member's edit form's answers 400, and a member who is not there 404. A save goes
 * back to the member's page (303) with a notice.
 */
final readonly class MemberEdits
{
    /**
     * Why a change that would leave no active admin is refused.
     */
    public const string LAST_ADMIN = 'Lotline needs an active admin. Make another member an admin first.';

    /**
     * Each field's label in a 409's list.
     */
    private const array LABELS = ['name' => 'Name', 'role' => 'Role'];

    /**
     * @param SettingsAccess    $settingsAccess    Admins only; the member the route names; the 404 and 400 pages.
     * @param MemberPanels      $memberPanels      The member's page.
     * @param AccountRepository $accountRepository Changes the member.
     * @param MemberChecks      $memberChecks      Reads and checks the post.
     * @param MemberFields      $memberFields      The edit form.
     * @param SettingsSeals     $settingsSeals     Opens the form's envelope.
     * @param ChangeLines       $changeLines       What a 409 says.
     * @param StaffNotices      $staffNotices      Keeps the save's notice.
     */
    public function __construct(
        private SettingsAccess $settingsAccess,
        private MemberPanels $memberPanels,
        private AccountRepository $accountRepository,
        private MemberChecks $memberChecks,
        private MemberFields $memberFields,
        private SettingsSeals $settingsSeals,
        private ChangeLines $changeLines = new ChangeLines(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted edit form, with its session.
     *
     * @throws Throwable When the member cannot be read or written; a failed change is rolled back.
     *
     * @return PageResponse 303 to the member; their page again as 422 or 409; 400; 404; 403 for anyone but an admin.
     */
    public function update(ServerRequestInterface $serverRequest): PageResponse
    {
        $staffAccount = $this->settingsAccess->member($serverRequest);

        if (!$staffAccount instanceof StaffAccount) {
            return $this->settingsAccess->refusal($serverRequest);
        }

        $shown = $this->settingsSeals->shown($serverRequest, $this->memberPanels->path($staffAccount)) ?? [];

        if (!array_key_exists('name', $shown) || !array_key_exists('role', $shown)) {
            return $this->settingsAccess->altered();
        }

        $memberEntry = $this->memberChecks->posted($serverRequest);
        $errors = $this->memberChecks->errors($memberEntry, false);

        if ($errors !== []) {
            return $this->again($serverRequest, $staffAccount, $memberEntry, $errors, $shown, 422);
        }

        $name = trim($memberEntry->name);
        $outcome = $this->accountRepository->change($staffAccount->id, $shown['name'], $shown['role'], $name, $memberEntry->role);

        return $this->changed($serverRequest, $staffAccount, $memberEntry, $shown, $outcome);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted edit form, with its session.
     * @param StaffAccount           $staffAccount  The member as read before the change.
     * @param MemberEntry            $memberEntry   What was typed, found valid.
     * @param array<string, string>  $shown         The name and role the form showed.
     * @param string                 $outcome       What the change did, an `AccountWrite` outcome.
     *
     * @throws Throwable When the member cannot be read again, or the page cannot be built.
     *
     * @return PageResponse 303 to the member; their page as 409 or 422; 404 when they went meanwhile.
     */
    private function changed(
        ServerRequestInterface $serverRequest,
        StaffAccount $staffAccount,
        MemberEntry $memberEntry,
        array $shown,
        string $outcome,
    ): PageResponse {
        if ($outcome === AccountWrite::SAVED) {
            $this->staffNotices->keep($serverRequest, 'Saved ' . trim($memberEntry->name) . '.');

            return new PageRedirect($this->memberPanels->path($staffAccount));
        }

        // A member gone meanwhile (`AccountWrite::MISSING`) reads as none here too.
        $stored = $this->accountRepository->find($staffAccount->id);

        if (!$stored instanceof StaffAccount) {
            return $this->settingsAccess->missing();
        }

        if ($outcome === AccountWrite::LAST_ADMIN) {
            $errors = ['form' => self::LAST_ADMIN, 'role' => self::LAST_ADMIN];

            return $this->again($serverRequest, $stored, $memberEntry, $errors, $shown, 422);
        }

        return $this->stale($serverRequest, $stored, $memberEntry, $shown);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted edit form, with its session.
     * @param StaffAccount           $staffAccount  The member as stored now.
     * @param MemberEntry            $memberEntry   What was typed.
     * @param array<string, string>  $shown         The name and role the form showed.
     *
     * @throws Throwable When the page cannot be built.
     *
     * @return PageResponse Their page as 409: what changed, the typed values, the stored ones sealed.
     */
    private function stale(
        ServerRequestInterface $serverRequest,
        StaffAccount $staffAccount,
        MemberEntry $memberEntry,
        array $shown,
    ): PageResponse {
        $now = $this->memberPanels->shown($staffAccount);
        $then = $this->memberChecks->labelled($shown);
        $message = $this->changeLines->message('this member', self::LABELS, $then, $this->memberChecks->labelled($now));
        $form = $this->memberFields->edit($serverRequest, $this->memberPanels->path($staffAccount), $memberEntry, [], $now, $message);

        return $this->memberPanels->document($serverRequest, $staffAccount, $form, '', 409);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted edit form, with its session.
     * @param StaffAccount           $staffAccount  The member.
     * @param MemberEntry            $memberEntry   What was typed.
     * @param array<string, string>  $errors        Why each refused field is refused, by name; `form` for the alert.
     * @param array<string, string>  $shown         The name and role the form showed, sealed again.
     * @param int                    $status        422.
     *
     * @throws Throwable When the page cannot be built.
     *
     * @return PageResponse The member's page, the form with what was typed and the errors.
     */
    private function again(
        ServerRequestInterface $serverRequest,
        StaffAccount $staffAccount,
        MemberEntry $memberEntry,
        array $errors,
        array $shown,
        int $status,
    ): PageResponse {
        $form = $this->memberFields->edit($serverRequest, $this->memberPanels->path($staffAccount), $memberEntry, $errors, $shown, '');

        return $this->memberPanels->document($serverRequest, $staffAccount, $form, '', $status);
    }
}
