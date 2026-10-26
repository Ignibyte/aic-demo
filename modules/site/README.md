# Lotline foundation

The homepage introduces Lotline for fictional Ridgeway Motors. It describes the forthcoming dealership operations app and links to the existing database health API. Staff sign-in, inventory, customers, sales and dashboard behavior belong to later features.

`BootstrapPage` puts the theme's `TopBar` in the `header` region and its `BootstrapIntro` in `content`, through `PageDocuments`. Two blocks fill the rest of the drawer on every page: `NavigationBlock` places the sidebar (the site's name, then a link per area with its Heroicon, the current one marked from the request path; areas join as their pages land) and `SiteFooterBlock` the footer line. The components and templates live in `themes/lotline/`.

The framework serves `/assets/aic.css` (the bundled theme stylesheet), the pinned Tessera script and its map, with byte ranges, before the application is assembled. The native comparison steps cover health, a missing route, the homepage, those files, missing and traversal asset requests and a stylesheet range.

The site has no brand icon yet: browser requests for `/favicon.ico` return 404.
