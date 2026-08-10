# Requirements

This file translates the useful product requirements from the legacy `lstamellos/wp-liveblog-backend` plugin into a clean integration specification. Legacy implementation details are intentionally not carried over.

## Scope

`tagdiv-liveblog` is a presentation and placement adapter between:

- Newspaper / tagDiv Composer
- Automattic Liveblog

Automattic Liveblog remains the source of truth for entries, polling, REST/AJAX, editor permissions, live/archive state, lazy loading and frontend editing.

## Presentation requirements

### Element and placement

- Provide a native `Liveblog` element in tagDiv Composer.
- Allow the element to be placed in a Newspaper template like any other Composer block.
- Preserve the single native `#wpcom-liveblog-container`; do not create a second Liveblog runtime.
- If the tagDiv element is absent, preserve Automattic Liveblog's native placement and behavior.

### General

- Optional liveblog heading/title.
- Per-element settings, not site-wide global appearance settings.
- Standard tagDiv Design Options support.

### Entry

- Background color.
- Text color.
- Border color and width.
- Border radius.
- Padding.
- Spacing between entries.

### Metadata

- Show/hide author name.
- Show/hide avatar.
- Show/hide timestamp.
- Meta alignment.
- Author positioning/layout is deferred until the exact installed Liveblog markup is validated; it must not be emulated through DOM reconstruction.
- Background and text colors.
- Padding and spacing.

### Content

- Background color.
- Text color.
- Padding.
- Future typography controls should use tagDiv-native font controls where available.

### Timeline

- Optional connector line.
- Line color.
- Line width.
- Position/offset.

### Responsive

- Use tagDiv responsive parameter types where the installed Newspaper/td-composer version confirms their runtime format.
- Desktop/tablet/mobile behavior must be validated in the actual Composer before responsive controls are promoted from experimental to stable.

## Composer preview

- Do not start Liveblog polling or frontend editing inside Composer.
- Render a representative static preview using upstream Liveblog class names.
- Composer controls should visibly update the preview.

## Dynamic entry compatibility

- Newly inserted entries must inherit presentation automatically through scoped CSS.
- Do not repair or reconstruct entry markup with JavaScript.
- JavaScript may relocate the single upstream root container into the tagDiv slot before the upstream Liveblog frontend application starts.

## Explicitly excluded

The following legacy features are outside this plugin's initial scope:

- Liveblog post scanner.
- Liveblog post list/control panel.
- Custom open/close administration UI.
- Generic `pre_get_posts` manipulation.
- Generic `posts_per_page` overrides.
- REST request rewriting.
- Liveblog template overrides.
- DOM reconstruction of metadata blocks.
- Site-wide high-priority CSS overrides.

## Deferred behavioral requirements

The legacy plugin also exposed behavior controls such as newest-first ordering, refresh interval and maximum entries per page. These should only be added if there is a clear editorial requirement and a narrow upstream Liveblog API/filter supports them. They must not be implemented through global WordPress query or REST overrides.
