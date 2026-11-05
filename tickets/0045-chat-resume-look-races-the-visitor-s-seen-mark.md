---
number: 45
title: "The chat-resume look races the visitor's seen mark"
type: bug
status: closed
created: 2026-11-05T02:40:00Z
updated: 2026-11-05T11:22:27Z
phase: "docs"
---

`tests/browser/chat-resume.spec.ts` failed once in gate:23 on 2026-11-05 with no difference
from PHP, and passed on the next run:

```
-   "chatUnread": 1,
+   "chatUnread": 0,
  > 35 |     expect(JSON.parse(fresh[1].signals)).toEqual({ chatTyping: false, chatUnread: 1 });
```

The look starts a chat in the page, inserts a staff reply ("Two") into the database, then reads
the stream and expects one unread message. The page's dock is still open with its own stream,
so when that stream delivers "Two" before the look reads, the visitor counts as having seen it
and the unread count is 0. The look depends on which of the two readers gets there first.

Make the look deterministic: take the page's own reader out of the race before the reply is
inserted (for example, leave the car page or close the dock first), or assert the count the
seen mark gives. The app's unread rule is not in question. Keep the look, and run it several
times in a row under gate:23 to show it holds.
