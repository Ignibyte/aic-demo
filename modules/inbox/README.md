# Inbox

The signed-in member's notifications (#19): a bell in the top bar of every staff page with the unread count and the latest five, kept live by a Server-Sent Events stream; `/app/inbox`, every notification newest first and paged; opening one, which marks it read and goes on to what it is about; and "Mark all read". The module requires `staff` (who is signed in, the users' entity type, the staff page frame and its notices). It owns no table: AIC keeps the notifications in `aic_notifications` and reads and writes them through `AIC\Notification\Application\Inbox` (AIC #202), each read and write scoped to one user id.

## Whose inbox

`InboxOwners` reads the signed-in member's email from the staff session and `MemberRepository` finds that user's id, which is the user id a notification carries (`LeadNotices` sends the salesperson's as `(string) $seller->id`). Everything below asks `Inbox` for that user alone, so another member's notification is never listed, counted or changed: `Inbox::one()` and `Inbox::markRead()` answer null and false for a notification that is not the member's, and the opening answers 404 whatever the id.

## The bell

`BellBlock` sits in the `header` region at weight -10, before the account menu, on the pages under `/app`. AIC #203 lets a block take services as a controller does, so it asks for `InboxOwners` and `BellParts`. `BellParts::bell()` builds the theme's `Bell`, keyed `bell`, with the badge keyed `count` and anchored `bell-count` (`BellCount` around `BellBadge`, hidden at 0) and the menu keyed `items` and anchored `bell-items` (`BellItems`, each `BellItem` keyed `notification-{id}`). Inside the bell's template those parts sit at positional paths, which gate:15 keeps out of modules, so the stream targets their anchors, `@bell-count` and `@bell-items` (Tessera 0.3.1, AIC #204), which Tessera finds on the live page when an event arrives. The menu lists the latest five; "All notifications" links `/app/inbox`. The bell names its stream as `data-stream="/app/inbox/events?after={newest id}"`.

## The stream

`GET /app/inbox/events` (`BellEvents`, a Stream) answers AIC's `EventStream` over a `BellSource` that `BellStreams` builds: it starts after the id in `Last-Event-ID` when Tessera resumes, else the page's `after`, else the member's newest notification. Each connection:

- first sends a prepend to `@bell-items` for each notification after that id (the newest five, oldest first), its event id the notification's, then the unread count, an inner patch of `@bell-count` with no id;
- then looks once a second (`usleep`, as AIC's `TickSource` does): a new notification sends its prepend and the count, a count changed elsewhere (a read in another tab) the count alone, and nothing new a keep-alive comment;
- after 20 looks ends with `TesseraEvents::reconnect()`, so Tessera comes back with the last id it saw and nothing is sent twice.

A stream holds a server worker while its connection is open, and a worker learns its client has gone only when a write fails. The keep-alive after every idle look frees the worker within a second of a page left, which matters for gate:23's two-worker compiled site and its one-request `php -S`. The stream does not read the session store: a member signed out elsewhere is sent to sign in by the protected prefix on the next reconnect, a 303 that ends the stream.

The menu shows five: a prepend puts the newest at the top, and the list's CSS hides every item after the fifth rather than the stream removing the oldest. The stream's parts render without Tessera's marks, so they are prepended and replaced, never morphed. The dashboard keeps its own poll every five seconds; the bell's stream runs beside it.

## Pages

| Route | Controller | What it answers |
|---|---|---|
| `GET /app/inbox` | `InboxPage` | The member's notifications, twenty a page newest first (`?page=`; a malformed one reads as the first, and one past the end as the last), each linking to its opening, with "Mark all read" while any is unread. |
| `GET /app/inbox/{id}` | `NotificationPage` | Marks it read and answers 303 to its link when that is a path on this site, else to the inbox; 404 when it is not the member's. For Tessera the 303 is AIC's `PageFullVisit` (#21), so the browser loads the link itself: it may be a file to save, such as a report's CSV export, which a visit cannot swap in. |
| `POST /app/inbox/read-all` | `InboxReadAllPost` | Marks every one of the member's read and answers 303 to the inbox with "Marked every notification read."; 403 without the form token. |
| `GET /app/inbox/events` | `BellEvents` | The bell's stream, above. |

Signed out, each answers 303 to sign in, as every page under `/app` does.

## Forgetting

`bin/aic inbox:forget {login}` (`InboxForget`, #21) deletes every kept notification of the member who signs in with that login, read or not, through AIC's `Inbox::forget()` (AIC #210), and prints how many. It exits 1 when no member signs in so, and 2 with its usage for anything but one argument.

## Checks

gate:23's steps open the inbox, a later page and a malformed one, a notification that is not the member's (404), "Mark all read" without a token (403) and with one, the stream read for one event (`"events": 1`, AIC #202) fresh and resumed from a `Last-Event-ID`, and then the inbox, the stream and a notification signed out (303; that step is what reaches the opening's route for gate:23, which counts only a step below 400). The gate user has no notifications, so both sites answer alike: a step before the first sign-in runs `"console": "inbox:forget gate"` (AIC #210), so each pass starts with that inbox empty, and the reports' export steps forget the notification their export leaves before the next page shows the bell. The browser looks are `tests/browser/inbox-*.spec.ts`; the live look sends a real lead and runs the worker with `ddev exec bin/aic queue:work`, as the leads looks do.
