# Leads

What a visitor asks about a car on its public page, and how the salesperson hears of it (#18). A lead is a question ("Ask about this car") or a test drive request ("Book a test drive"). The module requires `staff` (who is signed in, the users and their roles, the staff page frame), `inventory` (the vehicles' entity type, `ListedVehicle` and the database's today) and `customers` (the customers' entity type, to link a lead by email). `storefront` requires it: the forms sit on a car's page, which only storefront can find by its slug, so storefront hosts the two posts and hands them to this module's exported `LeadDesk`.

## Data

`CreateLeadsTable` makes `leads`: an identity `id`; `vehicle_id`, whose foreign key cascades, so a car's leads go with it as its photos do (a car is deleted only before it is sold); `kind` (`inquiry` or `test_drive`); `name`, `email` (lower case), `phone` (empty when a question gives none) and `message`; `drive_on` and `drive_at` (`HH:MM`), null for a question; `salesperson_id` and `customer_id`, each null for none and set null when that user or customer is removed; `status` (`open` or `closed`); and `created_at`. It is indexed on `status` and on `vehicle_id`.

`Entity/LeadTable` is its entity type, referring to inventory's, staff's and customers' exported types. `LeadRepository` records, finds, lists, reassigns and closes leads, and reads the salespeople, all through AIC's `EntityStore`.

## Assignment

The salespeople are the users whose role is `salesperson`; staff keeps nothing else about them, so these are the active ones. `LeadRepository::record()` runs one `EntityStore::transaction()`: it locks the salespeople's rows ordered by id (`forUpdate`), reads the newest lead's salesperson, takes the next one by id (the first when there is none or past the last), and inserts the lead. A second lead at the same moment waits for the lock and sees the first, so leads go to each in turn. With no salesperson the lead is unassigned. Since the turn follows the newest lead, removing leads brings it back: gate:23's two passes, each deleting its car and the car's leads, assign alike.

A lead whose email is a customer's (both lower case) is linked to that customer.

## The public forms

`LeadDesk::panels()` gives a car's page its two panels, the theme's `Panel` keyed `inquiry` and `test-drive` and anchored `car-inquiry` and `car-test-drive`, each holding AIC's `Form` (with the form guard's token and honeypot, so a visitor who never signed in gets a session for the token). Each form ends with the theme's `SubmitButton`, whose `data-target` names its own panel's anchor: Tessera reads a submit's target from the button first, so it swaps that panel alone. `LeadDesk::send()` checks the post (`LeadChecks`, with `LeadHours` for a test drive), and answers both panels with 422 and the errors beside their fields, or records the lead, queues its job and answers 200 with a thank-you (the theme's `Notice`) where the form was. Tessera's write id makes a double click record one lead.

A question needs a name, an email and the question; its phone is optional. A test drive needs a phone, a day after the database's today that is not a Sunday, and a time from 09:00 to 17:00: Ridgeway Motors opens Monday to Saturday, 09:00 to 18:00, and a drive starts at least an hour before closing. Days and times compare as text with `strcmp()`, and the weekday is worked out in integers, so no time zone enters it.

## Notifications

Recording a lead queues one `lead-notices` job with the lead's id; the request sends no mail. `SendLeadNotices` reads the lead (one that is gone, with its car, is done with nothing sent) and `LeadNotices` sends through AIC's `Notifier`, which queues one `notify` job per channel: the salesperson's notification (their user id, the visitor's details, `/app/leads/{id}`) to `database` (`aic_notifications`) and `mail`, and the visitor's confirmation to `mail` alone. One `bin/aic queue:work` runs them all, since the worker takes jobs until none is due. In DDEV the mail lands in Mailpit; on this box only the web container reaches Mailpit's SMTP, so run the worker with `ddev exec bin/aic queue:work`.

## Staff pages

| Route | Controller | What it answers |
|---|---|---|
| `GET /app/leads` | `LeadListPage` | The open leads, newest first: at most 50 with how many match, filtered by `kind` and `salesperson` (an email); an unknown value of either shows every lead. The day a lead came in shows without its time. |
| `GET /app/leads/{id}` | `LeadPage` | The lead and its car (linked to its inventory page), its salesperson and its customer; the reassign and close forms while it is open, for its salesperson or a manager. 404 for no lead. |
| `POST /app/leads/{id}/assign` | `LeadAssignPost` | Gives the lead to the salesperson the form names by email: 303 to the lead with a notice; 422 for one who is not a salesperson. |
| `POST /app/leads/{id}/close` | `LeadClosePost` | Closes the lead: 303 to the list with a notice. |

Only the lead's salesperson and a manager (an admin or a sales manager) may reassign or close it (`LeadAccess`): anyone else gets 403 and sees no forms. A closed lead answers 409; an unknown one 404. The sidebar's "Leads" link opens the list.

## Checks

gate:23's steps post a question and a test drive about the gate's own car, refused (a bad field, no token, the honeypot, a Sunday evening) and sent; reach the lead's page through the list with `"capture": "/app/leads/"` (AIC #200); reassign and close it, refused and done. The browser looks are `tests/browser/leads-*.spec.ts`; the notification look runs the worker through `ddev exec` and reads Mailpit at the address `ddev describe -j` gives.
