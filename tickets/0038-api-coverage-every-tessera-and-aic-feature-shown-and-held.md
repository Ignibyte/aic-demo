---
number: 38
title: "API coverage: every Tessera and AIC feature, shown and held"
type: feature
status: closed
created: 2026-11-02T18:30:00Z
updated: 2026-11-05T10:04:37Z
phase: "docs"
---

Close the expansion by proving coverage. `/theme` gains a "Coverage" page with one row per
Tessera attribute, directive, action, event and runtime member, and one per AIC surface that
`bin/aic code:coverage` (AIC #232) lists: the live example (on the page itself or a link to the
product page that uses it), and the browser test that holds it.

- Every row is green or carries a written reason it is not used (for example SOAP, or Tessera
  helpers if Chad chose none in #26).
- `bin/aic code:coverage --missing` run on Lotline prints nothing but the reasoned rows; the
  ticket's notes paste its output.
- A browser test walks the coverage page and follows every link, so a feature dropped later
  breaks the test.

Needs AIC #232 and tickets #25 to #37.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments

### 2026-11-05T07:21:00Z

`bin/aic code:coverage --missing` on Lotline, on AIC 8e6daa2 (#272), lists 54 surfaces, and each one's
row on `/theme/coverage` gives the reason Lotline does not use it (`config/coverage.php`). Two
natural uses were added in this run: `autofocus` on the staff reply box at `/app/chats/{id}`, and AIC's
`CodeBlock` for this command on the Coverage page.

```text
code:coverage — 204 surfaces: 150 used, 54 unused.

contracts — 22 of 44 used
SURFACE                                   USED BY
AIC\Component\Contracts\Frame             unused
AIC\Component\Contracts\Keyed             unused
AIC\Component\Contracts\Layout            unused
AIC\Component\Contracts\LayoutName        unused
AIC\Component\Contracts\PageLayouts       unused
AIC\Component\Contracts\RenderObserver    unused
AIC\Container\Contracts\ServiceProvider   unused
AIC\Container\Contracts\ServiceRegistry   unused
AIC\Controller\Contracts\CommandWriter    unused
AIC\Controller\Contracts\EventWriter      unused
AIC\Controller\Contracts\JobRecorder      unused
AIC\Controller\Contracts\ResponseWriter   unused
AIC\Database\Contracts\Database           unused
AIC\Http\Contracts\PublicAssets           unused
AIC\Kernel\Contracts\RequestObserver      unused
AIC\Module\Contracts\ModuleDeclaration    unused
AIC\Notification\Contracts\Channel        unused
AIC\Notification\Contracts\MailTransport  unused
AIC\Routing\Contracts\RouteObserver       unused
AIC\Sapi\Contracts\RequestExtractor       unused
AIC\Sapi\Contracts\SapiSink               unused
AIC\Session\Contracts\SessionStore        unused

responses — 11 of 13 used
SURFACE                              USED BY
AIC\Controller\Domain\JobRetry       unused
AIC\Controller\Domain\PageFragments  unused

manifest — 13 of 13 used
(every one used)

schema — 26 of 35 used
SURFACE                                        USED BY
AIC\Schema\Contracts\ColumnTypes::bigInteger   unused
AIC\Schema\Contracts\ColumnTypes::boolean      unused
AIC\Schema\Contracts\ColumnTypes::identity     unused
AIC\Schema\Contracts\ColumnTypes::numeric      unused
AIC\Schema\Contracts\ColumnTypes::text         unused
AIC\Schema\Contracts\ColumnTypes::timestampTz  unused
AIC\Schema\Contracts\Schema::extension         unused
AIC\Schema\Contracts\TableAlteration::unique   unused
AIC\Schema\Contracts\TableBuilder::numeric     unused

query — 15 of 15 used
(every one used)

atoms — 15 of 27 used
SURFACE                             USED BY
AIC\Component\Domain\Badge          unused
AIC\Component\Domain\Checkbox       unused
AIC\Component\Domain\FilterForm     unused
AIC\Component\Domain\Heading        unused
AIC\Component\Domain\Input          unused
AIC\Component\Domain\PasswordInput  unused
AIC\Component\Domain\RawHtml        unused
AIC\Component\Domain\Stack          unused
AIC\Component\Domain\Textarea       unused
AIC\Component\Domain\ThemePage      unused
AIC\Component\Domain\Timeline       unused
AIC\Component\Domain\TrackedAssets  unused

tessera — 48 of 57 used
SURFACE           USED BY
data-style        unused
data-ignore       unused
data-ref          unused
@delete           unused
tessera:exchange  unused
signals           unused
visit             unused
intercept         unused
stop              unused
```
