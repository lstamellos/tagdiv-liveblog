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
- lazy loading;
- live/archive state.

`tagdiv-liveblog` owns:

- the native `Liveblog` element in tagDiv Composer;
- the desired page/template location;
- per-element presentation controls;
- a static Composer preview;
- scoped CSS for the upstream Liveblog markup.

## Placement model

Automattic Liveblog injects one `#wpcom-liveblog-container` into post content. This plugin does **not** disable that behavior.

The Composer element renders a slot. On Liveblog posts, a tiny footer script runs before Automattic Liveblog's frontend app and moves the native container into that slot. If a Newspaper template does not render Post Content at all, the script creates the same minimal upstream root (`#wpcom-liveblog-container` with the Liveblog post ID class) before the Liveblog app starts.

There is never a second Liveblog runtime or duplicate container, and no entry markup is reconstructed by JavaScript.

## Composer controls

Version 0.1.12 includes:

- native TagDiv Header settings (`custom_title`, `custom_url`, block header style);
- native TagDiv Design Options / CSS settings for the whole block;
- author, avatar and timestamp visibility;
- exact, relative or combined timestamp display;
- stacked or inline metadata layout;
- timestamp-first or author-first ordering;
- metadata alignment, separator and character/symbol prefixes;
- entry background, text, border, radius, padding and spacing;
- metadata background, text, padding and spacing;
- content background, text, border, radius and padding;
- optional timeline line rendered behind complete entry boxes.

See [`docs/requirements.md`](docs/requirements.md) for the requirements map and exclusions.

## Global Single Template support

The Liveblog block can be placed in a Global Single Cloud Template. On posts without an active or archived Liveblog state it emits no slot or container. On Liveblog posts it resolves the real article ID and mounts the single upstream Liveblog container in the configured block location.

## Requirements

- WordPress 6.4+
- PHP 7.4+
- Newspaper / tagDiv Composer with the `td_api_block` API (Newspaper V11+)
- Automattic Liveblog

## Validated runtime

Version 0.1.12 was validated on OmniaTV with:

- WordPress 7.0.3
- PHP 8.3.6
- Newspaper 12.7.7
- tagDiv Composer 5.4.6
- Automattic Liveblog 1.12.2

The production Global Single Template gate was verified with one Liveblog slot/container on a Liveblog post and no slot/container on a normal post.

## Release packaging

GitHub releases include an installable `tagdiv-liveblog-VERSION.zip` asset and a matching SHA-256 checksum.
