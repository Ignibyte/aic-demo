<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use App\Site\Form\ThemeEntry;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The style guide's resilience cases that need no watch (#34), built from the theme's parts in `ResilienceCases`'
 * frame: the refused form (the guide's vehicle form posting to its case), the protocol mismatch (a link to its own
 * page) and the full visit (a form whose answer asks for its page to load whole).
 */
final readonly class ResilienceSamples
{
    /**
     * @param ResilienceCases  $resilienceCases  The case pages' frame.
     * @param ThemeSampleForms $themeSampleForms The guide's vehicle form.
     * @param FormTokens       $formTokens       Issues each form's guard fields.
     */
    public function __construct(
        private ResilienceCases $resilienceCases,
        private ThemeSampleForms $themeSampleForms = new ThemeSampleForms(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, for the visitor's token.
     * @param ThemeEntry             $themeEntry    What was posted; empty on a first visit.
     * @param array<string, string>  $problems      What is wrong, by field name.
     *
     * @throws LogicException When a key is malformed, the page's regions do not fit the theme, or the request carries
     *                        no session.
     *
     * @return PageDocument The refused form's page: 200, or 422 with each field's error.
     */
    public function refused(
        ServerRequestInterface $serverRequest,
        ThemeEntry $themeEntry,
        array $problems,
    ): PageDocument {
        $form = $this->themeSampleForms->vehicle($serverRequest, $themeEntry, $problems, ResilienceCases::REFUSED);

        return $this->resilienceCases->page(
            'refused',
            'A refused form',
            'Several errors, and the summary that takes the focus',
            'Save the form empty: the answer is 422 with an error for each field, swapped in place, and Tessera moves'
            . ' the focus to the summary above them.',
            [new KeyedComponent('vehicle', $form)],
            $problems === [] ? 200 : 422,
        );
    }

    /**
     * @throws LogicException When a key is malformed, or the page's regions do not fit the theme.
     *
     * @return PageDocument The protocol mismatch's page, with a link to itself.
     */
    public function protocol(): PageDocument
    {
        return $this->resilienceCases->page(
            'protocol',
            'A protocol mismatch',
            'A server that speaks only protocol 1',
            'Every Tessera request to this page is answered 400 with Tessera-Protocol: 1, as a server that speaks only'
            . ' protocol 1 answers, so Tessera loads the page whole instead.',
            [new KeyedComponent('again', new Link('Visit this page again', ResilienceCases::PROTOCOL))],
            200,
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, for the visitor's token.
     *
     * @throws LogicException When a key is malformed, the page's regions do not fit the theme, or the request carries
     *                        no session.
     *
     * @return PageDocument The full visit's page, with its form.
     */
    public function fullVisit(ServerRequestInterface $serverRequest): PageDocument
    {
        $button = new Button('Leave by a full visit', 'submit', 'full-visit-go');
        $guardFields = $this->formTokens->guard($serverRequest);
        $form = new Form('full-visit-form', ResilienceCases::FULL_VISIT, [$button], $guardFields);

        return $this->resilienceCases->page(
            'full-visit',
            'A full visit',
            'An answer that asks for the page to load whole',
            'Leave by a full visit: the answer carries Tessera-Full-Visit, and Tessera hands the address to the'
            . ' browser, which loads the page whole.',
            [new KeyedComponent('leave', $form)],
            200,
        );
    }
}
