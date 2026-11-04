<?php

declare(strict_types=1);

namespace App\Compare\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\PageDocument;
use AIC\Tessera\Domain\TesseraRequest;
use App\Compare\Form\CompareQuery;
use App\Storefront\Service\CarSlugs;
use App\Storefront\Service\StorePages;
use App\Theme\Components\CompareDrawer\CompareDrawer;
use App\Theme\Components\CompareTable\CompareTable;
use App\Theme\Components\StoreHeading\StoreHeading;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function count;

/**
 * The comparison's one flow (#33). Four or more distinct slugs are refused with 422 before any car is read. Otherwise
 * `ComparisonTables` gives the cars side by side, or nothing when none of them is for sale. A Tessera request (the bar's
 * Compare link, which targets the drawer) gets the store frame with its drawer filled and only the heading in
 * `content`; a plain one (the bar's form with JavaScript off) gets the table in `content` and the drawer empty, so no
 * answer holds the table twice. The dealership's profile is read once, for the payments' APR and the frame; the chat's
 * dock comes from the page.
 */
final readonly class CarComparisons
{
    /**
     * The page's title.
     */
    private const string TITLE = 'Compare cars';

    /**
     * What a refusal of four or more says.
     */
    private const string TOO_MANY = 'Compare up to three cars at a time.';

    /**
     * What it says when none of the asked cars is for sale.
     */
    private const string NONE = 'No car to compare.';

    /**
     * @param StorePages       $storePages       The store's frame and the dealership's profile.
     * @param ComparisonTables $comparisonTables The cars side by side.
     * @param CompareQuery     $compareQuery     The asked slugs.
     */
    public function __construct(
        private StorePages $storePages,
        private ComparisonTables $comparisonTables,
        private CompareQuery $compareQuery = new CompareQuery(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest  The request, with its query and its session.
     * @param KeyedComponent|null    $keyedComponent The chat's dock, which every store page carries; null for none.
     *
     * @throws LogicException   When an entity type, a key or a region is malformed, or the request has no session.
     * @throws RuntimeException When the cars, their photos or the profile cannot be read.
     *
     * @return PageDocument The comparison (200), or the refusal of four or more (422).
     */
    public function page(ServerRequestInterface $serverRequest, ?KeyedComponent $keyedComponent): PageDocument
    {
        $slugs = $this->compareQuery->slugs($serverRequest);
        $dealershipProfile = $this->storePages->profile();
        $refused = count($slugs) > CompareQuery::MOST;
        $compareTable = $refused
            ? null
            : $this->comparisonTables->table($slugs, $dealershipProfile->aprBasisPoints);
        $message = match (true) {
            $refused => self::TOO_MANY,
            $compareTable instanceof CompareTable => '',
            default => self::NONE,
        };
        $tessera = $serverRequest->getHeaderLine(TesseraRequest::REQUEST) !== '';
        $parts = [new KeyedComponent('heading', $this->heading($message))];

        if (!$tessera && $compareTable instanceof CompareTable) {
            $parts[] = new KeyedComponent('comparison', $compareTable);
        }

        $compareDrawer = $tessera
            ? $this->drawer($message, $compareTable)
            : null;

        return $this->storePages->framed($dealershipProfile, self::TITLE, $parts, $refused ? 422 : 200, $keyedComponent, $compareDrawer);
    }

    /**
     * @param string $message Why there is no table; empty when there is one.
     *
     * @return StoreHeading The page's heading: the comparison's, or what stands in its place.
     */
    private function heading(string $message): StoreHeading
    {
        $lead = match ($message) {
            self::TOO_MANY => 'Untick a car, then compare again.',
            self::NONE => 'Tick Compare on up to three cars to see them side by side.',
            default => 'The cars you chose, side by side.',
        };

        $title = $message === ''
            ? self::TITLE
            : $message;

        return new StoreHeading('compare-title', $title, $lead, CarSlugs::PAGES, 'See every car for sale');
    }

    /**
     * @param string            $message      Why there is no table; empty when there is one.
     * @param CompareTable|null $compareTable The cars side by side; null for none.
     *
     * @return CompareDrawer The drawer for a Tessera answer: the table, or what stands in its place.
     */
    private function drawer(string $message, ?CompareTable $compareTable): CompareDrawer
    {
        $tables = $compareTable instanceof CompareTable
            ? [new KeyedComponent('table', $compareTable)]
            : [];
        $text = $message === self::NONE
            ? self::NONE . ' Tick Compare on up to three cars to see them side by side.'
            : $message;

        return new CompareDrawer($text, $tables);
    }
}
