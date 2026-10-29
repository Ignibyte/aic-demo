<?php

declare(strict_types=1);

namespace App\Settings\Form;

use AIC\Form\Application\PostedFields;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffAccount;
use App\Staff\Repository\AccountRepository;
use App\Staff\Service\Roles;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function mb_strlen;
use function preg_match;
use function strlen;
use function strtolower;
use function trim;

/**
 * A member's form (#22): what a post sent, and its checks. A name of at most 100 characters; on the add form an email
 * as customers' checks take one, at most 254 characters, that no member signs in with yet; and one of the roles.
 */
final readonly class MemberChecks
{
    /**
     * Why an email a member signs in with already is refused.
     */
    public const string EMAIL_TAKEN = 'A member already signs in with this email.';

    /**
     * A valid email as the HTML standard defines it, with at least one dot in its domain, as customers' checks have it.
     */
    private const string EMAIL = '/^[A-Za-z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?'
        . '(?:\\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$/';

    /**
     * @param AccountRepository $accountRepository Knows which emails are taken.
     * @param PostedFields      $postedFields      Reads the posted fields.
     * @param Roles             $roles             The roles.
     */
    public function __construct(
        private AccountRepository $accountRepository,
        private PostedFields $postedFields = new PostedFields(),
        private Roles $roles = new Roles(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted member form.
     *
     * @return MemberEntry What it sent, the email trimmed and in small letters; a missing field or a list reads empty.
     */
    public function posted(ServerRequestInterface $serverRequest): MemberEntry
    {
        return new MemberEntry(
            $this->postedFields->value($serverRequest, 'name'),
            strtolower(trim($this->postedFields->value($serverRequest, 'email'))),
            $this->postedFields->value($serverRequest, 'role'),
        );
    }

    /**
     * @param MemberEntry $memberEntry What was posted.
     * @param bool        $adding      Whether it is the add form, which names an email.
     *
     * @throws RuntimeException         When the taken emails cannot be read, or a stored role is no role.
     * @throws InvalidArgumentException When the users' type is malformed.
     *
     * @return array<string, string> Why each refused field is refused, by name; empty when the entry is valid.
     */
    public function errors(MemberEntry $memberEntry, bool $adding): array
    {
        $errors = [
            'email' => $adding ? $this->emailError($memberEntry->email) : '',
            'name' => $this->nameError(trim($memberEntry->name)),
            'role' => $this->role($memberEntry->role) instanceof Role ? '' : 'Choose a role.',
        ];
        $refused = [];

        foreach ($errors as $field => $error) {
            if ($error === '') {
                continue;
            }

            $refused[$field] = $error;
        }

        return $refused;
    }

    /**
     * @param string $value A role as posted or sealed.
     *
     * @return Role|null The role it names; null when it names none.
     */
    public function role(string $value): ?Role
    {
        foreach ($this->roles->all() as $role) {
            if ($role->value === $value) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $values A member's name and role's stored value, by field name.
     *
     * @return array<string, string> The same as a person reads them: the role by its label.
     */
    public function labelled(array $values): array
    {
        $role = $this->role($values['role'] ?? '');
        $values['role'] = $role instanceof Role
            ? $role->label()
            : '';

        return $values;
    }

    /**
     * @param string $name A posted name, trimmed.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function nameError(string $name): string
    {
        if ($name === '') {
            return "Enter the member's name.";
        }

        return mb_strlen($name) > 100
            ? 'The name is at most 100 characters.'
            : '';
    }

    /**
     * @param string $email A posted email, trimmed and in small letters.
     *
     * @throws RuntimeException         When the taken emails cannot be read, or a stored role is no role.
     * @throws InvalidArgumentException When the users' type is malformed.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function emailError(string $email): string
    {
        if ($email === '') {
            return 'Enter the email they will sign in with.';
        }

        if (strlen($email) > 254) {
            return 'The email is at most 254 characters.';
        }

        if (preg_match(self::EMAIL, $email) !== 1) {
            return 'Enter an email such as name@example.com.';
        }

        return $this->accountRepository->byLogin($email) instanceof StaffAccount
            ? self::EMAIL_TAKEN
            : '';
    }
}
