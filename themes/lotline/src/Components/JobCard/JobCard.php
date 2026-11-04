<?php

declare(strict_types=1);

namespace App\Theme\Components\JobCard;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\GuardFields;
use App\Theme\Components\DetailCard\Fact;
use App\Theme\Components\StatusBadge\StatusBadge;

/**
 * A failed job (#35): its name and badge, its facts, the reason it failed set monospace, and two buttons, Retry now and
 * Discard, each a POST form of its own that writes the visitor's guard fields before its button. Its ids grow from
 * `id`: `<id>-title`, `<id>-reason`, `<id>-retry` and `<id>-discard`.
 */
final readonly class JobCard implements Component
{
    /**
     * @param string      $id          The card's id, such as `failed-12`.
     * @param string      $title       The job's name.
     * @param StatusBadge $statusBadge Its status.
     * @param list<Fact>  $facts       Its facts, in order.
     * @param string      $reason      Why it failed, as the worker recorded it.
     * @param string      $retry       Where Retry now posts.
     * @param string      $discard     Where Discard posts.
     * @param GuardFields $guardFields The visitor's form token and honeypot, written in each form.
     */
    public function __construct(
        public string $id,
        public string $title,
        public StatusBadge $statusBadge,
        public array $facts,
        public string $reason,
        public string $retry,
        public string $discard,
        public GuardFields $guardFields,
    ) {
    }
}
