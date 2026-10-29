<?php

declare(strict_types=1);

namespace App\Staff\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPages;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The sign-in page: the email and password, where to go after, and the error when the last attempt failed. The email
 * is a text field, since the gate's user signs in as `gate`. A line kept for it shows above the form once, as the
 * settings' set-password page keeps "Your password is set." (#22). The panel's line names the dealership its page
 * passes, from the profile (#23).
 */
final readonly class SignInForm
{
    /**
     * @param StaffPages   $staffPages   The staff pages' document.
     * @param FormTokens   $formTokens   Issues the visitor's CSRF token.
     * @param StaffNotices $staffNotices The line kept for the page.
     */
    public function __construct(
        private StaffPages $staffPages,
        private FormTokens $formTokens = new FormTokens(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $email         The email to show again.
     * @param string                 $next          Where to go after; SignInPost keeps it on the site.
     * @param string                 $error         Why the last attempt failed; empty for none.
     * @param int                    $status        200, or 422 after a refusal.
     * @param string                 $dealership    The dealership's name, from its profile, for the panel's line.
     *
     * @throws LogicException When a key or the status is malformed, or the request carries no session.
     *
     * @return PageDocument The page.
     */
    public function page(
        ServerRequestInterface $serverRequest,
        string $email,
        string $next,
        string $error,
        int $status,
        string $dealership,
    ): PageDocument {
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'sign-in-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        $body[] = new KeyedComponent('form', $this->form($serverRequest, $email, $next, $error));
        $panel = new Panel('sign-in', 'Staff sign in', $dealership . ' staff only.', false, $body);

        return $this->staffPages->page('Sign in', [new KeyedComponent('sign-in', $panel)], $status);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $email         The email to show again.
     * @param string                 $next          Where to go after.
     * @param string                 $error         Why the last attempt failed; empty for none.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return Form The form, the error above its fields when there is one.
     */
    private function form(ServerRequestInterface $serverRequest, string $email, string $next, string $error): Form
    {
        $alerts = $error === ''
            ? []
            : [new Alert(AlertSeverity::Error, $error, 'sign-in-error')];
        $fields = [
            ...$alerts,
            new HiddenInput('next', $next),
            new FormField('sign-in-email', 'email', 'Email', 'text', $email, '', true, false),
            new FormField('sign-in-password', 'password', 'Password', 'password', '', '', true, false),
            new Button('Sign in', 'submit', 'sign-in-submit'),
        ];

        return new Form('sign-in-form', '/sign-in', $fields, $this->formTokens->issue($serverRequest)->value());
    }
}
