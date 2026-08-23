# tagDiv Liveblog 0.1.22

This maintenance release scopes the integration stylesheet to pages where the Liveblog block can actually render.

Changes:
- the main `tagdiv-liveblog.css` stylesheet is no longer enqueued on ordinary singular posts/pages;
- the stylesheet remains available inside TagDiv Composer for block preview/editing;
- active and archived Liveblog posts continue to receive the main stylesheet and Liveblog runtime integration assets;
- permission-gated management CSS/JS remains limited to users who can manage the resolved Liveblog;
- no rendering, pagination, Key Events, polling or state behavior is changed.
