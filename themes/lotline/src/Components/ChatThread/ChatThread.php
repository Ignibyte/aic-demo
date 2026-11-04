<?php

declare(strict_types=1);

namespace App\Theme\Components\ChatThread;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A chat's messages, oldest first (#32), from chat-thread.phtml: each a `ChatLine` keyed by its seq. It sits inside the
 * `ChatLog`, and a stream's fresh connection sends it whole as the log's inner markup.
 */
final readonly class ChatThread implements Component
{
    /**
     * @param list<KeyedComponent> $lines Each message's `ChatLine`, keyed `line-<seq>`, oldest first.
     */
    public function __construct(
        public array $lines,
    ) {
    }
}
