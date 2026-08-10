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

The Composer element renders a slot. On liveblog posts, a tiny footer script runs before Automattic Liveblog's frontend app and moves the native container into that slot. If a Newspaper template does not render Post Content at all, the script creates the same minimal upstream root (`#wpcom-liveblog-container` with the liveblog post ID class) before the Liveblog app starts. This has two useful properties:

1. there is never a second Liveblog runtime or duplicate container;
2. if the Composer element is absent, Liveblog remains in its native location and continues to work normally.

No entry markup is reconstructed by JavaScript.

## Initial controls

The first implementation includes controls for:

- optional title;
- author/avatar/timestamp visibility;
- metadata alignment;
- entry background, text, border, radius, padding and spacing;
- metadata background, text, padding and spacing;
- content background, text and padding;
- optional timeline line;
- tagDiv Design Options.

See [`docs/requirements.md`](docs/requirements.md) for the full requirements map and exclusions.

## Requirements

- WordPress 6.4+
- PHP 7.4+
- Newspaper / tagDiv Composer with the `td_api_block` API (Newspaper V11+)
- Automattic Liveblog

## Canary status

OmniaTV runtime compatibility has been verified against WordPress 7.0.3, Newspaper 12.7.7, tagDiv Composer 5.4.6 and Liveblog 1.12.2.

The first Composer canary confirmed that the single native Liveblog root is relocated into the block correctly. Version 0.1.2 fixes color handling discovered during that canary: tagDiv's color picker can emit RGBA values, so presentation colors are validated as tagDiv-compatible color values instead of being restricted to WordPress hex-only colors.

The remaining canary check is to distinguish an empty controlled test Liveblog from a frontend API/runtime failure before changing the relocation mechanism.

## Status

Early integration scaffold. Production Single Post templates should not be changed until the Composer and frontend canary checks are complete.
