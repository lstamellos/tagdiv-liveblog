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
- live/archive state.

`tagdiv-liveblog` owns:

- the native `Liveblog` element in tagDiv Composer;
- the desired page/template location;
- per-element presentation controls;
- the configured number of entries shown by Automattic Liveblog on each native pagination page;
- integration glue required for numeric entry deep links inside Newspaper/TagDiv templates;
- a static Composer preview;
- scoped CSS for the upstream Liveblog markup.

## Placement model

Automattic Liveblog injects one `#wpcom-liveblog-container` into post content. This plugin does **not** disable that behavior.

The Composer element renders a slot. On Liveblog posts, a tiny footer script runs before Automattic Liveblog's frontend app and moves the native container into that slot. If a Newspaper template does not render Post Content at all, the script creates the same minimal upstream root (`#wpcom-liveblog-container` with the Liveblog post ID class) before the Liveblog app starts.

There is never a second Liveblog runtime or duplicate container, and no entry markup is reconstructed by JavaScript.

## Composer controls

Version 0.1.15 includes:

- native TagDiv Header settings (`custom_title`, `custom_url`, block header style);
- native TagDiv Design Options / CSS settings for the whole block;
- configurable Liveblog entries per native pagination page, range 1–100, with a default of 20;
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

## Entry deep links

Automattic Liveblog uses numeric URL fragments such as `#60819` to open a specific Liveblog entry. In a TagDiv-integrated view, version 0.1.15 keeps that upstream deep-link behavior while making it consistent with the block configuration:

- the deep-link request uses the same entries-per-page value configured in the TagDiv block;
- pagination page count and page navigation are based on a current Liveblog pagination anchor rather than stale cached page state;
- the viewport scrolls to the linked entry after the integrated Liveblog DOM is ready;
- the numeric fragment remains while the linked entry is being viewed;
- the fragment is removed when the visitor leaves that entry context through native Liveblog pagination or the native new-entry control.

The adapter does not replace Automattic Liveblog Redux state, pagination reducers, polling, rendering or controls.

## Global Single Template support

The Liveblog block can be placed in a Global Single Cloud Template. On posts without an active or archived Liveblog state it emits no slot or container. On Liveblog posts it resolves the real article ID and mounts the single upstream Liveblog container in the configured block location.

## Requirements

- WordPress 6.4+
- PHP 7.4+
- Newspaper / tagDiv Composer with the `td_api_block` API (Newspaper V11+)
- Automattic Liveblog

## Validated runtime

Version 0.1.15 was validated on OmniaTV with:

- WordPress 7.0.3
- PHP 8.3.6
- Newspaper 12.7.7
- tagDiv Composer 5.4.6
- Automattic Liveblog 1.12.2

Validation covered:

- Global Single Template rendering and non-Liveblog gating;
- configurable native pagination page size;
- active Liveblog polling;
- direct numeric entry deep links;
- immediate First / Prev / Next / Last navigation after a deep-link load;
- correct deep-link scroll targeting;
- archived Liveblog deep-link pagination without polling.

## Release packaging

GitHub releases include an installable `tagdiv-liveblog-VERSION.zip` asset and a matching SHA-256 checksum.
