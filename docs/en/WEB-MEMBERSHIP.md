# Web Membership and "Sign in with Google" - Implementation Guide

Raptor ships a guest cart and checkout (`Web\Shop\Cart`, `/cart`, `/order`) but **no customer accounts** on the public web. This is deliberate: most sites built on Raptor (news, pages, institutional portals) never need visitor accounts, and a membership subsystem (registration, e-mail verification, password reset, social login, profile, order history, account deletion) would touch the layout, routing, session and CSRF of every project that does not use it.

This guide is for a project that does need it. Build it inside that project, following the rules below, so it stays in one folder and plugs into the existing shop with a few small hooks.

---

## 1. Layout

Keep the whole feature in one module folder so it can be removed by deleting that folder plus a handful of lines:

```
application/web/account/
  AccountController.php     # register, login, logout, profile, order history
  GoogleAuthController.php  # OAuth redirect + callback
  CustomersModel.php        # customers table
  login.html, register.html, profile.html, orders.html
```

Register the `Web\Account\` namespace in `composer.json` (`autoload.psr-4`) and run `composer dump-autoload`.

## 2. Customers are NOT dashboard users

Store visitors in their own table (e.g. `customers`), never in `users`. The `users` table drives dashboard RBAC; mixing customers into it means a single bug in a role check becomes dashboard access. Every mature platform separates them (Sylius `ShopUser` vs `AdminUser`, Laravel separate guards).

Suggested columns:

| Column | Notes |
|---|---|
| `id` | primary |
| `email` | unique, lowercase |
| `password` | `password_hash()`; NULL for Google-only accounts |
| `google_sub` | unique, NULL when never linked |
| `name`, `phone`, `address` | used to pre-fill checkout |
| `email_verified_at` | datetime |
| `is_active` | soft delete / block |
| `created_at`, `updated_at` | |

Let the Model create the table (no CREATE TABLE in migrations). Add a nullable `customer_id` column to `products_orders` with a migration (`ALTER TABLE products_orders ADD COLUMN customer_id BIGINT`) to link orders to accounts.

## 3. Session

- Keep the logged-in customer in its own session key, e.g. `$_SESSION['RAPTOR_WEB_CUSTOMER_ID']` - the session is shared with the dashboard, so never reuse a dashboard key.
- Every route that writes the session (login, logout, register, OAuth callback, profile update) MUST use the `/session/` prefix in `WebRouter.php` - other routes get a read-only session.
- Call `session_regenerate_id(true)` right after a successful login (session fixation).
- The guest cart (`Cart::SESSION_KEY`) lives in the same session, so it survives login automatically. If you also persist carts per account, merge the guest cart into the saved one on login.

## 4. CSRF on the public web

Today the public forms carry no account, so they rely on the spam token and the `SameSite=Lax` session cookie. Once a visitor can be logged in, every state-changing account route (profile, password, address book, logout, checkout as a member) needs a real CSRF token:

- generate a per-session token on login, print it as a hidden form field / meta tag,
- compare it with `hash_equals()` in the controller (or reuse `Dashboard\CsrfMiddleware` on those routes with a web-side token key).

Logout must be a POST, not a GET link.

## 5. "Sign in with Google" (OpenID Connect)

"OAuth with Gmail" in a client request means **Sign in with Google**, not the Gmail API. Request only `openid email profile`. Gmail API scopes trigger Google's restricted-scope verification and are not needed for login.

Setup: Google Cloud Console -> APIs & Services -> Credentials -> OAuth client ID (Web application). Authorized redirect URI: `https://example.com/session/account/google/callback`. Google matches the URI exactly, so always send the default-language (unprefixed) callback URL, even when the flow starts on `/en/...`; store the page to return to in the session together with `state`. Put the values in `.env`:

```
RAPTOR_GOOGLE_CLIENT_ID=
RAPTOR_GOOGLE_CLIENT_SECRET=
```

Empty `RAPTOR_GOOGLE_CLIENT_ID` = hide the Google button (feature off).

Flow (authorization code + PKCE), no extra package needed:

1. `GET /session/account/google` - create `state` and a PKCE `code_verifier` (random, 32+ bytes), store both in the session, redirect to `https://accounts.google.com/o/oauth2/v2/auth` with `response_type=code`, `client_id`, `redirect_uri`, `scope=openid email profile`, `state`, `code_challenge` (base64url SHA-256 of the verifier), `code_challenge_method=S256`.
2. `GET /session/account/google/callback` - reject when `state` does not match the session value (`hash_equals`), then delete it from the session (single use).
3. POST the `code` + `code_verifier` + client secret to `https://oauth2.googleapis.com/token` (`codesaur/http-client`).
4. Verify the returned `id_token` with `firebase/php-jwt` (already a Raptor dependency) against Google's keys from `https://www.googleapis.com/oauth2/v3/certs` (`JWK::parseKeySet()`, cache them). Check `iss` is `https://accounts.google.com` or `accounts.google.com`, `aud` equals your client id, and `exp` is in the future.
5. Identify the account by **`sub`**, never by e-mail. E-mail addresses can change and be recycled; `sub` cannot.

Account linking rules (this is where account takeover bugs come from):

- `google_sub` found -> log that customer in.
- Not found, and a customer with the same e-mail exists -> link only when the token says `email_verified: true`; otherwise refuse and ask the visitor to sign in with a password first.
- Not found at all -> create a customer with `google_sub`, `email`, `name`, `email_verified_at = now`, `password = NULL`.

## 6. Checkout for members

Keep guest checkout. Forced registration is one of the most common reasons shoppers abandon a cart. For a logged-in customer:

- pre-fill name, e-mail, phone and address on `/order` from the `customers` row,
- in `ShopController::orderSubmit()` set `customer_id` on the order when a customer is logged in (read the id from the session; never trust a posted id),
- list the customer's own orders on `profile/orders` with `WHERE customer_id = :id`.

## 7. Password accounts

If you offer e-mail + password as well as Google:

- `password_hash()` / `password_verify()`, a minimum length, and allow long passwords (at least 64 characters),
- e-mail verification and password reset links use random single-use tokens with an expiry, stored hashed; mark a token used instead of deleting it (same pattern as the dashboard `forgot` table),
- rate-limit login and reset requests (see `LoginController` for the dashboard version),
- show the same message for "unknown e-mail" and "wrong password".

## 8. Personal data

Customer accounts are personal data. Provide a privacy policy page, a way to delete the account (deactivate, then remove personal fields; keep orders with the name/e-mail snapshot they already store), and do not log passwords, tokens or full OAuth responses.
