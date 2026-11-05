---
number: 46
title: "The chat's sent patch names @chat-send again"
type: chore
status: open
created: 2026-11-05T10:00:00Z
updated: 2026-11-05T10:00:00Z
---

`modules/chat/README.md` says the send answer scopes its `chatSent` patch to the region
(`colophon` for the visitor, `content` for the member) because AIC 98f7ea4 wrote an `@chat-send`
scope as it was, and that "Lotline #38 moves back to `@chat-send` once it lands". AIC #260, which
resolves an `@NAME` scope to the path the request named, has landed (Lotline is on 9aa35e5), but
#38 kept the change out of its run.

Scope the patch to `@chat-send` on both sides, drop the region names from the answer, and
rewrite the README paragraph to say what the code does. The chat's kept looks
(`chat-send`, `chat-resume`, `chat-reply-focus`) must still pass under gate:23: a sent message
clears the box on both sides, with and without JavaScript.
