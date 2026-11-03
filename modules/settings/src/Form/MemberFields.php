<?php

declare(strict_types=1);

namespace App\Settings\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\KeyedComponent;
use AIC\Form\Application\FormTokens;
use App\Staff\Service\Roles;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\FormGrid\FormGrid;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\SelectField\SelectOption;
use App\Theme\Components\SubmitButton\SubmitButton;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A member's two forms (#22): the settings page's add form (name, email and role, `member-add-<name>`), posting to
 * `/app/settings/staff`; and a member page's edit form (name and role, `member-<name>`), posting to the member's path
 * with the envelope sealing the name and role it was issued for. An alert opens either form when its post was refused
 * (`member-add-error`, `member-error`) or, on the edit form, stale (`member-stale`).
 */
final readonly class MemberFields
{
    /**
     * Where the add form posts.
     */
    public const string ADD = '/app/settings/staff';

    /**
     * What a refused form's alert says.
     */
    private const string REFUSED = 'Fix the fields marked below.';

    /**
     * @param SettingsSeals $settingsSeals Seals the values the edit form was issued for.
     * @param FormTokens    $formTokens    Issues the visitor's guard fields.
     * @param Roles         $roles         The roles, in order.
     */
    public function __construct(
        private SettingsSeals $settingsSeals,
        private FormTokens $formTokens = new FormTokens(),
        private Roles $roles = new Roles(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param MemberEntry            $memberEntry   The values the fields show.
     * @param array<string, string>  $errors        Why each refused field is refused, by name.
     *
     * @throws LogicException When the request has no session, or a key is malformed.
     *
     * @return Form The add form; blank when the entry is.
     */
    public function add(ServerRequestInterface $serverRequest, MemberEntry $memberEntry, array $errors): Form
    {
        $formGrid = new FormGrid([
            $this->field('member-add-name', 'name', 'Name', $memberEntry->name, $errors, 'text'),
            $this->field('member-add-email', 'email', 'Email (they sign in with it)', $memberEntry->email, $errors, 'email'),
            new KeyedComponent('role', $this->role('member-add-role', $memberEntry->role, $errors['role'] ?? '')),
        ]);
        $fields = [];

        if ($errors !== []) {
            $fields[] = new Alert(AlertSeverity::Error, self::REFUSED, 'member-add-error');
        }

        $fields[] = $formGrid;
        $fields[] = new SubmitButton('Add the member', 'member-add', '');

        return new Form('member-add-form', self::ADD, $fields, $this->formTokens->guard($serverRequest));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $path          The member's path, where the form posts.
     * @param MemberEntry            $memberEntry   The values the fields show.
     * @param array<string, string>  $errors        Why each refused field is refused, by name.
     * @param array<string, string>  $shown         The stored name and role the form is issued for, which its envelope
     *                                              seals.
     * @param string                 $stale         A 409's account of what changed; empty for none.
     *
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key is malformed.
     *
     * @return Form The edit form.
     */
    public function edit(
        ServerRequestInterface $serverRequest,
        string $path,
        MemberEntry $memberEntry,
        array $errors,
        array $shown,
        string $stale,
    ): Form {
        $formGrid = new FormGrid([
            $this->field('member-name', 'name', 'Name', $memberEntry->name, $errors, 'text'),
            new KeyedComponent('role', $this->role('member-role', $memberEntry->role, $errors['role'] ?? '')),
        ]);
        $fields = [];

        if ($errors !== []) {
            $fields[] = new Alert(AlertSeverity::Error, $errors['form'] ?? self::REFUSED, 'member-error');
        }

        if ($stale !== '') {
            $fields[] = new Alert(AlertSeverity::Error, $stale, 'member-stale');
        }

        $fields[] = $this->settingsSeals->field($path, $shown);
        $fields[] = $formGrid;
        $fields[] = new SubmitButton('Save', 'member-save', '');

        return new Form('member-form', $path, $fields, $this->formTokens->guard($serverRequest));
    }

    /**
     * @param string                $id     The control's id.
     * @param string                $field  The field's name.
     * @param string                $label  Its label.
     * @param string                $value  Its value.
     * @param array<string, string> $errors Why each refused field is refused, by name.
     * @param string                $type   Its control's type.
     *
     * @throws LogicException When the name is no key.
     *
     * @return KeyedComponent The field, keyed by its name; never browser-required, so an empty post reaches the checks.
     */
    private function field(
        string $id,
        string $field,
        string $label,
        string $value,
        array $errors,
        string $type,
    ): KeyedComponent {
        return new KeyedComponent($field, new FormField($id, $field, $label, $type, $value, $errors[$field] ?? '', false, false));
    }

    /**
     * @param string $id    The control's id.
     * @param string $value The chosen role's stored value; empty for none.
     * @param string $error Why the choice is refused; empty when it is not.
     *
     * @return SelectField The roles, the most rights first, the chosen one selected.
     */
    private function role(string $id, string $value, string $error): SelectField
    {
        $options = [];

        foreach ($this->roles->all() as $role) {
            $options[] = new SelectOption($role->value, $role->label(), $role->value === $value);
        }

        return new SelectField($id, 'role', 'Role', 'Choose a role', $options, $error, false);
    }
}
