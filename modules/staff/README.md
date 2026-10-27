# Staff

Ridgeway Motors' staff sign in to Lotline, and everything under `/app` is theirs alone. The module builds on AIC's Auth: `AIC\Auth\Application\Authenticator` signs a member in and out and keeps their id in the session, `PgcryptoPasswordHasher` hashes passwords with Postgres pgcrypto (bcrypt), and `RequireAuthenticationMiddleware` sends a signed-out visitor to anything under `/app` to `/sign-in?next=…`.

The manifest binds `UserProvider` to `StaffRepository` and `PasswordHasher` to `PgcryptoPasswordHasher`, runs the middleware after the session over the `RouteAuthConfig` that the `AccessRules` factory makes (every module's protected prefixes, sign-in at `/sign-in`), and protects `/app`. It exports `StaffPages` (the staff pages' document) and `StaffSession` (who is signed in), which `inventory` and `site`'s navigation use.

## Data

`CreateUsersTable` makes `users`: an identity `id`, `email` (unique; the login), `name`, `role` and `password_hash`, with `created_at`. Roles are `Role` cases stored as `admin`, `sales_manager` and `salesperson`; `Roles::stored()` reads a stored value back and refuses any other. `Role::label()` is written as if/return, since elephc cannot lower a `match` over enum cases yet (AIC #164).

## Pages

- `GET /sign-in` (`SignInPage`): the form from `SignInForm`, carrying `next`. The email is a text field, so a login such as the gate's `gate` passes the browser.
- `POST /sign-in` (`SignInPost`): on success a full visit (303) to `next` when it is a path on this site, else to `/app`; a wrong email or password answers the form again as 422 with "Wrong email or password.", the email kept and the password never.
- `POST /sign-out` (`SignOutPost`): ends the session, a full visit (303) to `/`.
- `GET /app` (`StaffHomePage`): greets the member by name and role until the dashboard lands. The inventory list, `/app/inventory`, is the `inventory` module's.

`AccountMenuBlock` puts the account corner in the `header` region on every page, after the page's `TopBar`: the theme's `SignedInMenu` (name, role, email and the sign-out form; the block sets its id stem, `account`, so its ids are `account-name`, `account-role`, `sign-out-form` and `sign-out`), or a "Sign in" link. A block takes no services, so `StaffSession` keeps the signed-in member's email, name and role in the session at sign-in, and both the block and `/app` read them there. Signing out clears the whole session.

## Commands

- `bin/aic user:add {login} {password}`: exactly those two arguments. A new login becomes an admin named by its login; an existing one only gets the new password. gate:23 and `vendor/bin/aic-gate browser` make their own `gate` user with it.
- `bin/aic staff:seed`: no arguments. It saves the admin from `LOTLINE_ADMIN_EMAIL`, `LOTLINE_ADMIN_NAME` and `LOTLINE_ADMIN_PASSWORD` (exit 2 without them), and, when `LOTLINE_STAFF_PASSWORD` is set, four invented staff sharing that password: Dana Whitlock (sales manager), Leo Fontaine, Marcus Hale and Priya Natarajan (salespeople), all at `@ridgeway-motors.test`. Run again, it updates them. `config/staff.php` reads the settings, the one place that touches the environment, and the `StaffSettings` factory hands them over. No password lives in the repository; on a dev box DDEV's ignored `.ddev/config.local.yaml` holds them.

Run `bin/aic migrate` first. From the host, point it at DDEV's published Postgres port (`PGPORT`, from `ddev describe`).

## Checks

`config/native-site.json` walks the flow: an anonymous `/app` (303), the sign-in page, a refused password (422), sign-in (303), `/app` signed in (200), sign-out (303) and `/app` again (303). The Playwright tests in `tests/browser/` look at it in a browser: a signed-out `/app` comes back after sign-in, the header shows the name, a refused password shows the error, and signing out locks `/app` again.
