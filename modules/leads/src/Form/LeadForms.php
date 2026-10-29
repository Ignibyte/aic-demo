<?php

declare(strict_types=1);

namespace App\Leads\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\KeyedComponent;
use AIC\Form\Application\FormTokens;
use App\Leads\Entity\LeadKind;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\Panel\Panel;
use App\Theme\Components\SubmitButton\SubmitButton;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A car's two lead panels (#18): "Ask about this car" and "Book a test drive", each the theme's Panel keyed and
 * anchored (`car-inquiry`, `car-test-drive`), holding AIC's Form (its token and honeypot with it) whose Send button
 * targets that anchor, so Tessera swaps the panel alone. After a send the panel holds a thank-you instead; after a
 * refusal, the form again with what was typed and each error beside its field.
 */
final readonly class LeadForms
{
    /**
     * @param FormTokens $formTokens Issues the visitor's CSRF token, opening a session for one who never signed in.
     * @param LeadFields $leadFields The forms' fields.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
        private LeadFields $leadFields = new LeadFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request for the car's page, with its session.
     * @param string                 $path          The car's public page, which the forms post below.
     *
     * @throws LogicException When the request carries no session, or a key or an anchor is malformed.
     *
     * @return list<KeyedComponent> Both panels, blank.
     */
    public function blank(ServerRequestInterface $serverRequest, string $path): array
    {
        $token = $this->formTokens->issue($serverRequest)->value();
        $leadEntry = new LeadEntry('', '', '', '', '', '');

        return [
            $this->formPanel(LeadKind::Inquiry, $path, $token, $leadEntry, []),
            $this->formPanel(LeadKind::TestDrive, $path, $token, $leadEntry, []),
        ];
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     * @param string                 $path          The car's public page.
     * @param LeadKind               $leadKind      Which form was sent.
     * @param LeadEntry              $leadEntry     What was typed, or what was stored when it passed.
     * @param array<string, string>  $errors        Why each refused field is refused; empty when the lead was recorded.
     *
     * @throws LogicException When the request carries no session, or a key or an anchor is malformed.
     *
     * @return list<KeyedComponent> Both panels: the sent one thanked, or refused with its errors; the other blank.
     */
    public function answered(
        ServerRequestInterface $serverRequest,
        string $path,
        LeadKind $leadKind,
        LeadEntry $leadEntry,
        array $errors,
    ): array {
        $token = $this->formTokens->issue($serverRequest)->value();
        $other = $leadKind->other();
        $keyedComponent = $this->sentPanel($leadKind, $path, $token, $leadEntry, $errors);
        $blank = $this->formPanel($other, $path, $token, new LeadEntry('', '', '', '', '', ''), []);

        return $leadKind === LeadKind::Inquiry
            ? [$keyedComponent, $blank]
            : [$blank, $keyedComponent];
    }

    /**
     * @param LeadKind              $leadKind  The form that was sent.
     * @param string                $path      The car's public page.
     * @param string                $token     The visitor's CSRF token.
     * @param LeadEntry             $leadEntry What was typed, or what was stored.
     * @param array<string, string> $errors    Why each refused field is refused; empty when the lead was recorded.
     *
     * @throws LogicException When a key or an anchor is malformed.
     *
     * @return KeyedComponent Its panel: the thank-you, or the form refused.
     */
    private function sentPanel(
        LeadKind $leadKind,
        string $path,
        string $token,
        LeadEntry $leadEntry,
        array $errors,
    ): KeyedComponent {
        if ($errors !== []) {
            return $this->formPanel($leadKind, $path, $token, $leadEntry, $errors);
        }

        return $this->panel($leadKind, [new KeyedComponent('thanks', $this->thanks($leadKind, $leadEntry))]);
    }

    /**
     * @param LeadKind              $leadKind  Which form.
     * @param string                $path      The car's public page.
     * @param string                $token     The visitor's CSRF token.
     * @param LeadEntry             $leadEntry The values to show.
     * @param array<string, string> $errors    Why each refused field is refused, by name.
     *
     * @throws LogicException When a key or an anchor is malformed.
     *
     * @return KeyedComponent The panel holding the form, an alert above it when anything was refused.
     */
    private function formPanel(
        LeadKind $leadKind,
        string $path,
        string $token,
        LeadEntry $leadEntry,
        array $errors,
    ): KeyedComponent {
        $slug = $leadKind->slug();
        $body = $errors === []
            ? []
            : [new KeyedComponent('alert', new Alert(AlertSeverity::Error, 'Fix the fields marked below.', $slug . '-error'))];
        $send = $leadKind === LeadKind::Inquiry
            ? 'Send question'
            : 'Ask for this drive';
        $fields = [
            $this->leadFields->grid($leadKind, $leadEntry, $errors),
            $this->leadFields->message($leadKind, $leadEntry, $errors['message'] ?? ''),
            new SubmitButton($send, $slug . '-send', '@car-' . $slug),
        ];
        $body[] = new KeyedComponent('form', new Form($slug . '-form', $path . '/' . $slug, $fields, $token));

        return $this->panel($leadKind, $body);
    }

    /**
     * @param LeadKind  $leadKind  Which form was sent.
     * @param LeadEntry $leadEntry What was stored.
     *
     * @return Notice The thank-you the panel shows instead of its form.
     */
    private function thanks(LeadKind $leadKind, LeadEntry $leadEntry): Notice
    {
        $message = $leadKind === LeadKind::Inquiry
            ? 'Thanks, ' . $leadEntry->name . '. We have your question and will reply to ' . $leadEntry->email . ' soon.'
            : 'Thanks, ' . $leadEntry->name . '. We have your request for ' . $leadEntry->driveOn . ' at ' . $leadEntry->driveAt
                . ' and will call ' . $leadEntry->phone . ' to confirm it.';

        return new Notice($message, NoticeTone::Success, $leadKind->slug() . '-thanks');
    }

    /**
     * @param LeadKind             $leadKind Which panel.
     * @param list<KeyedComponent> $body     What it holds.
     *
     * @throws LogicException When a key or an anchor is malformed.
     *
     * @return KeyedComponent The panel, keyed by its slug and anchored `car-inquiry` or `car-test-drive`.
     */
    private function panel(LeadKind $leadKind, array $body): KeyedComponent
    {
        $anchor = 'car-' . $leadKind->slug();
        $panel = $leadKind === LeadKind::Inquiry
            ? new Panel($anchor, 'Ask about this car', 'A salesperson reads every question and replies by email.', false, $body)
            : new Panel($anchor, 'Book a test drive', 'Monday to Saturday, from 09:00 to 17:00. We call to confirm.', false, $body);

        return new KeyedComponent($leadKind->slug(), $panel, '', $anchor);
    }
}
