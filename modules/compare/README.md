# Compare

Up to three cars for sale side by side (#33). A visitor ticks Compare on a car's tile in the list at `/cars` or on its page. A bar at the foot of every store page counts the ticks, and its Compare opens a drawer with the cars side by side, without changing the URL. With JavaScript off the same ticks are a plain GET form, and Compare loads a page of its own.

The module requires `storefront` for the store's frame (`StorePages`), the slugs (`CarSlugs`) and the figures as written (`CarFigures`), which storefront exports for it. It also requires `inventory` for the cars, `photos` for their main photos, `finance` for the payment, `chat` for the dock every store page carries and `settings`, whose profile service the store frame is built from.

## The route

`GET /compare` (`ComparePage`) is open to anyone. It names the cars by their slugs, `?car=<slug>&car=<slug>`, the order they were ticked in. `CompareQuery` reads them from the raw query string, because PHP's own parsing keeps only the last of repeated `car` values. Each slug is kept once, in order, and empty ones are left out.

- Four or more distinct slugs answer 422, before any car is read: "Compare up to three cars at a time."
- Otherwise `ComparisonTables` takes the well-formed slugs' keys and reads the cars for sale with those keys in one statement, through inventory's `ListedVehicleRepository::compared()`. It keeps only a car whose own slug is one asked for, in the order asked. So two cars that share a 6-digit key never mix, and a slug that is malformed, sold, on hold, unknown, or another car's key under another name drops out with no word. With none left the page says "No car to compare."
- The cars' photos are read in one statement (`VehiclePhotos::photosOf()`), and the dealership's profile once, for the frame and the APR.

Each car's column, the theme's `CompareTable` with one `ComparedCar` per car, holds its main photo (or "No photo yet") and its title, linked to its page. Under those come its year, make, model, trim, mileage and price, then its monthly payment: `FinanceCalculators::monthly()`, the figure the calculator on the car's page starts at (10% down rounded down to $100, 60 months, the dealership's APR). The caption says so.

`CarComparisons` is the flow. A Tessera request (the bar's Compare link) gets the store frame with the drawer filled and only the heading in `content`. AIC answers it with the drawer's fragment alone, since the link targets `@compare-drawer`. A plain request (the bar's form with JavaScript off) gets the table in `content` and the drawer empty, so no answer holds the table twice. The 422 follows the same split: the message in the drawer, or as the page's heading. `CompareServices` is the module's factory. It builds the comparison from the tables, the photos and the frame, and the controller takes the chat's dock, so the generated handler stays one short method.

## In the browser

Every store page holds the theme's `CompareDock` in `colophon`, keyed `compare`, after the footer and the chat's dock. `StorePages` places it. Its root has the id `compare` and `data-permanent`, so an enhanced visit keeps it as the same live node. It declares the selection in the document's scope with `data-signals__document`: `compare`, the chosen slugs; `compareFull`, a fourth tick refused; and `compareOpen`, the drawer shown. The selection lives in the browser only, with no session and no cookie, and a full load starts with none.

- A tick is a checkbox named `car`, its value the car's slug, `form="compare-form"`, with `data-bind:compare`, which keeps its value in the list. Its `data-on:change` runs after the bind and keeps the first three (`$compare.slice(0, 3)`), setting `compareFull` when a fourth came. The box goes unticked and the bar says "You can compare up to three cars. Untick one to add another." No request is made.
- The bar, the GET form `compare-form`, is hidden while nothing is ticked (`data-show`). It shows the count (`data-text`), the refusal message, Clear and the Compare link. The link's `href` comes from the signal (`data-attr:href`), and it carries `data-target="@compare-drawer"` and `data-push="off"`, so the visit updates the drawer alone and leaves the address and history as they were. Its click opens the drawer.
- The drawer, the theme's `CompareDrawer`, is keyed `drawer` and anchored `compare-drawer`, and shows while `compareOpen` holds. The dock's root carries `data-on:click__outside`, and the bar and the drawer both sit inside it, so a click anywhere else closes the drawer, and so does its Close button.

With JavaScript off the link, Clear and the message stay hidden, and the form's own Compare button shows. It compares the cars ticked on that page; ticks on other pages are not kept.

A deploy mid-visit needs nothing here: AIC's tracked assets give every store page's stylesheet and scripts `data-track`, so a visit whose answer names another stylesheet makes Tessera load the page whole.
