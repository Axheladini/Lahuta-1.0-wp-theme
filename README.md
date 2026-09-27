# API Only — headless WordPress theme

A minimal theme for running WordPress as a pure backend/CMS, with all content
served through the REST API (`/wp-json/...`) to a separate frontend (React,
Next.js, Vue, a mobile app, etc.). WPGraphQL works fine alongside it too,
since this theme doesn't touch GraphQL — it only cleans up the REST/front-end
side of core.

## Install

1. Zip and upload, or copy the `api-only-theme` folder into `wp-content/themes/`.
2. Activate it under **Appearance → Themes**.
3. That's it — no theme options screen, no customizer settings.

## What it does

- Strips front-end `<head>` bloat (RSD link, generator tag, emoji scripts, oEmbed links).
- Redirects any normal front-end page request (`/`, `/2026/09/hello-world/`, etc.)
  to the REST API root, so there's no dead theme template rendering.
- Disables XML-RPC.
- Sends CORS headers on REST responses so a frontend on a different origin can call it.
- Adds a `featured_image` object (full/large/medium/thumbnail URLs + alt text)
  to `posts` and `pages` REST responses.
- Registers an example custom endpoint: `GET /wp-json/api-only/v1/site-info`.

## Configuration (in `wp-config.php`)

```php
// Restrict CORS to specific frontend origins (comma-separated).
// If omitted, all origins are allowed (Access-Control-Allow-Origin: *).
define( 'API_ONLY_ALLOWED_ORIGINS', 'https://app.example.com,https://staging.example.com' );

// Set to false if you'd rather NOT redirect front-end URLs to /wp-json/
// (e.g. return WordPress's normal 404 behavior instead).
define( 'API_ONLY_REDIRECT_FRONTEND', true );
```

## Authenticating write requests

WordPress core ships with **Application Passwords** (Users → Profile). Issue
one per integration and send it as HTTP Basic auth from your frontend's
server (never from client-side JS). For public/untrusted clients, add a
JWT or OAuth2 plugin in front of the REST API instead of Application Passwords.

## Exposing your own data

- Custom post types: register with `'show_in_rest' => true` and a `rest_base`.
- Custom fields: register with `register_post_meta( ..., array( 'show_in_rest' => true ) )`.
- Custom aggregated endpoints: add more routes in `functions.php` following the
  `api-only/v1/site-info` example, or split them into an mu-plugin if you'd
  rather keep endpoint logic out of the theme entirely.

## Notes

- This theme intentionally has no styling, templates, menus, or widget areas —
  there is nothing for a human visitor to see.
- If you also install WPGraphQL, it works independently of this theme; nothing
  here needs to change.
