# OmniaTV runtime compatibility audit — 2026-08-10

Read-only audit source commit: `cee0f3a806b9ad1a52c761d4b88f087abdf79d36`.

## Runtime

- WordPress: 7.0.3
- PHP: 8.3.6
- Multisite: yes
- Child theme: `omniatv` 9.0c
- Parent theme: Newspaper 12.7.7
- tagDiv Composer: 5.4.6
- Automattic Liveblog: 1.12.2
- Legacy WP Liveblog Appearance Layer: 0.10.8, inactive

## tagDiv API verified

The installed runtime exposes:

- `td_api_block::add()`
- `td_block::render()`
- `td_block::get_block_classes()`
- `td_block::get_block_css()`
- `td_block::get_shortcode_att()`
- `td_util::tdc_is_live_editor_iframe()`
- `td_util::tdc_is_live_editor_ajax()`
- `td_config::get_map_block_general_array()`

Both `tdc_init` and `tdc_loaded` fire in the loaded runtime.

The integration therefore uses the documented tagDiv plugin lifecycle and merges `td_config::get_map_block_general_array()` into the custom control map instead of reimplementing standard block/Design Options parameters.

## Liveblog API verified

The installed runtime exposes:

- `WPCOM_Liveblog::is_liveblog_post()`
- `WPCOM_Liveblog::add_liveblog_to_content()`
- `WPCOM_Liveblog::enqueue_scripts()`
- Liveblog frontend enqueue priority: 10
- Liveblog key: `liveblog`
- Supported post type: `post`

The installed Liveblog version uses the expected native single root:

```html
<div id="wpcom-liveblog-container" class="POST_ID"></div>
```

and its upstream single-entry template uses these presentation selectors:

- `.liveblog-entry`
- `.liveblog-meta`
- `.liveblog-author-avatar`
- `.liveblog-author-name`
- `.liveblog-meta-time`
- `.liveblog-time-update`
- `.liveblog-entry-text`

This matches the scoped selectors used by the integration scaffold.

## Existing content

The site contains 109 Liveblog posts discovered through the native `liveblog` state:

- `archive`: 70
- `enable`: 39

The published post `853492305` (`Liveblog test`, 2025-01-27) is an appropriate initial canary target.

## Legacy plugin state

The old appearance/control plugin is inactive. Its `wlcp_settings` option remains in the database, but the legacy class is not loaded and scan state/results options are absent. This means it will not compete for frontend hooks during canary validation and its retained option can remain untouched for rollback/history.

## Canary constraints

- Do not network-activate the integration during initial validation.
- Activate only for the primary `omniatv.com` site in the multisite network.
- Do not modify or delete the legacy plugin/options during the canary.
- Validate the Composer element first on a non-production template or controlled test template.
- Validate frontend placement/rendering against post `853492305` before applying the element to the production Single Post template.
