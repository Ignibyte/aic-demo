<?php

declare(strict_types=1);

namespace App\Settings\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Form;
use AIC\Form\Application\FormTokens;
use AIC\Form\Application\PostedFields;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\SubmitButton\SubmitButton;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use SensitiveParameter;

use function strlen;

/**
 * The set-password form (#22): a new password typed twice, its checks, and the form itself. A password is 12 to 72
 * bytes (bcrypt reads no further), and the second entry must match the first. Neither is ever shown again.
 */
final readonly class PasswordChecks
{
    /**
     * @param PostedFields $postedFields Reads the posted fields.
     * @param FormTokens   $formTokens   Issues the visitor's guard fields.
     */
    public function __construct(
        private PostedFields $postedFields = new PostedFields(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted set-password form.
     *
     * @return string The password typed first, as typed.
     */
    public function password(ServerRequestInterface $serverRequest): string
    {
        return $this->postedFields->value($serverRequest, 'password');
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted set-password form.
     *
     * @return array<string, string> Why each refused field is refused, by name; empty when the password may be set.
     */
    public function errors(ServerRequestInterface $serverRequest): array
    {
        $password = $this->password($serverRequest);
        $length = strlen($password);

        if ($length < 12 || $length > 72) {
            return ['password' => 'Choose a password of 12 to 72 characters.'];
        }

        return $this->postedFields->value($serverRequest, 'confirm') === $password
            ? []
            : ['confirm' => 'Type the same password again.'];
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $path          The link's path, where the form posts.
     * @param array<string, string>  $errors        Why each refused field is refused, by name.
     *
     * @throws LogicException When the request has no session.
     *
     * @return Form The form, both fields empty, an alert above them after a refusal.
     */
    public function form(ServerRequestInterface $serverRequest, #[SensitiveParameter]
        string $path, array $errors, ): Form
    {
        $fields = [];

        if ($errors !== []) {
            $fields[] = new Alert(AlertSeverity::Error, 'Fix the fields marked below.', 'password-error');
        }

        $fields[] = new FormField('password-new', 'password', 'New password', 'password', '', $errors['password'] ?? '', true, false);
        $again = $errors['confirm'] ?? '';
        $fields[] = new FormField('password-confirm', 'confirm', 'The same password again', 'password', '', $again, true, false);
        $fields[] = new SubmitButton('Set my password', 'password-save', '');

        return new Form('password-form', $path, $fields, $this->formTokens->guard($serverRequest));
    }
}
