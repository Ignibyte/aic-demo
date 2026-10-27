<?php

declare(strict_types=1);

namespace App\Staff\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\Link;
use AIC\Form\Application\FormTokens;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\StaffSession;
use App\Theme\Components\SignedInMenu\SignedInMenu;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * The header's account corner, after the page's top bar on every page: the signed-in member's menu with their name,
 * role and email and the sign-out button, or a link to sign in.
 */
final readonly class AccountMenuBlock implements Block
{
    /**
     * @param StaffSession $staffSession Who is signed in.
     * @param FormTokens   $formTokens   Issues the sign-out form's CSRF token.
     */
    public function __construct(
        private StaffSession $staffSession = new StaffSession(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @return Placement `header`, weight 0, every page.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('header', 0, '');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its session.
     *
     * @throws LogicException           When the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return Component The menu, or the sign-in link.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        $staffMember = $this->staffSession->member($serverRequest);

        if (!$staffMember instanceof StaffMember) {
            return new Link('Sign in', '/sign-in');
        }

        $token = $this->formTokens->issue($serverRequest)->value();
        $form = new Form('sign-out-form', '/sign-out', [new Button('Sign out', 'submit', 'sign-out')], $token);

        return new SignedInMenu($staffMember->name, $staffMember->role->label(), $staffMember->email, $form);
    }
}
