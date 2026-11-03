<?php

declare(strict_types=1);

namespace App\Sales\Form;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use App\Sales\Entity\Sale;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\ConfirmCard\ConfirmCard;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function number_format;

/**
 * A sale's void page: the question, with the form that voids it (its envelope sealing the sale's path) and Keep it,
 * back to the sale.
 */
final readonly class SaleVoidForm
{
    /**
     * @param StaffPanels $staffPanels The sale pages' frame.
     * @param StaffSeals  $staffSeals  Seals the sale's path.
     * @param FormTokens  $formTokens  Issues the visitor's guard fields.
     */
    public function __construct(
        private StaffPanels $staffPanels,
        private StaffSeals $staffSeals,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param Sale                   $sale          The sale.
     *
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageDocument The page that asks first.
     */
    public function page(ServerRequestInterface $serverRequest, Sale $sale): PageDocument
    {
        $path = $sale->path();
        $form = new Form('sale-void-form', $path . '/void', [
            $this->staffSeals->field($path),
            new Button('Void the sale', 'submit', 'sale-void-confirm'),
        ], $this->formTokens->guard($serverRequest));
        $confirmCard = new ConfirmCard(
            'Void the sale of the ' . $sale->vehicle . '?',
            'Sold to ' . $sale->customer . ' on ' . $sale->soldOn . ' for $' . number_format($sale->price),
            'The sale is removed and the vehicle goes back in stock. Void this sale?',
            $form,
            new ButtonLink('Keep it', $path, 'sale-keep', ButtonTone::Ghost),
        );
        $description = 'A voided sale is gone for good, and the vehicle can be sold again.';

        return $this->staffPanels->page('Void a sale', 'Void a sale', $description, ['confirm' => $confirmCard], 200);
    }
}
