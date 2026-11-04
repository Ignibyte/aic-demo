# Storefront

Ridgeway Motors' public side, for customers (#17): the cars for sale at `/cars`, each car's page and photos below it, and the same cars as JSON at `/api/vehicles` for listing partners. Nobody signs in to it, and nothing under it is protected. The module requires `inventory`, whose exported `ListedVehicleRepository` gives the cars for sale; `photos`, whose exported `VehiclePhotos` gives their photos; `staff`, for its `StaffRequests`, which reads a query parameter or a route attribute; `settings`, for the dealership's profile (#22); and `finance`, for the car's payment calculator (#26).

## What the public sees

Only vehicles whose status is `in_stock`: `ListedVehicleRepository` reads no other row, so a sold car or one on hold is never listed, never found by its slug and never served a photo. Each row reaches this module as inventory's `ListedVehicle`, which has no VIN and no cost, so no public page and no feed answer can show them; nothing here reads a customer.

## Slugs

A car's page is `/cars/{slug}`, the slug `{year}-{make}-{model}-{key}`: lower case, each run of characters other than `a-z` and `0-9` one hyphen, none at either end, and the key the first 6 hex digits of the SHA-256 of the VIN, which inventory works out (`2023-volkswagen-tiguan-9f381a`). The key is the same on every database and on both of gate:23's passes, where a row id is not, and it does not show the VIN. `CarSlugs` writes a slug and reads one back into its year and key; `CarFinder` asks inventory for the car in stock of that year with that key and answers it only when its own slug is the one asked for, so `/cars/2023-ford-tiguan-9f381a` answers 404.

## The layout

Every page goes through `StorePages`, which builds the theme's `store` layout and passes `ThemeLayoutName::Store` to `PageDocuments::page()`: the theme's `StoreHeader` (the dealership's name, linked to `/cars`) in `masthead`, the page's parts in `content` and the `StoreFooter` (the name, then the dealership's address, phone and hours, those given) in `colophon`, in one column, with no drawer. The name, address, phone and hours are the dealership's profile, which `settings`' exported `DealershipProfiles` reads on each page (#22), so an admin's save shows on the next page; until the first save they are the defaults, Ridgeway Motors open Monday to Saturday, 09:00 to 18:00. The staff blocks place themselves in `sidebar` (the navigation), `header` (the account menu or the sign-in link) and `footer` (the site footer), which the `store` layout does not have, and AIC skips a block for a region its layout does not place, so none of them shows on a public page, whoever is signed in. Only `StorePages` builds the public header, so no staff page shows it. The layout's Tessera identity is `lotline-store`: a visit between a staff page and a public one loads a whole document.

`StorePages::missing()` is the 404 for a car that is sold, on hold or unknown, in the same layout, with a link back to the list.

## The list

`CarListPage` answers `GET /cars` with the parts `CarListing` builds: the heading, the theme's `FilterBar`, then the results, keyed `results` and anchored `car-results`. The results are an AIC `SourcedComponent`: `CarResults` holds the repository and the parsed query and counts, pages and reads the cars in `build()`, which the render pass calls only when it renders them. `CarTiles` lays them out as the theme's `TableResults`, holding a `CarGrid` (a card per car, 12 a page: its main photo or "No photo yet", its year, make and model, its mileage and its price, linked to its page) and, past one page, its `Pagination`. The filter form and each page link target `@car-results`, so with JavaScript Tessera answers them with the results alone and pushes the URL; with JavaScript off they are plain GETs.

| Parameter | What it does | Default |
|---|---|---|
| `make` | One make for sale | every make |
| `year` | One model year for sale | every year |
| `min_price`, `max_price` | List price bounds, whole dollars, 1 to 7 digits | no bound |
| `sort` | `price` (low to high) or `-price` (high to low) | newest on the lot first |
| `page` | The page, 1 to 9999 | 1 |

`CarQueries` checks each parameter on its own: one that is missing, unknown or malformed falls back to its default and leaves the others alone, and a page past the last shows the first. `CarLinks` writes each page link with only the parameters that differ from their defaults. Each order ends with the VIN, inside inventory, so a page's cars are the same on every request.

## A car

`CarPage` answers `GET /cars/{slug}` with the theme's `CarProfile`, which `CarProfiles` builds: the car's photos in the theme's `PhotoGallery` with no controls, in the order they were added, the first the main one, each `alt` naming the car and the photo's number; its title, trim and price; its year, make, model, trim, body style, mileage and colour, as `DetailCard`'s `Fact`s; and finance's payment calculator at the car's price and the dealership's default APR (#26), under the price in the same card. `CarPages` reads the profile once through `StorePages::profile()`, takes its APR for the calculator, and hands the profile back to `StorePages::page()` for the header and footer, so the page still reads it once. The calculator works in the browser alone; finance's README says how. `CarPhotoStream` serves a photo at `GET /cars/{slug}/photos/{position}` through photos' `VehiclePhotos::download()`, so AIC answers its ETag, conditions and ranges; a car not for sale, or an empty position, answers 404. Each image's source is `VehiclePhoto::source()`, the photo's URL in AIC's public files (#23), `/files/photos/{sha256}.{extension}`, which the framework serves before any session; the list's cards and the feed name the same URLs. A public file is public: once the car is sold its page and `CarPhotoStream` answer 404, and its photo stays at its `/files/` URL. `StoreServices` takes the public pages' frame (`StorePages`) and the public files, which keeps its generated part one short method.

Below the car sit two lead forms (#18), "Ask about this car" and "Book a test drive", side by side in the theme's `PanelGrid`. `CarPages` builds the car's page for `CarPage` and for the two posts, `POST /cars/{slug}/inquiry` (`CarInquiryPost`) and `POST /cars/{slug}/test-drive` (`CarTestDrivePost`). The panels, the checks, the recording and the notifications are the `leads` module's: storefront requires it and calls its exported `LeadDesk`. A post finds its car as the page does, so a car not for sale answers 404; otherwise the page answers 200 with the sent form's panel thanked, or 422 with it refused, and Tessera swaps that panel alone.

## The feed

`VehicleFeedApi` answers `GET /api/vehicles` with `{"vehicles": [...]}`: every car for sale in the list's first order (newest on the lot first, then by VIN). `?make=` keeps one make, matched without regard to case; a make not for sale, or a list of makes (`make[]=…`), answers an empty list rather than every car. `VehicleFeedItemApi` answers `GET /api/vehicles/{slug}` with one car's object, or `{"error": "No vehicle for sale has that slug."}` with 404. Each car, a `FeedVehicle`, is:

| Field | What it is |
|---|---|
| `slug`, `path` | Its slug and its page's path |
| `year`, `make`, `model`, `trim`, `body_style`, `mileage`, `exterior_colour` | What it is |
| `price` | Its list price as a decimal string, `"28995.00"` |
| `photos` | Each photo's path, the main one first |

Paths are relative to the site, so both of gate:23's sites answer the same bytes. The list's page and the feed read their cars' photos in one statement, `CarFinder::photosOf()` over photos' `VehiclePhotos::photosOf()` (#25: the profiler showed `/cars` reading them once a car, 12 statements a page).

## The chat's dock

Every store page carries the chat's dock (#32) after the footer in `colophon`, built by chat's exported `ChatDesk` (`dock()` for a car's page and `/chat`, `pageDock()` for any other store page) and handed to `StorePages`: the visitor's open chat on every page, which a Tessera visit keeps as the same node; else, on a car's page, the start form, posting to `POST /cars/{slug}/chat` (`CarChatPost`), which `CarPages::chat()` answers with the car's page and the dock open (200) or the start refused (422). `StorePages::page()`, `missing()` and `framed()` take the dock as a `?KeyedComponent`; `CarListPage` and `CarPages` build it. The visitor's framed chat pages sit here too, in `Form/ChatAnswers.php`, since `src/Service/` is at its cap: `GET /chat` (`ChatPage`), the chat on a page of its own; `POST /chat/messages` (`ChatMessagePost`), 303 back to the store page it came from, or the send form's fragment with the `chatSent` patch for Tessera, 422 refused, 409 with no chat open; and `POST /chat/end` (`ChatEndPost`), 303 back. The chat module's README says the rest.

## The comparison's dock

Every store page carries the comparison's dock (#33) last in `colophon`, after the footer and the chat's dock: the theme's `CompareDock`, keyed `compare`, which `StorePages::framed()` places, its drawer keyed `drawer` and anchored `compare-drawer`. The drawer is empty unless the page hands one in, which only the compare module's `/compare` does. Each car's tile in the list and each car's page carry a Compare tick, its value the car's slug, which `CarTiles` and `CarProfiles` hand the theme's `CarTile` and `CarProfile`. The module exports `StorePages`, `CarSlugs` and `CarFigures`, so `/compare` is framed, linked and written as the store's other pages are. The compare module's README says the rest.
