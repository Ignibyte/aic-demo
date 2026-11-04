<?php

declare(strict_types=1);

namespace App\Chat\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use AIC\Database\Contracts\QueryException;
use App\Chat\Entity\Chat;
use App\Chat\Repository\ChatMessageRepository;
use App\Chat\Repository\ChatRepository;
use App\Chat\Service\ChatNotices;
use InvalidArgumentException;
use JsonException;
use Override;
use UnexpectedValueException;

use function ctype_digit;
use function strlen;

/**
 * The `chat-transcript` job (#32), queued in the same transaction as a chat's end when the visitor left an email:
 * mails the visitor every message, oldest first, through AIC's notifications, whose delivery the same worker runs
 * next. A chat that is gone, still open, or has no email is done with nothing sent.
 */
final readonly class SendChatTranscript implements Job
{
    /**
     * @param ChatRepository        $chatRepository        Reads the chat.
     * @param ChatMessageRepository $chatMessageRepository Reads its messages.
     * @param ChatNotices           $chatNotices           Sends the transcript.
     */
    public function __construct(
        private ChatRepository $chatRepository,
        private ChatMessageRepository $chatMessageRepository,
        private ChatNotices $chatNotices,
    ) {
    }

    /**
     * @param JobInput $jobInput The payload: `chat`, the chat's id.
     *
     * @throws QueryException           When the chat cannot be read or the delivery cannot be queued; the queue retries.
     * @throws InvalidArgumentException When a type is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     * @throws JsonException            When the delivery's payload cannot be encoded.
     *
     * @return JobResponse Done, whether or not a mail went; failed for a payload that names no chat.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $named = $jobInput->value('chat', '');

        if ($named === '' || strlen($named) > 18 || !ctype_digit($named)) {
            return new JobFailed('No chat is named by this chat-transcript payload: "' . $named . '".');
        }

        $chat = $this->chatRepository->find((int) $named);

        if ($chat instanceof Chat && !$chat->open && $chat->visitorEmail !== '') {
            $this->chatNotices->transcript($chat, $this->chatMessageRepository->thread($chat->id, $chat->visitorName));
        }

        return new JobDone();
    }
}
