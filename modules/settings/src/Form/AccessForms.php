<?php

declare(strict_types=1);

namespace App\Settings\Form;

use AIC\Component\Domain\Form;
use AIC\Component\Domain\HiddenInput;
use AIC\Form\Application\FormTokens;
use App\Theme\Components\SubmitButton\SubmitButton;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A member page's two buttons (#22), each a form of its own: Deactivate or Reactivate (`member-active`), posting
 * `active` to `…/active`, and Send a password link (`member-link`), posting to `…/link`. They carry only the form token:
 * the path names the member, and neither saves anything the page showed.
 */
final readonly class AccessForms
{
    /**
     * @param FormTokens $formTokens Issues the visitor's guard fields.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $path          The member's path.
     * @param bool                   $active        Whether they may sign in now.
     *
     * @throws LogicException When the request has no session.
     *
     * @return Form Deactivate for an active member, Reactivate for a deactivated one.
     */
    public function active(ServerRequestInterface $serverRequest, string $path, bool $active): Form
    {
        $fields = $active
            ? [new HiddenInput('active', '0'), new SubmitButton('Deactivate', 'member-active', '')]
            : [new HiddenInput('active', '1'), new SubmitButton('Reactivate', 'member-active', '')];

        return new Form('member-active-form', $path . '/active', $fields, $this->formTokens->guard($serverRequest));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $path          The member's path.
     *
     * @throws LogicException When the request has no session.
     *
     * @return Form Send a password link.
     */
    public function link(ServerRequestInterface $serverRequest, string $path): Form
    {
        $fields = [new SubmitButton('Send a password link', 'member-link', '')];

        return new Form('member-link-form', $path . '/link', $fields, $this->formTokens->guard($serverRequest));
    }
}
