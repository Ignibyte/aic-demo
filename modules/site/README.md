# Lotline foundation

The homepage introduces Lotline for fictional Ridgeway Motors. It describes the forthcoming dealership operations app and links to the existing database health API. Staff sign-in, inventory, customers, sales and dashboard behavior belong to later features.

`BootstrapPage` composes the `BootstrapIntro` data component in the content region of `BootstrapLayout`. Plain PHP templates are compiled by `composer run build` and rendered by AIC. The layout tracks the bundled stylesheet and the Tessera release pinned in application config. The top-level `assets/base.css` supplies the responsive foundation styles.

`AssetFile` and `RequestedAsset` serve only `/assets/aic.css`, the configured Tessera script and its `.map` through AIC's typed inline file response. The current pin serves `/assets/tessera-0.1.0/tessera.csp.iife.js` and its source map. Unknown URLs and traversal attempts answer 404. The script and map paths derive from the same validated `AppConfig` release used by the page and build. The stylesheet path is literal; request text never becomes a filesystem path. The framework handles file absence and byte ranges. A Tessera upgrade changes the application pin and corresponding native comparison URLs, with no second version in the asset service to synchronize.

The native comparison script retains the original health and missing-route requests and adds the homepage, all served files, missing and traversal asset requests, and stylesheet range behavior. The workflow engine owns the full gate and inspection evidence. Browser observations distinguish the configured DDEV PHP reference from the compiled native server.

The bootstrap has no brand icon: browser requests for `/favicon.ico` return 404. This is a known cosmetic gap for the upcoming product shell. The page, health endpoint and required built assets succeed; browser verification does not claim zero console errors.
