<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use App\Theme\Components\CaseWatch\CaseWatch;
use App\Theme\Components\CaseWatch\WatchedEvent;
use App\Theme\Components\FeedStatus\FeedStatus;
use App\Theme\Components\FormField\FormField;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The style guide's watched resilience cases (#34), each in a `CaseWatch` that counts the event its case raises: the
 * expired page (`tessera:expired`), the write with no answer (`tessera:indeterminate`) and the failing poll
 * (`tessera:error`). The frame is `ResilienceCases`'.
 */
final readonly class ResilienceWatches
{
    /**
     * @param ResilienceCases  $resilienceCases  The case pages' frame.
     * @param ResilienceOutage $resilienceOutage The feed's outage.
     * @param FormTokens       $formTokens       Issues each form's guard fields.
     */
    public function __construct(
        private ResilienceCases $resilienceCases,
        private ResilienceOutage $resilienceOutage = new ResilienceOutage(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, for the visitor's token.
     *
     * @throws LogicException When a key is malformed, the page's regions do not fit the theme, or the request carries
     *                        no session.
     *
     * @return PageDocument The expired page's case: the lapse form, then the send form.
     */
    public function expired(ServerRequestInterface $serverRequest): PageDocument
    {
        $forms = [
            $this->form($serverRequest, 'expired-lapse', ResilienceCases::EXPIRED, [
                new HiddenInput('intent', 'lapse'),
                new Button('Expire the token', 'submit', 'expired-lapse-go'),
            ]),
            $this->form($serverRequest, 'expired-send', ResilienceCases::EXPIRED, [
                new HiddenInput('intent', 'send'),
                new Button('Send', 'submit', 'expired-send-go'),
            ]),
        ];

        return $this->resilienceCases->page(
            'expired',
            'An expired page',
            'A write whose token the session no longer holds',
            'Expire the token, then send: the form guard answers 419, and Tessera raises tessera:expired and offers'
            . ' to reload the page.',
            [$this->watch('expired-watch', WatchedEvent::Expired, $forms)],
            200,
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, for the visitor's token.
     *
     * @throws LogicException When a key is malformed, the page's regions do not fit the theme, or the request carries
     *                        no session.
     *
     * @return PageDocument The write with no answer's case: a note form the gateway answers.
     */
    public function gateway(ServerRequestInterface $serverRequest): PageDocument
    {
        $keyedComponent = $this->form($serverRequest, 'gateway-form', ResilienceCases::GATEWAY, [
            new FormField('gateway-note', 'note', 'A note', 'text', '', '', false, false),
            new Button('Save the note', 'submit', 'gateway-save'),
        ]);

        return $this->resilienceCases->page(
            'gateway',
            'A write with no answer',
            'A gateway gave up waiting and answered 504 in plain text',
            'Save the note: the gateway in front of the app answers 504 in plain text, so Tessera cannot tell whether'
            . ' the write was saved. It raises tessera:indeterminate and says so above the form.',
            [$this->watch('gateway-watch', WatchedEvent::Indeterminate, [$keyedComponent])],
            200,
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: the visitor's token and the outage.
     *
     * @throws LogicException When a key is malformed, the page's regions do not fit the theme, or the request carries
     *                        no session.
     *
     * @return PageDocument The failing poll's case: the take-down form, then the polled feed, anchored `resilience-feed`.
     */
    public function feed(ServerRequestInterface $serverRequest): PageDocument
    {
        $keyedComponent = $this->form($serverRequest, 'feed-down', ResilienceCases::FEED, [
            new Button('Take the feed down', 'submit', 'feed-down-go'),
        ]);
        $feedStatus = new FeedStatus(ResilienceCases::FEED, $this->feedMessage($serverRequest));

        return $this->resilienceCases->page(
            'feed',
            'A failing poll',
            'A feed that stops answering, then answers again',
            'Take the feed down: its next ' . ResilienceOutage::POLLS . ' polls are answered 503, and Tessera raises'
            . ' tessera:error for each. The poll after them answers again.',
            [$this->watch('feed-watch', WatchedEvent::Error, [
                $keyedComponent,
                new KeyedComponent('feed', $feedStatus, '', 'resilience-feed'),
            ])],
            200,
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the outage in its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return string Whether the feed is up, going down, or back after an outage.
     */
    private function feedMessage(ServerRequestInterface $serverRequest): string
    {
        $down = $this->resilienceOutage->down($serverRequest);

        if ($down > 0) {
            return 'The feed is down for the next ' . $down . ' polls.';
        }

        $failed = $this->resilienceOutage->failed($serverRequest);

        return $failed > 0
            ? 'The feed answered again after ' . $failed . ' failed polls.'
            : 'The feed is up. Tessera polls it every second.';
    }

    /**
     * @param string               $id           The watch's id, also its key's stem.
     * @param WatchedEvent         $watchedEvent The event it counts.
     * @param list<KeyedComponent> $body         What it holds.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return KeyedComponent The watch, keyed `watch`.
     */
    private function watch(string $id, WatchedEvent $watchedEvent, array $body): KeyedComponent
    {
        return new KeyedComponent('watch', new CaseWatch($id, $watchedEvent, $body));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, for the visitor's token.
     * @param string                 $id            The form's id, also its key.
     * @param string                 $action        Where it posts.
     * @param list<Component>        $fields        Its fields and its button.
     *
     * @throws LogicException When a key is malformed, or the request carries no session.
     *
     * @return KeyedComponent The form, with the session's guard fields, keyed by its id.
     */
    private function form(
        ServerRequestInterface $serverRequest,
        string $id,
        string $action,
        array $fields,
    ): KeyedComponent {
        return new KeyedComponent($id, new Form($id, $action, $fields, $this->formTokens->guard($serverRequest)));
    }
}
