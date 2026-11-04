<?php

declare(strict_types=1);

/*
 * The chat module's manifest (#32): live chat between a storefront visitor and
 * the salesperson on the car's leads, or the sales manager. The visitor has
 * no account; their session's signed envelope names their chat. It exports
 * ChatDesk, which builds the dock every store page places and records what a
 * visitor sends, as leads exports LeadDesk; the storefront holds the
 * visitor's framed pages. This module holds the visitor's stream and the two
 * public posts that answer 204 (typing and read), the staff's chat inbox,
 * conversation, reply, typing, close and stream under /app, the transcript
 * job, and the chats' and messages' tables. The build reads it; nothing
 * reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Chat\Controller\ChatClosePost;
use App\Chat\Controller\ChatEvents;
use App\Chat\Controller\ChatListPage;
use App\Chat\Controller\ChatReadPost;
use App\Chat\Controller\ChatReplyPost;
use App\Chat\Controller\ChatStaffTypingPost;
use App\Chat\Controller\ChatTypingPost;
use App\Chat\Controller\ChatViewPage;
use App\Chat\Controller\StaffChatEvents;
use App\Chat\Entity\ChatReply;
use App\Chat\Job\SendChatTranscript;
use App\Chat\Migration\CreateChatMessagesTable;
use App\Chat\Migration\CreateChatsTable;
use App\Chat\Service\ChatDesk;
use App\Chat\Service\ChatServices;

return (new ModuleManifest('chat'))
    ->requires('staff')
    ->requires('inventory')
    ->requires('leads')
    ->requires('settings')
    ->route(RouteMethod::Get, '/chat/events', ChatEvents::class)
    ->route(RouteMethod::Post, '/chat/typing', ChatTypingPost::class)
    ->route(RouteMethod::Post, '/chat/read', ChatReadPost::class)
    ->route(RouteMethod::Get, '/app/chats/{id:int}', ChatViewPage::class)
    ->route(RouteMethod::Post, '/app/chats/{id:int}/messages', ChatReplyPost::class)
    ->route(RouteMethod::Post, '/app/chats/{id:int}/typing', ChatStaffTypingPost::class)
    ->route(RouteMethod::Post, '/app/chats/{id:int}/close', ChatClosePost::class)
    ->route(RouteMethod::Get, '/app/chats/{id:int}/events', StaffChatEvents::class)
    ->route(RouteMethod::Get, '/app/chats', ChatListPage::class)
    ->factory(ChatServices::class)
    ->job('chat-transcript', SendChatTranscript::class)
    ->migration(CreateChatsTable::class)
    ->migration(CreateChatMessagesTable::class)
    ->export(ChatDesk::class)
    ->export(ChatReply::class);
