<?php

declare(strict_types=1);

namespace App\Chat\Form;

use AIC\Form\Application\PostedFields;
use AIC\Form\Domain\SameOriginPath;
use Psr\Http\Message\ServerRequestInterface;

use function mb_strlen;
use function preg_match;
use function str_replace;
use function strlen;
use function strtolower;
use function trim;

/**
 * A chat form's fields and their checks (#32): the message, of 1 to 1,000 characters once trimmed, its line breaks
 * kept; the visitor's name, at most 80 and one line, "Visitor" when none is given; and their email, which may be left
 * empty. Each check answers the reason it refuses, or nothing.
 */
final readonly class ChatChecks
{
    /**
     * The longest message, in characters.
     */
    public const int LONGEST = 1_000;

    /**
     * Why a send over the limit is refused.
     */
    public const string TOO_QUICK = 'You are sending messages quickly. Wait a minute, then send again.';

    /**
     * Why a start over the limit is refused.
     */
    public const string TOO_MANY = 'You have started three chats this hour. Wait a while, then start another.';

    /**
     * @param PostedFields $postedFields Reads a posted field.
     */
    public function __construct(
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted chat form.
     *
     * @return string Its message, trimmed, each line break as one line feed; empty when it is missing or not text.
     */
    public function message(ServerRequestInterface $serverRequest): string
    {
        return trim(str_replace("\r", "\n", str_replace("\r\n", "\n", $this->postedFields->value($serverRequest, 'message'))));
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted start form.
     *
     * @return string The name as typed, trimmed.
     */
    public function name(ServerRequestInterface $serverRequest): string
    {
        return trim($this->postedFields->value($serverRequest, 'name'));
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted start form.
     *
     * @return string The email as typed, trimmed and lower case.
     */
    public function email(ServerRequestInterface $serverRequest): string
    {
        return strtolower(trim($this->postedFields->value($serverRequest, 'email')));
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted chat form.
     * @param string                 $otherwise     Where to go when `back` names no store page.
     *
     * @return string Its `back` when it is a storefront page (`/cars`, below it, or `/chat`) on this site, by AIC's own
     *                same-origin rule; else `$otherwise`.
     */
    public function back(ServerRequestInterface $serverRequest, string $otherwise): string
    {
        $back = $this->postedFields->value($serverRequest, 'back');
        $store = preg_match('#^/(cars|chat)([/?]|$)#', $back) === 1 && preg_match(SameOriginPath::PATTERN, $back) === 1;

        return $store
            ? $back
            : $otherwise;
    }

    /**
     * @param string $message The message, trimmed.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    public function messageReason(string $message): string
    {
        if ($message === '') {
            return 'Write a message before you send it.';
        }

        return mb_strlen($message) <= self::LONGEST
            ? ''
            : 'Keep a message to 1,000 characters.';
    }

    /**
     * @param string $name The name, trimmed.
     *
     * @return string Why it is refused; empty when it is fine or empty.
     */
    public function nameReason(string $name): string
    {
        return $name === '' || preg_match('/^[^\\x00-\\x1F\\x7F]{1,80}$/u', $name) === 1
            ? ''
            : 'Keep your name to one line of 80 characters.';
    }

    /**
     * @param string $email The email, trimmed and lower case.
     *
     * @return string Why it is refused; empty when it is fine or empty.
     */
    public function emailReason(string $email): string
    {
        $written = $email === '' || (strlen($email) <= 254 && preg_match('/^[^\\s@]+@[^\\s@.]+(\\.[^\\s@.]+)+$/', $email) === 1);

        return $written
            ? ''
            : 'Give an email like you@example.com, or leave it empty.';
    }
}
