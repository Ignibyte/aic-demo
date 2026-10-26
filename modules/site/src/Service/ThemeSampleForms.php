<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\KeyedComponent;
use AIC\Form\Application\FormTokens;
use App\Site\Form\ThemeEntry;
use App\Theme\Components\FormActions\FormActions;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\FormGrid\FormGrid;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\SelectField\SelectOption;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The style guide's sample vehicle form, posting to `/theme` through the framework's Form atom: its fields in a grid,
 * each with its error, and an alert above them when anything is wrong.
 */
final readonly class ThemeSampleForms
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
     * @param ThemeEntry             $themeEntry    What was posted; empty on a first visit.
     * @param array<string, string>  $problems      What is wrong, by field name.
     *
     * @throws LogicException When a key is malformed, or the request carries no session.
     *
     * @return Form The vehicle form, an alert above it when anything is wrong.
     */
    public function vehicle(ServerRequestInterface $serverRequest, ThemeEntry $themeEntry, array $problems): Form
    {
        $alerts = $problems === []
            ? []
            : [new Alert(AlertSeverity::Error, 'Fix the fields marked below.', 'vehicle-error')];
        $formGrid = $this->grid($themeEntry, $problems);
        $formActions = new FormActions('Save', 'vehicle-save', '/theme');
        $fields = [...$alerts, new HiddenInput('intent', 'save'), $formGrid, $formActions];

        return new Form('vehicle-form', '/theme', $fields, $this->formTokens->issue($serverRequest)->value());
    }

    /**
     * @param ThemeEntry            $themeEntry The values.
     * @param array<string, string> $problems   What is wrong, by field name.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FormGrid The name, price, body style and notes fields.
     */
    private function grid(ThemeEntry $themeEntry, array $problems): FormGrid
    {
        $formField = $this->field('price', 'Price, in dollars', 'number', $themeEntry->price, $problems);

        return new FormGrid([
            new KeyedComponent('name', $this->field('name', 'Name', 'text', $themeEntry->name, $problems)),
            new KeyedComponent('price', $formField),
            new KeyedComponent('body_style', $this->bodyStyle($themeEntry, $problems['body_style'] ?? '')),
            new KeyedComponent('notes', $this->field('notes', 'Notes', 'textarea', $themeEntry->notes, $problems)),
        ]);
    }

    /**
     * @param ThemeEntry $themeEntry The values.
     * @param string     $error      What is wrong with the choice; empty when nothing is.
     *
     * @return SelectField The body style select, the posted one chosen.
     */
    private function bodyStyle(ThemeEntry $themeEntry, string $error): SelectField
    {
        $options = [];

        foreach (ThemeEntry::BODY_STYLES as $value => $label) {
            $options[] = new SelectOption($value, $label, $value === $themeEntry->bodyStyle);
        }

        return new SelectField('vehicle-body_style', 'body_style', 'Body style', 'Choose one', $options, $error, false);
    }

    /**
     * @param string                $name     The field's name.
     * @param string                $label    Its label.
     * @param string                $type     Its input type, or `textarea`.
     * @param string                $value    What was posted.
     * @param array<string, string> $problems What is wrong, by field name.
     *
     * @return FormField The field, with its error when it has one; never required, so an empty post reaches the
     *                   server and answers its errors.
     */
    private function field(string $name, string $label, string $type, string $value, array $problems): FormField
    {
        $error = $problems[$name] ?? '';

        return new FormField('vehicle-' . $name, $name, $label, $type, $value, $error, false, false);
    }
}
