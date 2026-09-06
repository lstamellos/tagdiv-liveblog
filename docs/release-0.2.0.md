# 0.2.0

Adds an optional modern realtime transport without enabling or modifying Automattic Liveblog's legacy Socket.IO 1.x subsystem.

- opt-in `Tagdiv_Liveblog_Realtime` bridge, disabled by default;
- public active Liveblogs only;
- small Redis Pub/Sub invalidation messages, never rendered entry payloads;
- reference single-node Socket.IO 4.8.3 / ioredis 6.0.0 service;
- capture of the existing Automattic Liveblog Redux store through its compose enhancer, without replacing the upstream bundle;
- authoritative reconciliation through Automattic Liveblog's existing entries endpoint;
- native `POLLING_SUCCESS`, `CANCEL_POLLING` and `START_POLLING` actions for reducer-consistent updates and fail-open polling fallback;
- Socket.IO client loaded dynamically so a missing realtime service cannot block the upstream Liveblog app;
- Node service disconnects clients whenever its Redis subscription is unavailable, forcing browsers back to polling;
- native `LIVEBLOG_USE_SOCKETIO` and the new transport are mutually exclusive;
- reference Node service excluded from the WordPress plugin release ZIP.

See [`realtime.md`](realtime.md) for architecture, configuration and deployment order.
