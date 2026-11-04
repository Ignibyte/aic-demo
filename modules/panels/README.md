# Panels

The vehicle page's heavy parts, which load after the page (#31): its price history, the vehicles like it, and its leads and holds. The module requires `staff` for the vehicle pages' frame, `inventory` for the vehicles and their price changes, and `leads` and `holds` for theirs, each read through the repository its own module exports. Every route sits under staff's protected `/app` prefix.

## How a panel reaches the page

Each panel is a plugin of inventory's exported type `VehiclePanel`: `PriceHistoryPanel`, `SimilarVehiclesPanel` and `VehicleActivityPanel`, registered in the manifest and built by the generated provider with no arguments. `VehicleCards`, inventory's vehicle page, asks each for its part after the sale, so inventory never names this module.

`PanelParts` builds every part the same way, under the panel's key and anchor, around the theme's `LazyPanel`:

| Request | The part | Reads |
|---|---|---|
| A Tessera visit (a link, a prefetch, Back and Forward, a redirect followed) | A skeleton: `<key>-<VIN>-loading`, `aria-busy`, `data-lazy` naming the panel's route, no props | nothing |
| Anything else (a plain request, JavaScript off, a submit answered in place) | The panel inline: `<key>-<VIN>`, its version sealed in its props, `data-poll__30s` naming its route | its version, then its sources as they render |

The two ids differ on purpose. Idiomorph pairs an old element with a new one only when their ids agree, and Tessera loads a lazy element once per node, so a skeleton always arrives as a new element and loads, and an arrived panel is never morphed back into a skeleton that would never load. A visit to another vehicle gets new elements for the same reason.

What a panel shows comes from its sources (`sources()`), each an AIC `ComponentSource` the render pass builds only when it renders it: `PriceHistorySource`, `SimilarVehiclesSource`, and for the leads and holds `VehicleActivitySource` (the leads) and `VehicleHoldsSource` (the holds). A Tessera answer for one panel therefore reads that panel's data and no other.

## The routes

| Route | Class | Panel |
|---|---|---|
| `GET /app/inventory/{vin}/price-history` | `PriceHistoryPage` | Price history, anchored `vehicle-price-history` |
| `GET /app/inventory/{vin}/similar` | `SimilarVehiclesPage` | Similar vehicles, anchored `vehicle-similar` |
| `GET /app/inventory/{vin}/activity` | `VehicleActivityPage` | Leads and holds, anchored `vehicle-activity` |

`PanelPages` is their one flow, in `DashboardPoll`'s order. The route's VIN is checked against the VIN alphabet before any query, and a VIN that is malformed or on no vehicle answers the vehicle pages' 404. The panel's props are opened by its anchor, and props altered on the way answer 400. Then the panel's version is read; a refresh of the panel alone whose props carry that version answers 204 (`PageUnchanged`) with nothing built. Otherwise the answer is the vehicle pages' frame (`StaffPanels` with the id `vehicle`) holding the panel alone, at the path the vehicle page gives it: AIC answers a Tessera request with that panel's fragment and a plain one with the page whole.

## Versions

| Panel | Version |
|---|---|
| Price history | the vehicle's price changes' count and newest id (`VehiclePriceRepository::version()`) |
| Similar vehicles | the count and newest `updated_at` of the vehicles like it, and its own list price (`VehicleRepository::similarVersion()`) |
| Leads and holds | its leads' count and newest id with their moves' count and newest id (`VehicleLeadRepository::version()`), then its holds' count, newest `updated_at` and how many active ones passed their time (`HoldRepository::version()`) |

A version sits in the props alone, which gate:23 masks, so its ids and timestamps never reach a compared body.

## What each panel shows

- **Price history**: `PriceChart` places the list price over time on a 320 by 120 drawing for the theme's `PriceLine`: the day the vehicle came at the first change's old price (its price now with no change), each change oldest first, at most the newest 24, and today at its price now, evenly spaced, the highest price at the top. The points are listed under the line, which says "No price changes recorded." when there is none.
- **Similar vehicles**: at most four not sold, of the vehicle's make and body style, itself left out, nearest its list price and then by VIN, as a `DataTable` whose VINs link to their pages. "No similar vehicles on the lot." when there are none.
- **Leads and holds**: the vehicle's newest ten leads (day, visitor linking to the lead, kind, stage, salesperson or "Unassigned") and its newest ten holds (day placed, customer, placer, Active, Expired, Released or Sold, expiry), each table with its empty line.
