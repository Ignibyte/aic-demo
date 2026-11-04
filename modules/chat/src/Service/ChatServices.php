<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Queue\Application\PostgresQueue;
use App\Chat\Form\ChatForms;
use App\Chat\Repository\ChatMessageRepository;
use App\Chat\Repository\ChatRepository;
use App\Chat\Repository\ChatStaffRepository;
use App\Leads\Repository\VehicleLeadRepository;

/**
 * The chat module's factory (#32): the visitor's desk, with its parts and writes, built here, so the storefront's
 * generated wiring, which takes the desk, does not spell the chat's tree again beside this module's own (gate:14). No
 * chat handler takes the desk, so only the storefront builds this factory.
 */
final readonly class ChatServices
{
    /**
     * @param EntityStore   $entityStore   The tables.
     * @param EnvelopeCodec $envelopeCodec Seals the visitor's envelope.
     * @param PostgresQueue $postgresQueue Queues the transcript job.
     * @param ChatNotices   $chatNotices   The start's notification.
     * @param ChatForms     $chatForms     The chat's forms.
     */
    public function __construct(
        private EntityStore $entityStore,
        private EnvelopeCodec $envelopeCodec,
        private PostgresQueue $postgresQueue,
        private ChatNotices $chatNotices,
        private ChatForms $chatForms,
    ) {
    }

    /**
     * @return ChatDesk The visitor's side of the chat.
     */
    public function chatDesk(): ChatDesk
    {
        return new ChatDesk(
            new ChatVisitors($this->envelopeCodec),
            new ChatRepository($this->entityStore),
            $this->chatParts(),
            $this->chatSends(),
            $this->chatForms,
        );
    }

    /**
     * @return ChatParts The chat as components.
     */
    private function chatParts(): ChatParts
    {
        return new ChatParts(new ChatMessageRepository($this->entityStore), $this->chatForms);
    }

    /**
     * @return ChatSends The chat's writes.
     */
    private function chatSends(): ChatSends
    {
        $chatRepository = new ChatRepository($this->entityStore);
        $chatAssignees = new ChatAssignees(new VehicleLeadRepository($this->entityStore), new ChatStaffRepository($this->entityStore));

        return new ChatSends(
            $chatRepository,
            new ChatMessageRepository($this->entityStore),
            new ChatVisitors($this->envelopeCodec),
            new ChatLimits($chatRepository),
            $chatAssignees,
            $this->chatNotices,
            $this->postgresQueue,
        );
    }
}
