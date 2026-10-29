<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Component\Domain\Form;
use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\PageDocument;
use App\Settings\Form\AccessForms;
use App\Settings\Form\MemberEntry;
use App\Settings\Form\MemberFields;
use App\Staff\Entity\StaffAccount;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPages;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A member's page, `/app/settings/staff/{id}` (#22): in the staff frame, two panels. The member (the notice a change
 * kept, then the form for their name and role) and their access (whether they may sign in, a refusal's warning, and
 * the Deactivate or Reactivate and Send a password link buttons).
 */
final readonly class MemberPanels
{
    /**
     * @param StaffPages   $staffPages   The staff pages' document.
     * @param MemberFields $memberFields The edit form.
     * @param AccessForms  $accessForms  The two buttons.
     * @param StaffNotices $staffNotices The notice a change kept.
     */
    public function __construct(
        private StaffPages $staffPages,
        private MemberFields $memberFields,
        private AccessForms $accessForms = new AccessForms(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param StaffAccount $staffAccount A member.
     *
     * @return string Their page's path.
     */
    public function path(StaffAccount $staffAccount): string
    {
        return MemberTable::PAGES . $staffAccount->id;
    }

    /**
     * @param StaffAccount $staffAccount A member.
     *
     * @return array<string, string> Their name and role's stored value, as the edit form shows them and seals them.
     */
    public function shown(StaffAccount $staffAccount): array
    {
        return ['name' => $staffAccount->name, 'role' => $staffAccount->role->value];
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param StaffAccount           $staffAccount  The member.
     * @param string                 $warning       Why a change of their access was refused; empty for none.
     * @param int                    $status        200, or 422 with the warning.
     *
     * @throws LogicException When the request has no session, an envelope cannot be sealed, or a key or the status does
     *                        not fit the theme.
     *
     * @return PageDocument The page, the edit form filled from the member.
     */
    public function page(
        ServerRequestInterface $serverRequest,
        StaffAccount $staffAccount,
        string $warning = '',
        int $status = 200,
    ): PageDocument {
        $memberEntry = new MemberEntry($staffAccount->name, '', $staffAccount->role->value);
        $form = $this->memberFields->edit($serverRequest, $this->path($staffAccount), $memberEntry, [], $this->shown($staffAccount), '');

        return $this->document($serverRequest, $staffAccount, $form, $warning, $status);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param StaffAccount           $staffAccount  The member.
     * @param Form                   $form          The edit form as a post left it.
     * @param string                 $warning       Why a change of their access was refused; empty for none.
     * @param int                    $status        The page's status: 200, 409 or 422.
     *
     * @throws LogicException When the request has no session, or a key or the status does not fit the theme.
     *
     * @return PageDocument The page.
     */
    public function document(
        ServerRequestInterface $serverRequest,
        StaffAccount $staffAccount,
        Form $form,
        string $warning,
        int $status,
    ): PageDocument {
        $member = [];

        foreach ($this->staffNotices->take($serverRequest, 'member-notice') as $key => $notice) {
            $member[] = new KeyedComponent($key, $notice);
        }

        $member[] = new KeyedComponent('form', $form);
        $access = [];

        if ($warning !== '') {
            $access[] = new KeyedComponent('warning', new Notice($warning, NoticeTone::Warning, 'member-warning'));
        }

        $path = $this->path($staffAccount);
        $access[] = new KeyedComponent('active', $this->accessForms->active($serverRequest, $path, $staffAccount->active()));
        $access[] = new KeyedComponent('link', $this->accessForms->link($serverRequest, $path));
        $panels = [
            new KeyedComponent('member', new Panel('member', $staffAccount->name, 'Signs in as ' . $staffAccount->email, true, $member)),
            new KeyedComponent('access', new Panel('access', 'Access', $this->access($staffAccount), true, $access)),
        ];

        return $this->staffPages->page($staffAccount->name, $panels, $status);
    }

    /**
     * @param StaffAccount $staffAccount The member.
     *
     * @return string Whether they may sign in, in a line.
     */
    private function access(StaffAccount $staffAccount): string
    {
        return $staffAccount->active()
            ? 'Active: they may sign in. Deactivating them signs them out on their next request.'
            : 'Deactivated on ' . $staffAccount->deactivatedOn . ': they cannot sign in until they are reactivated.';
    }
}
