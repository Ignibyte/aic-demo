<?php

declare(strict_types=1);

namespace App\Leads\Form;

use AIC\Component\Domain\Form;
use AIC\Form\Application\FormTokens;
use App\Leads\Entity\Lead;
use App\Leads\Entity\Seller;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\SelectField\SelectOption;
use App\Theme\Components\SubmitButton\SubmitButton;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The forms on an open lead's page for someone who may move it (#18): reassign it to another salesperson, named by
 * email as sales' form names one, and close it.
 */
final readonly class LeadActions
{
    /**
     * @param FormTokens $formTokens Issues the CSRF token.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its session.
     * @param Lead                   $lead          The lead.
     * @param list<Seller>           $sellers       The salespeople.
     * @param string                 $error         Why the last reassign was refused; empty when none was.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return Form Reassign: the salespeople, the lead's own chosen.
     */
    public function assign(ServerRequestInterface $serverRequest, Lead $lead, array $sellers, string $error): Form
    {
        $seller = $lead->seller;
        $current = $seller instanceof Seller
            ? $seller->email
            : '';
        $options = [];

        foreach ($sellers as $choice) {
            $options[] = new SelectOption($choice->email, $choice->name, $choice->email === $current);
        }

        $selectField = new SelectField('lead-salesperson', 'salesperson', 'Salesperson', 'Choose a salesperson', $options, $error, false);
        $fields = [$selectField, new SubmitButton('Reassign', 'lead-assign', '')];

        return new Form('lead-assign-form', $lead->path() . '/assign', $fields, $this->formTokens->issue($serverRequest)->value());
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its session.
     * @param Lead                   $lead          The lead.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return Form Close: one button.
     */
    public function close(ServerRequestInterface $serverRequest, Lead $lead): Form
    {
        $fields = [new SubmitButton('Close this lead', 'lead-close', '')];

        return new Form('lead-close-form', $lead->path() . '/close', $fields, $this->formTokens->issue($serverRequest)->value());
    }
}
