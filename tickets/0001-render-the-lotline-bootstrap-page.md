---
number: 1
title: "Render the fresh Lotline bootstrap page through components"
type: feature
status: closed
created: 2026-10-23T20:09:43Z
updated: 2026-10-26T20:07:44Z
phase: "docs"
---

Build the first real rendered page in this fresh Lotline app through the installed AIC workflow. This is a bootstrap feature under AIC128 and supplies the real template coverage required before later AIC132 product work. The operator baseline is commit3d5a0bd, not a delivered product. Its earlier complete gate run correctly rejected absent templates and deferred the template gate. Both issues must be resolved by an actual component page, with no dummy template, ignored config path or fake use.

## Acceptance

- GET / returns200 and shows Lotline, Ridgeway Motors and a short honest description of the forthcoming dealership operations app. Link to the existing health endpoint. This is a minimal foundation, not the finished dashboard or sign-in page.
- Use a real Page returning a PageDocument, a Layout with regions and data components. Plain PHP component/layout templates render through AIC's Renderer. No controller markup, flattened Html strings, RawHtml grant or generated-file edits.
- Make the base page readable at desktop and mobile widths. Reuse the existing app-owned assets convention. The approved Tailwind4/DaisyUI5 product design can follow in its own feature; no frontend dependency install is required for this small foundation.
- Serve the existing built stylesheet and pinned Tessera files safely on the compiled runtime. Use the framework's typed response/file APIs. Read-only generic AIC fixture examples in the stable checkout may clarify integration. Do not read or copy old Lotline application source. Reject missing/traversing asset paths appropriately.
- Retain the two existing native comparison steps. Add the actual homepage and served-asset steps to config/native-site.json. No old step is removed or weakened. Build wiring, templates, wiki and assets through composer run build; declare each generated output in the actual run's manifest before creating it.
- Set browser_testable to Yes in the real workflow spec. Use actual browser actions at Check. Observe the homepage heading/content, health link, stylesheet and Tessera requests, and usable mobile layout. Record real observations. Do not call a PHP browser visit proof of compiled browser coverage; identify which server you observed.
- All28 applicable App gates pass with zero deferrals, including full ordinary and taint Psalm. Every runtime class passes native checks and the whole-site steps match PHP. The installed framework/workflow/gate source remains unchanged.
- Complete Plan, Code, Check and Complete through the actual engine. At inspection-due, dispatch the installed native aic-seeker with the exact prompt Inspect the open run. File its actual returned ledger unchanged. Preserve real subject-bound evidence and every advisory.
- Leave the completed run and ticket in review for the primary driver. Do not commit, push or merge. Primary reviews the complete bootstrap PR and performs delivery.

## Boundaries

Work in /srv/stacks/aic-demo on feat/lotline-bootstrap-128. Existing operator controls, exact gate stubs, source packages and secrets are read-only. Use no suppression, baseline, threshold reduction, trust-store edit, permission bypass or synthetic review return. Do not change config/workflow.php or Codex controls. Read the installed skills and code index before proposing the exact source manifest. Report a required framework change to the primary before touching vendor or stable. No permanent test suite.

The current resident thread uses workspace-write with on-request approval and disabled shell network. Dependency/tool caches were populated by operator bootstrap. Do not request full access or write outside the app. Report a genuine environment hold through the workflow rather than bypassing it. The primary is available to resolve operator holds.

Keep this same Codex conversation and tmux agent open indefinitely after the report. The primary sends future assignments with official codex queue.

## Comments

### 2026-10-26T20:07:44Z

Closed by the operator: delivered and merged in aic-demo PR #1 (a64d018) with AIC #128's first theme slice; the page now renders in themes/lotline/.
