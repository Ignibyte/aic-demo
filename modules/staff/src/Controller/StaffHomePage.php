<?php

declare(strict_types=1);

namespace App\Staff\Controller;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffSession;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app: the first page for signed-in staff, greeting the member by name and role until the dashboard lands. The
 * prefix is protected, so an anonymous visitor never reaches it; a session without the member goes to sign in.
 */
final readonly class StaffHomePage implements Page
{
    /**
     * @param StaffPages   $staffPages   The staff pages' document.
     * @param StaffSession $staffSession Who is signed in.
     */
    public function __construct(
        private StaffPages $staffPages,
        private StaffSession $staffSession = new StaffSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws LogicException           When a key does not fit the theme, or the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse The page, or 303 to sign in.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $staffMember = $this->staffSession->member($serverRequest);

        if (!$staffMember instanceof StaffMember) {
            return new PageRedirect('/sign-in');
        }

        $notice = new Notice('The dashboard, inventory, customers and sales arrive in later updates.', NoticeTone::Info, 'app-coming');
        $panel = new Panel(
            'welcome',
            'Welcome, ' . $staffMember->name,
            $staffMember->role->label() . ' at Ridgeway Motors',
            true,
            [new KeyedComponent('coming', $notice)],
        );

        return $this->staffPages->page('Home', [new KeyedComponent('welcome', $panel)], 200);
    }
}
