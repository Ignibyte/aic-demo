<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Queue\Application\PostgresQueue;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatReply;
use App\Chat\Entity\ChatSide;
use App\Chat\Entity\ChatWrite;
use App\Chat\Form\ChatChecks;
use App\Chat\Repository\ChatMessageRepository;
use App\Chat\Repository\ChatRepository;
use App\Inventory\Entity\ListedVehicle;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The chat's writes (#32), for both sides: a start on a car's page, a message, the end with its transcript, and the
 * typing and read marks. A start and a message are checked first, then held to the visitor's limits, and each is
 * written in one transaction: a start inserts the chat and its first message, a message takes the chat's next seq
 * under a lock on its row. The end queues `chat-transcript` in the same transaction when the visitor left an email.
 */
final readonly class ChatSends
{
    /**
     * @param ChatRepository        $chatRepository        The chats.
     * @param ChatMessageRepository $chatMessageRepository The messages.
     * @param ChatVisitors          $chatVisitors          The visitor's envelope.
     * @param ChatLimits            $chatLimits            The start and send limits.
     * @param ChatAssignees         $chatAssignees         Who a start goes to.
     * @param ChatNotices           $chatNotices           The start's notification.
     * @param PostgresQueue         $postgresQueue         Queues the transcript.
     * @param ChatChecks            $chatChecks            Reads and checks the fields.
     */
    public function __construct(
        private ChatRepository $chatRepository,
        private ChatMessageRepository $chatMessageRepository,
        private ChatVisitors $chatVisitors,
        private ChatLimits $chatLimits,
        private ChatAssignees $chatAssignees,
        private ChatNotices $chatNotices,
        private PostgresQueue $postgresQueue,
        private ChatChecks $chatChecks = new ChatChecks(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted start form, with its session.
     * @param ListedVehicle          $listedVehicle The car for sale it was sent from.
     * @param string                 $carPath       The car's page.
     *
     * @throws Throwable When the chats cannot be read or written (the transaction is rolled back first), the
     *                   notification cannot be queued, the request has no session, or no randomness is to be had.
     *
     * @return ChatReply Taken (200, the first message's seq), or refused (422) with the fields as sent.
     */
    public function start(
        ServerRequestInterface $serverRequest,
        ListedVehicle $listedVehicle,
        string $carPath,
    ): ChatReply {
        $name = $this->chatChecks->name($serverRequest);
        $email = $this->chatChecks->email($serverRequest);
        $message = $this->chatChecks->message($serverRequest);
        $chatVisitor = $this->chatVisitors->visitor($serverRequest);
        $refusal = $this->startRefusal($name, $email, $message, $chatVisitor->key);

        if ($refusal instanceof ChatReply) {
            return $refusal;
        }

        $key = $this->chatVisitors->key($chatVisitor);
        $chatId = $this->record($this->values($listedVehicle, $carPath, $name, $email, $key), $message);
        $this->chatVisitors->keep($serverRequest, $chatId, $key);
        $chat = $this->chatRepository->find($chatId);

        if ($chat instanceof Chat) {
            $this->chatNotices->started($chat, $message);
        }

        return new ChatReply(200, '', '', '', '', '', 1);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted send form, with its session.
     * @param Chat                   $chat          The chat it was sent to, open, which the sender may write in.
     * @param ChatSide               $chatSide      Who sends it.
     * @param int                    $staffId       The member who sends it; 0 for the visitor.
     *
     * @throws Throwable When the messages cannot be read or written; the transaction is rolled back first.
     *
     * @return ChatReply Taken (200, its seq), refused (422) with the message as sent, or ended (409) when the chat
     *                   ended before it could be added.
     */
    public function send(ServerRequestInterface $serverRequest, Chat $chat, ChatSide $chatSide, int $staffId): ChatReply
    {
        $message = $this->chatChecks->message($serverRequest);
        $reason = $this->chatChecks->messageReason($message);

        if ($reason === '' && $chatSide === ChatSide::Visitor && !$this->chatLimits->maySend($chat->id)) {
            $reason = ChatChecks::TOO_QUICK;
        }

        if ($reason !== '') {
            return new ChatReply(422, '', '', $message, $reason, 'message', 0);
        }

        $chatWrite = new ChatWrite();
        $chatMessageRepository = $this->chatMessageRepository;
        $chatId = $chat->id;
        $append = static function () use ($chatMessageRepository, $chatId, $chatSide, $staffId, $message, $chatWrite): void {
            $chatMessageRepository->appendLocked($chatId, $chatSide, $staffId, $message, $chatWrite);
        };
        $this->chatRepository->transaction($append);

        return $chatWrite->written() > 0
            ? new ChatReply(200, '', '', '', '', '', $chatWrite->written())
            : new ChatReply(409, '', '', '', ChatReply::ENDED, '', 0);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted send form, with its session.
     * @param Chat                   $chat          The visitor's open chat.
     *
     * @throws Throwable When the messages cannot be read or written; the transaction is rolled back first.
     *
     * @return ChatReply What `send()` answers for the visitor's message.
     */
    public function fromVisitor(ServerRequestInterface $serverRequest, Chat $chat): ChatReply
    {
        return $this->send($serverRequest, $chat, ChatSide::Visitor, 0);
    }

    /**
     * Ends a chat, and queues its transcript in the same transaction when the visitor left an email.
     *
     * @param Chat $chat The chat.
     *
     * @throws Throwable When the chat cannot be written or the job queued; the transaction is rolled back first.
     *
     * @return bool Whether this ended it; false when it had ended already.
     */
    public function end(Chat $chat): bool
    {
        $chatWrite = new ChatWrite();
        $chatRepository = $this->chatRepository;
        $postgresQueue = $this->postgresQueue;
        $chatId = $chat->id;
        $mail = $chat->visitorEmail !== '';
        $chatRepository->transaction(static function () use ($chatRepository, $postgresQueue, $chatId, $mail, $chatWrite): void {
            if (!$chatRepository->end($chatId)) {
                return;
            }

            $chatWrite->hold(1);

            if (!$mail) {
                return;
            }

            $postgresQueue->dispatch('chat-transcript', ['chat' => (string) $chatId], 0);
        });

        return $chatWrite->written() === 1;
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted chat form.
     * @param string                 $otherwise     Where to go when `back` names no store page.
     *
     * @return string Its `back` when it is a storefront page on this site; else `$otherwise`.
     */
    public function back(ServerRequestInterface $serverRequest, string $otherwise): string
    {
        return $this->chatChecks->back($serverRequest, $otherwise);
    }

    /**
     * Marks every message of the chat seen by the visitor.
     *
     * @param Chat $chat The visitor's open chat.
     *
     * @throws Throwable When the chat cannot be written, or a type is malformed.
     */
    public function read(Chat $chat): void
    {
        $this->chatRepository->see($chat->id, ChatSide::Visitor, $chat->lastSeq);
    }

    /**
     * @param ListedVehicle $listedVehicle The car for sale.
     * @param string        $carPath       The car's page.
     * @param string        $name          The visitor's name, checked; empty for none.
     * @param string        $email         Their email, checked; empty for none.
     * @param string        $key           Their key.
     *
     * @throws Throwable When the leads or the users cannot be read.
     *
     * @return array<string, string|int|null> The new chat's car, visitor and member.
     */
    private function values(
        ListedVehicle $listedVehicle,
        string $carPath,
        string $name,
        string $email,
        string $key,
    ): array {
        return [
            'car_path' => $carPath,
            'car_title' => $listedVehicle->title(),
            'staff_id' => self::orNull($this->chatAssignees->assignee($listedVehicle->id)),
            'vehicle_id' => $listedVehicle->id,
            'visitor_email' => $email === '' ? null : $email,
            'visitor_key' => $key,
            'visitor_name' => $name === '' ? 'Visitor' : $name,
        ];
    }

    /**
     * @param array<string, string|int|null> $values  The new chat's car, visitor and member.
     * @param string                         $message The visitor's first message.
     *
     * @throws Throwable When the tables cannot be written; the transaction is rolled back first.
     *
     * @return int The new chat's id.
     */
    private function record(array $values, string $message): int
    {
        $chatWrite = new ChatWrite();
        $chatRepository = $this->chatRepository;
        $chatRepository->transaction(static function () use ($chatRepository, $values, $message, $chatWrite): void {
            $chatRepository->startLocked($values, $message, $chatWrite);
        });

        return $chatWrite->written();
    }

    /**
     * @param string $name    The name, trimmed.
     * @param string $email   The email, trimmed and lower case.
     * @param string $message The message, trimmed.
     * @param string $key     The visitor's key; empty before their first start.
     *
     * @throws Throwable When the chats cannot be read.
     *
     * @return ChatReply|null The start refused (422) with its reason, the field it is about and the fields as sent;
     *                        null when it may go.
     */
    private function startRefusal(string $name, string $email, string $message, string $key): ?ChatReply
    {
        $checks = [
            'email' => $this->chatChecks->emailReason($email),
            'message' => $this->chatChecks->messageReason($message),
            'name' => $this->chatChecks->nameReason($name),
        ];

        foreach ($checks as $field => $reason) {
            if ($reason !== '') {
                return new ChatReply(422, $name, $email, $message, $reason, $field, 0);
            }
        }

        return $this->chatLimits->mayStart($key)
            ? null
            : new ChatReply(422, $name, $email, $message, ChatChecks::TOO_MANY, 'message', 0);
    }

    /**
     * @param int $id A user id; 0 for none.
     *
     * @return int|null It, or null for none.
     */
    private static function orNull(int $id): ?int
    {
        return $id === 0
            ? null
            : $id;
    }
}
