<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Session\Application\RequestSession;
use AIC\Session\Domain\RandomTokens;
use App\Chat\Entity\ChatVisitor;
use JsonException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;
use function preg_match;
use function strlen;
use function substr;

/**
 * The visitor as their session knows them (#32): `chat.visitor`, an `EnvelopeCodec` seal of their open chat's id and
 * their key. Visitors have no account; the envelope is how a request names their chat, and a chat is theirs only when
 * its row carries the same key. An altered or missing envelope reads as no chat and no key.
 */
final readonly class ChatVisitors
{
    /**
     * The session key holding the envelope.
     */
    public const string SESSION_KEY = 'chat.visitor';

    /**
     * How many hex digits a visitor's key has.
     */
    private const int KEY_LENGTH = 32;

    /**
     * @param EnvelopeCodec  $envelopeCodec  Seals and opens the envelope.
     * @param RequestSession $requestSession Reads the session off the request.
     * @param RandomTokens   $randomTokens   Makes the visitor's key, as AIC makes its session ids.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
        private RequestSession $requestSession = new RequestSession(),
        private RandomTokens $randomTokens = new RandomTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return ChatVisitor The chat and key the session's envelope holds; chat 0 and no key when it holds none.
     */
    public function visitor(ServerRequestInterface $serverRequest): ChatVisitor
    {
        $values = $this->opened($this->requestSession->read($serverRequest)->get(self::SESSION_KEY) ?? '');
        $chat = $values['chat'] ?? '';
        $key = $values['key'] ?? '';

        if (preg_match('/^[0-9a-f]{32}$/', $key) !== 1) {
            return new ChatVisitor(0, '');
        }

        $digits = $chat !== '' && strlen($chat) <= 18 && ctype_digit($chat);

        return new ChatVisitor($digits ? (int) $chat : 0, $key);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     * @param int                    $chatId        The visitor's open chat; 0 for none.
     * @param string                 $key           The visitor's key.
     *
     * @throws LogicException When the request carries no session, or the values cannot be sealed.
     */
    public function keep(ServerRequestInterface $serverRequest, int $chatId, string $key): void
    {
        $chat = $chatId > 0
            ? (string) $chatId
            : '';

        try {
            $token = $this->envelopeCodec->seal(['chat' => $chat, 'key' => $key]);
        } catch (JsonException $jsonException) {
            throw new LogicException('A chat visitor cannot be sealed.', 0, $jsonException);
        }

        $this->requestSession->read($serverRequest)->set(self::SESSION_KEY, $token);
    }

    /**
     * @param ChatVisitor $chatVisitor The visitor as the session knows them.
     *
     * @return string Their key; a new one when they have none.
     */
    public function key(ChatVisitor $chatVisitor): string
    {
        return $chatVisitor->key === ''
            ? substr($this->randomTokens->fresh(), 0, self::KEY_LENGTH)
            : $chatVisitor->key;
    }

    /**
     * @param string $token The session's envelope; empty for none.
     *
     * @return array<string, string> Its values; empty when there is none, or it was altered.
     */
    private function opened(string $token): array
    {
        if ($token === '') {
            return [];
        }

        try {
            return $this->envelopeCodec->openToken($token);
        } catch (InvalidEnvelope) {
            return [];
        }
    }
}
