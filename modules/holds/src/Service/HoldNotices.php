<?php

declare(strict_types=1);

namespace App\Holds\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Notifier;
use AIC\Notification\Contracts\Notification;
use App\Holds\Entity\Hold;
use JsonException;

use function str_contains;

/**
 * Tells the member of staff who placed a hold that it has ended (#20), through AIC's Notifier, one `notify` job per
 * channel: kept in the database, and mailed when their login is an address (the gate's own user, `gate`, is not). A
 * hold whose placer is gone tells no one.
 */
final readonly class HoldNotices
{
    /**
     * @param Notifier $notifier Queues each delivery.
     */
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    /**
     * @param Hold $hold A hold just released.
     *
     * @throws JsonException  When a delivery's payload cannot be encoded.
     * @throws QueryException When a delivery cannot be queued.
     */
    public function released(Hold $hold): void
    {
        if ($hold->placedBy === 0) {
            return;
        }

        $customer = $hold->customerName === ''
            ? 'a customer no longer on file'
            : $hold->customerName;
        $body = 'Your hold on the ' . $hold->title . ' (VIN ' . $hold->vin . ') for ' . $customer . ' passed its 48 hours at '
            . $hold->expires . " UTC and has ended. The car is for sale again.\n\nOpen the car: " . $hold->carPath();
        $notification = new Notification(
            (string) $hold->placedBy,
            $hold->placerEmail,
            'The hold on the ' . $hold->title . ' for ' . $customer . ' has ended',
            $body,
            $hold->carPath(),
        );
        $channels = str_contains($hold->placerEmail, '@')
            ? ['database', 'mail']
            : ['database'];
        $this->notifier->send($notification, $channels);
    }
}
