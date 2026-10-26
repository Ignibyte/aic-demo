<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\HiddenInput;
use AIC\Form\Application\FormTokens;
use App\Theme\Components\ConfirmCard\ConfirmCard;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use App\Theme\Components\SignedInMenu\SignedInMenu;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The style guide's one-button forms, each posting to `/theme`, which goes back to the guide: the confirm card's
 * delete and the account menu's sign-out.
 */
final readonly class ThemeSampleActions
{
    /**
     * @param FormTokens $formTokens Issues each form's token.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, for the visitor's token.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return ConfirmCard The question before deleting a sample customer.
     */
    public function confirm(ServerRequestInterface $serverRequest): ConfirmCard
    {
        $form = new Form('delete-form', '/theme', [
            new HiddenInput('intent', 'delete'),
            new Button('Delete', 'submit', 'delete-customer'),
        ], $this->formTokens->issue($serverRequest)->value());

        return new ConfirmCard(
            'Jordan Pike',
            'jordan.pike@example.com',
            'Delete this customer? This cannot be undone.',
            $form,
            new ButtonLink('Keep them', '/theme', 'keep-customer', ButtonTone::Ghost),
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, for the visitor's token.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return SignedInMenu A sample staff member's menu; nobody is signed in.
     */
    public function account(ServerRequestInterface $serverRequest): SignedInMenu
    {
        $form = new Form('sign-out-form', '/theme', [
            new HiddenInput('intent', 'sign-out'),
            new Button('Sign out', 'submit', 'sign-out'),
        ], $this->formTokens->issue($serverRequest)->value());

        return new SignedInMenu('Dana Reyes', 'Sales manager', 'dana.reyes@example.com', $form);
    }
}
