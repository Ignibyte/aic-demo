<?php

declare(strict_types=1);

namespace App\Theme\Components\ChatStart;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\KeyedComponent;

/**
 * The start of a chat on a car's page (#32), from chat-start.phtml: a closed `<details id="chat-start">` at the bottom
 * left, keyed and anchored `chat` as the open dock is, but not permanent, so the answer to a start brings the open
 * dock in its place. Its form posts the visitor's name, email and first message to the car's chat route; refused, it
 * opens with the reason and the fields as sent.
 */
final readonly class ChatStart implements Component
{
    /**
     * @param string               $action      Where the start posts, `/cars/<slug>/chat`.
     * @param list<KeyedComponent> $fields      The name, email and message fields, each a `FormField`.
     * @param string               $reason      Why the last start was refused; empty for none.
     * @param GuardFields          $guardFields The form token and the honeypot, which the form renders first.
     */
    public function __construct(
        public string $action,
        public array $fields,
        public string $reason,
        public GuardFields $guardFields,
    ) {
    }
}
