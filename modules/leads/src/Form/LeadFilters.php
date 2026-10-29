<?php

declare(strict_types=1);

namespace App\Leads\Form;

use AIC\Component\Domain\KeyedComponent;
use App\Leads\Entity\Lead;
use App\Leads\Entity\LeadKind;
use App\Leads\Entity\Seller;
use App\Theme\Components\FilterBar\FilterBar;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\SelectField\SelectOption;
use InvalidArgumentException;

/**
 * The leads list's filter bar (#18): a kind and a salesperson, each chosen from a select, in a plain GET form that
 * answers the content region in place.
 */
final readonly class LeadFilters
{
    /**
     * @param LeadQuery    $leadQuery What the list keeps.
     * @param list<Seller> $sellers   The salespeople.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FilterBar The kind and salesperson selects, what the list keeps chosen.
     */
    public function filterBar(LeadQuery $leadQuery, array $sellers): FilterBar
    {
        // Each kind through a typed parameter: elephc loses an enum case's type in a loop over a list of them (AIC #64, #164).
        $kinds = [$this->kindOption(LeadKind::Inquiry, $leadQuery->kind), $this->kindOption(LeadKind::TestDrive, $leadQuery->kind)];
        $people = [];

        foreach ($sellers as $seller) {
            $people[] = new SelectOption($seller->email, $seller->name, $seller->email === $leadQuery->seller);
        }

        $kind = new SelectField('filter-kind', 'kind', 'Kind', 'Every kind', $kinds, '', true);
        $salesperson = new SelectField('filter-salesperson', 'salesperson', 'Salesperson', 'Everyone', $people, '', true);
        $fields = [new KeyedComponent('kind', $kind), new KeyedComponent('salesperson', $salesperson)];

        return new FilterBar('leads-filters', Lead::PAGES, 'content', $fields, Lead::PAGES);
    }

    /**
     * @param LeadKind $leadKind A kind.
     * @param string   $chosen   The kind the list keeps; empty for both.
     *
     * @return SelectOption Its option, chosen when the list keeps it.
     */
    private function kindOption(LeadKind $leadKind, string $chosen): SelectOption
    {
        return new SelectOption($leadKind->value, $leadKind->label(), $leadKind->value === $chosen);
    }
}
