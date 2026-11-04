# Chat

Live chat between a storefront visitor and the salesperson on the car's leads, or the sales manager (#32). A visitor opens a chat from a car's page; the member it goes to sees it in their bell and in `/app/chats`; both sides see each message land as it is sent, the other side typing, and an unread count; the dock that holds the chat stays open while the visitor moves around the storefront. Without JavaScript every part is a form that posts and reloads. The module requires `staff` (the session's member, the users' type, the staff page frame, its seals and notices), `inventory` (the car for sale and the vehicles' type), `leads` (who the car's newest lead went to) and `settings` (the dealership's name on the transcript). It exports `ChatDesk` and `ChatReply` to `storefront`, which places the dock and holds the visitor's framed pages.

## Where the routes sit

The visitor's pages need the store's frame, which is storefront's, and every store page needs the dock, which is this module's, so storefront requires chat and the shape follows leads' `LeadDesk`. `ChatDesk` builds the dock and does the visitor's writes; storefront holds `POST /cars/{slug}/chat`, `GET /chat`, `POST /chat/messages` and `POST /chat/end`, each answered in the store's layout. This module holds everything that needs no store frame:

| Route | Controller | What it answers |
|---|---|---|
| `GET /chat/events` | `ChatEvents` | The visitor's stream, below. |
| `POST /chat/typing` | `ChatTypingPost` | 204; marks the visitor typing when a chat is open. |
| `POST /chat/read` | `ChatReadPost` | 204; marks the visitor's chat read when one is open. |
| `GET /app/chats` | `ChatListPage` | The chats the member sees, open first, then by their newest message; 204 for its poll while nothing changed. |
| `GET /app/chats/{id}` | `ChatViewPage` | The conversation, marked seen; 404 for no chat, 403 for one that went to another salesperson. |
| `POST /app/chats/{id}/messages` | `ChatReplyPost` | 303 to the chat, or its fragments with the `chatSent` patch for Tessera; 422, 409, 404 or 403. |
| `POST /app/chats/{id}/typing` | `ChatStaffTypingPost` | 204; marks the member typing. |
| `POST /app/chats/{id}/close` | `ChatClosePost` | 303 to the list with "Closed the chat with {name}."; 409, 404 or 403. |
| `GET /app/chats/{id}/events` | `StaffChatEvents` | The member's stream. |

Signed out, every route under `/app` answers 303 to sign in.

## The tables

`chats` (`CreateChatsTable`) holds one row a chat: the visitor's key, the car (its id, title and path; the chat goes with its car), the member it went to (null for none, or once removed), the visitor's name and email, `open` or `ended`, `last_seq`, the two seen marks, the two typing times, `created_at` and `ended_at`. `chat_messages` (`CreateChatMessagesTable`) holds the messages, numbered within their chat by `seq` from 1, unique with `chat_id`. AIC's tables take no defaults, so the insert writes the seqs and marks as 0. The same migration makes three views the chats are read through for what the database's clock decides: `chat_states` (whether each side typed in the last five seconds), `chat_recent` (the visitor's messages in the last minute, and whether the chat started in the last hour) and `chat_list` (the staff's list in one read). The typing times and `ended_at` are written through `ChatTable::typing()` and `ending()`, entity types over `chats` that mark that column `UpdatedAt`, so AIC's update sets it to `now()` and no text is bound to a timestamp column, which the compiled site refuses.

## The visitor

Visitors have no account. `ChatVisitors` keeps `chat.visitor` in the session, an `EnvelopeCodec` seal of the open chat's id and the visitor's key, 32 hex digits made on the session's first start and kept across chats. A chat is the visitor's only while its row carries that key and it is open; an altered envelope reads as none.

## The dock

`ChatDesk::dock()` gives `StorePages` what goes after the footer in `colophon`, keyed and anchored `chat`: the open chat's `ChatDock`, a details element with the id `chat-dock` and `data-permanent`, carrying the stream and the `chatUnread` and `chatTyping` signals, on every store page; else, on a car's page, `ChatStart`, a details element with the id `chat-start` and the start form, not permanent, so the start's answer brings the open dock in its place; else nothing. Opening the dock, and a message landing while it is open (`tessera:patched`), post the read. Inside: the log (`ChatLog`, keyed `messages` and anchored `chat-messages`, a `role="log"` box holding the `ChatThread`), the typing line, the send form and "End chat".

## Sending

`ChatSendForm` is the send form for both sides, keyed `send` and anchored `chat-send`. Its box carries `data-ignore-morph`, so neither an answer nor a stream patch touches what is typed; Enter sends through `requestSubmit()` and Shift+Enter breaks the line; `data-on:input__throttle.2s` posts the side's typing route; and `data-effect` clears the box when the `chatSent` signal changes, which an enhanced send's answer patches with the seq it took. The form declares `chatSent` in its region's scope (`data-signals__region`), and the answer scopes the patch to the region, `colophon` for the visitor and `content` for the member: Tessera 0.4.4 reads a fragment answer's `data-scope` only as a path, and AIC 98f7ea4 wrote an `@chat-send` scope as it was, so the patch failed. AIC #260 resolves an `@NAME` scope to the path the request named; Lotline #38 moves back to `@chat-send` once it lands. A refused message stays in the box with its reason.

`ChatSends` does the writes. `ChatChecks` checks a message (1 to 1,000 characters once trimmed, line breaks kept), a name (at most 80, one line; "Visitor" when none) and an email (or none). `ChatLimits` holds the module's own rate limits, since AIC has none: three chats per visitor key an hour, ten visitor messages per chat a minute, both counted from stored rows through `chat_recent`; a refusal is a 422. A message takes the chat's next seq under a lock on the chat's row (`ChatMessageRepository::appendLocked()`), marks it seen by its sender and clears the sender's typing. A start inserts the chat and its first message in one transaction and tells the member through AIC's `Notifier`, database channel alone: "New chat about the {car}", linking to the chat.

A chat goes to the salesperson on the car's newest lead that has one, while that member is active (`VehicleLeadRepository::sellerOf()`); else to the active sales manager with the lowest id; else to no one, and the managers take it from the list.

## The streams

`ChatSource` serves both sides, built by `ChatStreams`. Event ids are the chat's seqs, the same on both of gate:23's sites. A fresh connection sends the whole thread as the log's `inner` markup, its id the newest seq, then the signals; one with `Last-Event-ID` sends an `append` of each later message. It then looks once a second, 20 times, and ends with `reconnect()`: a new message is an `append` of `@chat-messages`, a change of the signals a `tessera-patch-signals` with no id (`@chat` for the visitor, `@chat-conversation` for the member), nothing a keep-alive. A chat that has ended sends "This chat has ended." as the inner markup of `@chat-send` and closes. A request with no chat to serve gets one closing event, since a Stream cannot answer 204. The member's connection marks what it sends seen.

The visitor's stream answers `Tessera-Stream-Pause: hidden` through `EventStream::pausedWhileHidden()` (AIC #259), so Tessera closes it while the tab is hidden and reconnects with `Last-Event-ID` when it is visible again; nothing sent meanwhile is lost. The member's stream stays open while hidden, so a conversation in a background tab still marks what arrives seen.

## Unread and typing

A side's unread count is the other side's messages after its seen mark. The visitor reads by opening the dock or `/chat`; the member by opening the conversation and through their stream. The staff list counts the visitor's messages after `staff_seen`, and refreshes itself by `data-poll__5s` with a version sealed by `StaffSeals` (each row's id, newest seq, seen mark and status), answered 204 while it holds. A side shows as typing while its typing time is under five seconds old.

## Ending

The visitor's "End chat" and the member's "Close chat" end the chat through `ChatSends::end()`, which queues `chat-transcript` in the same transaction when the visitor left an email. `SendChatTranscript` reads the chat and, once it has ended with an email, mails every message, oldest first, as `{time} {who}: {text}`, signed with the dealership's name.
