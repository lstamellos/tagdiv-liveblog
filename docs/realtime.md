# Optional realtime transport

`tagdiv-liveblog` 0.2.0 adds an opt-in realtime transport for public, active Automattic Liveblog posts.

It deliberately does **not** enable Automattic Liveblog's legacy `LIVEBLOG_USE_SOCKETIO` subsystem. Automattic Liveblog 1.12.x ships a Socket.IO 1.x browser client and a legacy Redis emitter protocol; this adapter instead uses a current Socket.IO 4 server as an invalidation signal service while keeping Automattic Liveblog's own endpoint and Redux reducers authoritative.

## Data flow

```text
Automattic Liveblog CRUD
        |
        | liveblog_*_entry hooks
        v
Tagdiv_Liveblog_Realtime
        |
        | Redis PUBLISH (small JSON invalidation envelope)
        v
Dedicated Redis instance
        |
        | Pub/Sub
        v
realtime-server (Socket.IO 4)
        |
        | tagdiv-liveblog:changed
        v
browser bridge
        |
        | authoritative GET to Automattic Liveblog entries endpoint
        | dispatches native POLLING_SUCCESS into upstream Redux store
        v
Automattic Liveblog reducers/UI
```

Native polling starts normally. After Socket.IO connects, the bridge first reconciles through Automattic Liveblog's own entries endpoint. Only after that succeeds does it dispatch `CANCEL_POLLING`. On disconnect, connect failure, or reconciliation failure it dispatches `START_POLLING`.

The Node service also disconnects clients whenever its Redis subscription becomes unavailable, forcing browsers back to polling.

## Scope

Realtime is enabled only when all of the following are true:

- `TAGDIV_LIVEBLOG_REALTIME_ENABLED` is true;
- Automattic's legacy `LIVEBLOG_USE_SOCKETIO` is not true;
- the post is published and publicly viewable;
- the Liveblog state is exactly `enable`.

## WordPress configuration

```php
define( 'TAGDIV_LIVEBLOG_REALTIME_ENABLED', true );
define( 'TAGDIV_LIVEBLOG_REALTIME_URL', 'https://example.com' );
define( 'TAGDIV_LIVEBLOG_REALTIME_PATH', '/socket.io/' );
define( 'TAGDIV_LIVEBLOG_REALTIME_REDIS_HOST', '127.0.0.1' );
define( 'TAGDIV_LIVEBLOG_REALTIME_REDIS_PORT', 6379 );
define( 'TAGDIV_LIVEBLOG_REALTIME_REDIS_CHANNEL', 'tagdiv-liveblog:events' );
```

Optional Redis ACL/password constants are `TAGDIV_LIVEBLOG_REALTIME_REDIS_USERNAME` and `TAGDIV_LIVEBLOG_REALTIME_REDIS_PASSWORD`. Optional timeouts are `TAGDIV_LIVEBLOG_REALTIME_REDIS_TIMEOUT` (seconds) and `TAGDIV_LIVEBLOG_REALTIME_RECONCILE_TIMEOUT` (milliseconds).

Deployment-specific values belong in server-side configuration. The plugin contains no site-specific hostnames, Unix users, filesystem paths or credentials.

## Reference Node service

The reference service in `realtime-server/` targets Node.js 24 LTS, Socket.IO 4.8.3 and ioredis 6.0.0. It binds to `127.0.0.1:3000` by default and requires an `ALLOWED_ORIGINS` allow-list.

Default environment variables:

```text
HOST=127.0.0.1
PORT=3000
SOCKET_PATH=/socket.io/
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CHANNEL=tagdiv-liveblog:events
ALLOWED_ORIGINS=https://example.com
```

It exposes `/healthz`, returning HTTP 200 only while the Redis subscription is ready.

## Apache 2.4.47+

```apache
ProxyPass        "/socket.io/" "http://127.0.0.1:3000/socket.io/" upgrade=websocket retry=0
ProxyPassReverse "/socket.io/" "http://127.0.0.1:3000/socket.io/"
```

## Redis event contract

```json
{
  "version": 1,
  "event": "liveblog.changed",
  "room": "liveblog:123",
  "post_id": 123,
  "sent_at": 1788656400.123
}
```

Redis and Socket.IO are invalidation transports only. Entry data remains authoritative in Automattic Liveblog.

## Deployment order

1. Provision dedicated Redis.
2. Install Node 24 LTS and the reference service dependencies.
3. Verify `/healthz` on loopback.
4. Add and validate the HTTPS reverse proxy.
5. Verify the Socket.IO client bundle and WebSocket upgrade externally.
6. Deploy tagDiv Liveblog 0.2.0 with realtime still disabled.
7. Add the WordPress realtime constants last.
8. Validate on a public canary Liveblog.

Rollback is immediate: remove or set `TAGDIV_LIVEBLOG_REALTIME_ENABLED` to false. Native polling remains authoritative.
