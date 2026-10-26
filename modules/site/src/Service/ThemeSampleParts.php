<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use App\Theme\Components\DetailCard\DetailCard;
use App\Theme\Components\DetailCard\Fact;
use App\Theme\Components\Icon\Heroicons;
use App\Theme\Components\IconEntry\IconEntry;
use App\Theme\Components\IconLegend\IconLegend;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\StatusBadge\BadgeTone;
use App\Theme\Components\StatusBadge\StatusBadge;
use InvalidArgumentException;

/**
 * The style guide's smaller parts with sample data: a vehicle's card, a badge and a notice in each tone, and every icon
 * with the area it stands for.
 */
final readonly class ThemeSampleParts
{
    /**
     * @param Heroicons $heroicons The icons.
     */
    public function __construct(
        private Heroicons $heroicons = new Heroicons(),
    ) {
    }

    /**
     * @return DetailCard A sample vehicle's card.
     */
    public function record(): DetailCard
    {
        $statusBadge = new StatusBadge('In stock', BadgeTone::Success, 'vehicle-status');

        return new DetailCard('vehicle-title', '2023 Ford F-150 XLT', $statusBadge, [
            new Fact('VIN', '1FTFW1E50PFA10233', true),
            new Fact('Body style', 'Truck', false),
            new Fact('Exterior colour', 'Agate Black', false),
            new Fact('Mileage', '18,420 mi', false),
            new Fact('List price', '$41,900', false),
            new Fact('On the lot since', '2026-09-14', false),
        ], 'No sale recorded yet.', [
            new ButtonLink('Record a sale', '/theme', 'vehicle-sell', ButtonTone::Secondary),
            new ButtonLink('Edit', '/theme', 'vehicle-edit', ButtonTone::Primary),
            new ButtonLink('Delete', '/theme', 'vehicle-delete', ButtonTone::Error),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return list<KeyedComponent> A badge in each tone.
     */
    public function badges(): array
    {
        return [
            new KeyedComponent('in-stock', new StatusBadge('In stock', BadgeTone::Success)),
            new KeyedComponent('on-hold', new StatusBadge('On hold', BadgeTone::Warning)),
            new KeyedComponent('sold', new StatusBadge('Sold', BadgeTone::Neutral)),
            new KeyedComponent('voided', new StatusBadge('Voided', BadgeTone::Error)),
        ];
    }

    /**
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return list<KeyedComponent> A notice in each tone.
     */
    public function notices(): array
    {
        return [
            new KeyedComponent('info', new Notice('The newest vehicles come first.', NoticeTone::Info, 'notice-info')),
            new KeyedComponent('success', new Notice('The sale is recorded.', NoticeTone::Success, 'notice-success')),
            new KeyedComponent('warning', new Notice(
                'This customer bought a vehicle, so they stay on record and cannot be deleted.',
                NoticeTone::Warning,
                'notice-warning',
            )),
        ];
    }

    /**
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return IconLegend Every icon with the area it stands for.
     */
    public function icons(): IconLegend
    {
        return new IconLegend([
            new KeyedComponent('home', new IconEntry($this->heroicons->home(), 'Dashboard')),
            new KeyedComponent('truck', new IconEntry($this->heroicons->truck(), 'Inventory')),
            new KeyedComponent('users', new IconEntry($this->heroicons->users(), 'Customers')),
            new KeyedComponent('banknotes', new IconEntry($this->heroicons->banknotes(), 'Sales')),
            new KeyedComponent('chart-bar', new IconEntry($this->heroicons->chartBar(), 'Reports')),
            new KeyedComponent('cog', new IconEntry($this->heroicons->cog(), 'Theme')),
        ]);
    }
}
