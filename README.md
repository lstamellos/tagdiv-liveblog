# tagDiv Liveblog

A focused integration between the Newspaper theme / tagDiv Composer and [Automattic Liveblog](https://github.com/Automattic/liveblog).

## Architecture

The plugin is deliberately an adapter, not a replacement Liveblog renderer.

Automattic Liveblog continues to own:

- entry storage and rendering;
- live updates and polling;
- REST/AJAX endpoints;
- frontend editing;
- permissions;
- native pagination controls;
- live/archive state and state transitions.

`tagdiv-liveblog` owns:

- the native `Liveblog` element in tagDiv Composer;
- the desired page/template location;
- per-element presentation controls;
- the configured number of entries shown by Automattic Liveblog on each native pagination page;
- integration glue required for numeric entry deep links inside Newspaper/TagDiv templates;
- the archived-state completion notice, its text and its presentation;
- a permission-gated frontend convenience UI for Archive / Reopen that delegates state changes to Automattic Liveblog;
- GitHub Releases integration for WordPress plugin updates;
- a static Composer preview;
- scoped CSS for the upstream Liveblog markup.

## Placement model

Automattic Liveblog injects one `#wpcom-liveblog-container` into post content. This plugin does **not** disable that behavior.

The Composer element renders a slot. On Liveblog posts, a tiny footer script runs before Automattic Liveblog's frontend app and moves the native container into that slot. If a Newspaper template does not render Post Content at all, the script creates the same minimal upstream root (`#wpcom-liveblog-container` with the Liveblog post ID class) before the Liveblog app starts.

There is never a second Liveblog runtime or duplicate container, and no entry markup is reconstructed by JavaScript.

## Composer controls

Version 0.1.16 adds an `Archived notice` control group while retaining the controls introduced through 0.1.15:

- native TagDiv Header settings (`custom_title`, `custom_url`, block header style);
- native TagDiv Design Options / CSS settings for the whole block;
- configurable Liveblog entries per native pagination page, range 1–100, with a default of 20;
- editable plain-text archived notice text;
- archived notice background and text colors;
- archived notice border color, width, style and radius;
- archived notice padding and spacing below the notice;
- archived notice font size, line height, weight, letter spacing and font style;
- archived notice text alignment and text transform;
- author, avatar and timestamp visibility;
- exact, relative or combined timestamp display;
- stacked or inline metadata layout;
- timestamp-first or author-first ordering;
- metadata alignment, separator and character/symbol prefixes;
- entry background, text, border, radius, padding and spacing;
- metadata background, text, padding and spacing;
- content background, text, border, radius and padding;
- optional timeline line rendered behind complete entry boxes.

The plugin does not add its own paginator. First / Prev / Next / Last, polling, page replacement and entry loading remain native Automattic Liveblog behavior.

See [`docs/requirements.md`](docs/requirements.md) for the requirements map and exclusions.

## Archived Liveblog notice

When the resolved Automattic Liveblog state is exactly `archive`, the block renders the configured archived notice text. The default is:

> Η ανταπόκριση έχει ολοκληρωθεί

The notice is placed inside the TagDiv Liveblog slot but outside Automattic Liveblog's React feed. As a result it remains at the top while native pagination replaces pages of entries. Active Liveblogs do not render archived-notice markup on the frontend.

The TagDiv Composer preview always displays the notice so its text and appearance can be configured from the `Archived notice` controls before an article is archived.

The Composer value is plain text and is sanitized before output. Developers may still override the resolved text with the `tagdiv_liveblog_archive_notice_text` filter without changing pagination or Liveblog state behavior.

## Frontend Archive / Reopen controls

For a logged-in user who can edit the specific Liveblog post, the block renders a small context-sensitive management status above the Liveblog feed:

- active: `This liveblog is active.` with an `Archive` button;
- archived: `This liveblog is archived.` with a `Reopen` button.

The integration does not implement a second state-changing API. The button posts to Automattic Liveblog's existing authenticated `set_liveblog_state_for_post` AJAX action using the upstream Liveblog nonce. Automattic Liveblog repeats its own post-scoped capability and nonce checks before changing state.

`Archive` maps to upstream state `archive`; `Reopen` maps to upstream state `enable`. Archive requires a confirmation prompt. After a successful upstream state transition, the page reloads so Automattic Liveblog restarts naturally in the new state and owns all editor, polling and rendering changes.

Users without permission to edit that specific post receive no management markup or management asset on the frontend.

## Entry deep links

Automattic Liveblog uses numeric URL fragments such as `#60819` to open a specific Liveblog entry. The integration keeps that upstream deep-link behavior while making it consistent with the block configuration:

- the deep-link request uses the same entries-per-page value configured in the TagDiv block;
- pagination page count and page navigation are based on a current Liveblog pagination anchor rather than stale cached page state;
- the viewport scrolls to the linked entry after the integrated Liveblog DOM is ready;
- the numeric fragment remains while the linked entry is being viewed;
- the fragment is removed when the visitor leaves that entry context through native Liveblog pagination or the native new-entry control.

The adapter does not replace Automattic Liveblog Redux state, pagination reducers, polling, rendering or controls.

## GitHub Releases as the update source

Starting with 0.1.16, the plugin declares this repository as its WordPress `Update URI` and uses the public GitHub Releases API as its update metadata source.

The updater accepts only:

- a published stable semantic-version release such as `v0.1.16`;
- the exact installable release asset `tagdiv-liveblog-VERSION.zip`;
- an uploaded asset with a GitHub-provided SHA-256 digest.

Before WordPress installs the package, the downloaded ZIP is verified against the release asset's SHA-256 digest.

The updater does **not** force automatic updates. It does not opt the plugin into auto-updates and does not override WordPress's `auto_update_plugin` policy. WordPress's normal per-plugin setting remains authoritative:

- if automatic updates are disabled for tagDiv Liveblog, a new GitHub release is offered as a normal manual plugin update;
- if the site owner has enabled automatic updates for tagDiv Liveblog, WordPress Core may install the same offered release through its normal background update process.

GitHub release metadata is cached for six hours to avoid unnecessary API requests.

## Global Single Template support

The Liveblog block can be placed in a Global Single Cloud Template. On posts without an active or archived Liveblog state it emits no slot or container. On Liveblog posts it resolves the real article ID and mounts the single upstream Liveblog container in the configured block location.

## Requirements

- WordPress 6.4+
- PHP 7.4+
- Newspaper / tagDiv Composer with the `td_api_block` API (Newspaper V11+)
- Automattic Liveblog

## Validated runtime

Version 0.1.16 was validated on OmniaTV with:

- WordPress 7.0.3
- PHP 8.3.6
- Newspaper 12.7.7
- tagDiv Composer 5.4.6
- Automattic Liveblog 1.12.2

Validation covers:

- configurable native entries-per-page pagination;
- numeric entry deep links, target scrolling and immediate native pagination;
- no archived notice on active Liveblogs;
- one archived notice at the top of every native pagination page;
- editable archived-notice text plus Composer style propagation;
- permission-gated frontend Archive / Reopen controls;
- upstream nonce and post-scoped permission enforcement for frontend state changes;
- active → archive and archive → enable transitions with page reload;
- direct entry deep links with the archived notice present;
- GitHub stable-release discovery and exact named release asset selection;
- SHA-256 package verification;
- preservation of the native WordPress per-plugin automatic-update preference;
- successful background auto-update in a true `DOING_CRON` context while the plugin remains active;
- Global Single Template integration.

## Release packaging

GitHub releases include an installable `tagdiv-liveblog-VERSION.zip` asset and a matching SHA-256 checksum.
