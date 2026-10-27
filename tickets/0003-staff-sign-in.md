---
number: 3
title: "Staff sign in, with roles"
type: feature
status: in-review
created: 2026-10-26T23:30:00Z
updated: 2026-10-27T05:01:42Z
phase: "docs"
---

Ridgeway Motors' staff sign in to Lotline. Everything under `/app` is for signed-in staff only.
This is the first product feature; old Lotline's ticket 4 (`/srv/stacks/lotline`, read only) is
the reference for behaviour, not for code.

What to build, as a new `staff` module on AIC's Auth (`AIC\Auth`: `Authenticator`, the
`UserProvider` and `UserCredentials` contracts, `PgcryptoPasswordHasher`,
`RequireAuthenticationMiddleware` with `RouteAuthConfig`) and AIC's sessions:

- A users table by migration: email (unique), name, role, password hash (pgcrypto bcrypt), created
  at. Roles are admin, sales manager and salesperson.
- `bin/aic user:add <login> <password>`: makes or updates a user who can sign in, the login being
  the email. gate:23 and `vendor/bin/aic-gate browser` make their own user with it
  (`AIC_GATE_LOGIN`, `AIC_GATE_PASSWORD`), so it must take exactly those two arguments; give such a
  user the admin role.
- `bin/aic staff:seed`: the admin from `LOTLINE_ADMIN_EMAIL`, `LOTLINE_ADMIN_NAME` and
  `LOTLINE_ADMIN_PASSWORD` (it refuses without them), and four invented `@ridgeway-motors.test`
  staff only when `LOTLINE_STAFF_PASSWORD` is set. No password in the repository; DDEV's ignored
  `.ddev/config.local.yaml` holds the local ones.
- `GET /sign-in` (the form, keeping where the visitor was going), `POST /sign-in` (on success a 303
  back there, kept on the site, else to `/app`; a wrong email or password shows the form again as
  422 without saying which), `POST /sign-out` (ends the session, 303 to `/`).
- `GET /app`: a first page for signed-in staff (the dashboard comes later) that greets the member of
  staff by name and role. A signed-out visit to anything under `/app` goes to `/sign-in` and comes
  back after it.
- The header's signed-in menu (the theme's `SignedInMenu`) shows the name and role and a sign-out
  button; signed out, a "Sign in" link. Use the theme's components; add one only if none fits.
- A step in `config/native-site.json` for every new page, and the steps for the flow: an anonymous
  `/app` (303), the sign-in page, a refused password (422), sign-in (303), `/app` signed in (200),
  sign-out (303) and `/app` again (303).

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, each a Playwright test the run keeps in `tests/browser/`: a signed-out visit to
  `/app` lands on sign-in and comes back after signing in; the header shows the name; a refused
  password shows the error; signing out locks `/app` again. They pass against DDEV
  (https://aic-demo.ddev.site:8843) and in gate:23 on the compiled site.

Do not start inventory or any other feature. When this run completes, stop.

## Comments
