<?php

declare(strict_types=1);

namespace App\Settings\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\KeyedComponent;
use AIC\Form\Application\FormTokens;
use App\Settings\Entity\DealershipProfile;
use App\Theme\Components\CheckGroup\CheckGroup;
use App\Theme\Components\CheckGroup\CheckOption;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\FormGrid\FormGrid;
use App\Theme\Components\SubmitButton\SubmitButton;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function str_contains;

/**
 * The profile form (#22): AIC's Form posting to `/app/settings/profile`, an alert above its fields when a post was
 * refused (`profile-error`) or stale (`profile-stale`), the envelope sealing the values it was issued for, the grid of
 * fields (`profile-<name>` each), the open days as the theme's check group (`profile-day-1`, Monday, to
 * `profile-day-7`) and the Save button.
 */
final readonly class ProfileFields
{
    /**
     * Where the form posts.
     */
    public const string ACTION = '/app/settings/profile';

    /**
     * The days' boxes in order, Monday (ISO day 1) first.
     */
    private const array DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * @param SettingsSeals $settingsSeals Seals the values the form was issued for.
     * @param FormTokens    $formTokens    Issues the visitor's CSRF token.
     */
    public function __construct(
        private SettingsSeals $settingsSeals,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest     The request, with its session.
     * @param DealershipProfile      $dealershipProfile The stored profile.
     *
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key is malformed.
     *
     * @return Form The form filled from the stored profile and sealing it.
     */
    public function stored(ServerRequestInterface $serverRequest, DealershipProfile $dealershipProfile): Form
    {
        $values = $dealershipProfile->values();

        return $this->form($serverRequest, new ProfileEntry($values), [], $values, '');
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param ProfileEntry           $profileEntry  The values the fields show.
     * @param array<string, string>  $errors        Why each refused field is refused, by name.
     * @param array<string, string>  $shown         The stored values the form is issued for, which its envelope seals.
     * @param string                 $stale         A 409's account of what changed; empty for none.
     *
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key is malformed.
     *
     * @return Form The form.
     */
    public function form(
        ServerRequestInterface $serverRequest,
        ProfileEntry $profileEntry,
        array $errors,
        array $shown,
        string $stale,
    ): Form {
        $fields = [];

        if ($errors !== []) {
            $fields[] = new Alert(AlertSeverity::Error, 'Fix the fields marked below.', 'profile-error');
        }

        if ($stale !== '') {
            $fields[] = new Alert(AlertSeverity::Error, $stale, 'profile-stale');
        }

        $fields[] = $this->settingsSeals->field(self::ACTION, $shown);
        $fields[] = $this->grid($profileEntry, $errors);
        $fields[] = $this->days($profileEntry->value('open_days'), $errors['days'] ?? '');
        $fields[] = new SubmitButton('Save the profile', 'profile-save', '');

        return new Form('profile-form', self::ACTION, $fields, $this->formTokens->issue($serverRequest)->value());
    }

    /**
     * @param ProfileEntry          $profileEntry The values to show.
     * @param array<string, string> $errors       Why each refused field is refused, by name.
     *
     * @throws LogicException When a key is malformed.
     *
     * @return FormGrid The text fields, `profile-<name>` each.
     */
    private function grid(ProfileEntry $profileEntry, array $errors): FormGrid
    {
        return new FormGrid([
            $this->field($profileEntry, $errors, 'name', 'Dealership name', 'text'),
            $this->field($profileEntry, $errors, 'phone', 'Phone (optional)', 'tel'),
            $this->field($profileEntry, $errors, 'address', 'Address (optional)', 'text'),
            $this->field($profileEntry, $errors, 'opens_at', 'Opens at', 'time'),
            $this->field($profileEntry, $errors, 'closes_at', 'Closes at', 'time'),
            $this->field($profileEntry, $errors, 'aged_stock_days', 'Aged stock after (days)', 'number'),
        ]);
    }

    /**
     * @param string $openDays The ticked days, ISO weekday digits.
     * @param string $error    Why the days are refused; empty when they are not.
     *
     * @return CheckGroup The seven boxes, Monday first.
     */
    private function days(string $openDays, string $error): CheckGroup
    {
        $options = [];

        foreach (self::DAYS as $index => $day) {
            $isoDay = (string) ($index + 1);
            $options[] = new CheckOption('profile-day-' . $isoDay, 'day_' . $isoDay, $day, str_contains($openDays, $isoDay));
        }

        return new CheckGroup('profile-days', 'Open on', $options, $error);
    }

    /**
     * @param ProfileEntry          $profileEntry The values to show.
     * @param array<string, string> $errors       Why each refused field is refused, by name.
     * @param string                $field        The field's name.
     * @param string                $label        Its label.
     * @param string                $type         Its control's type.
     *
     * @throws LogicException When the name is no key.
     *
     * @return KeyedComponent The field, keyed by its name; never browser-required, so an empty post reaches the checks.
     */
    private function field(
        ProfileEntry $profileEntry,
        array $errors,
        string $field,
        string $label,
        string $type,
    ): KeyedComponent {
        $value = $profileEntry->value($field);
        $formField = new FormField('profile-' . $field, $field, $label, $type, $value, $errors[$field] ?? '', false, false);

        return new KeyedComponent($field, $formField);
    }
}
