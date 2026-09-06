'use strict';

const http = require('node:http');
const { Server } = require('socket.io');
const Redis = require('ioredis');

function envString(name, fallback) {
  const value = process.env[name];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback;
}

function envPort(name, fallback) {
  const value = Number.parseInt(envString(name, String(fallback)), 10);
  if (!Number.isInteger(value) || value < 1 || value > 65535) {
    throw new Error(`${name} must be a TCP port between 1 and 65535`);
  }
  return value;
}

function normalizeSocketPath(path) {
  const clean = `/${String(path || '').replace(/^\/+|\/+$/g, '')}/`;
  return clean === '//' ? '/socket.io/' : clean;
}

function parseAllowedOrigins(value) {
  const origins = String(value || '')
    .split(',')
    .map(origin => origin.trim())
    .filter(Boolean);

  if (origins.length === 0) {
    throw new Error('ALLOWED_ORIGINS must contain at least one public origin');
  }

  return new Set(origins);
}

function parsePostId(value) {
  const text = String(value || '');
  if (!/^\d+$/.test(text)) return null;
  const id = Number(text);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
}

function validateEnvelope(raw) {
  if (Buffer.byteLength(raw, 'utf8') > 65536) return null;

  let payload;
  try {
    payload = JSON.parse(raw);
  } catch (error) {
    return null;
  }

  const postId = parsePostId(payload && payload.post_id);
  if (
    !payload ||
    payload.version !== 1 ||
    payload.event !== 'liveblog.changed' ||
    !postId ||
    payload.room !== `liveblog:${postId}`
  ) {
    return null;
  }

  return {
    postId,
    room: payload.room,
    sentAt: Number(payload.sent_at) || Date.now() / 1000
  };
}

const HOST = envString('HOST', '127.0.0.1');
const PORT = envPort('PORT', 3000);
const SOCKET_PATH = normalizeSocketPath(envString('SOCKET_PATH', '/socket.io/'));
const REDIS_HOST = envString('REDIS_HOST', '127.0.0.1');
const REDIS_PORT = envPort('REDIS_PORT', 6379);
const REDIS_CHANNEL = envString('REDIS_CHANNEL', 'tagdiv-liveblog:events');
const REDIS_USERNAME = envString('REDIS_USERNAME', '');
const REDIS_PASSWORD = envString('REDIS_PASSWORD', '');
const ALLOWED_ORIGINS = parseAllowedOrigins(process.env.ALLOWED_ORIGINS);

let redisReady = false;
let shuttingDown = false;

const redisOptions = {
  host: REDIS_HOST,
  port: REDIS_PORT,
  lazyConnect: false,
  enableReadyCheck: true,
  autoResubscribe: true,
  maxRetriesPerRequest: null
};
if (REDIS_USERNAME) redisOptions.username = REDIS_USERNAME;
if (REDIS_PASSWORD) redisOptions.password = REDIS_PASSWORD;

const subscriber = new Redis(redisOptions);

const httpServer = http.createServer((request, response) => {
  if (request.url === '/healthz') {
    const status = redisReady ? 200 : 503;
    response.writeHead(status, {
      'Content-Type': 'application/json; charset=utf-8',
      'Cache-Control': 'no-store'
    });
    response.end(JSON.stringify({
      ok: redisReady,
      redis: redisReady ? 'ready' : 'unavailable',
      sockets: io ? io.engine.clientsCount : 0
    }));
    return;
  }

  response.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
  response.end('Not found\n');
});

const io = new Server(httpServer, {
  path: SOCKET_PATH,
  transports: ['websocket'],
  serveClient: true,
  maxHttpBufferSize: 65536,
  connectionStateRecovery: {
    maxDisconnectionDuration: 120000,
    skipMiddlewares: false
  },
  allowRequest: (request, callback) => {
    const origin = request.headers.origin;
    callback(null, redisReady && typeof origin === 'string' && ALLOWED_ORIGINS.has(origin));
  }
});

io.on('connection', socket => {
  if (!redisReady) {
    socket.disconnect(true);
    return;
  }

  const postId = parsePostId(socket.handshake.auth && socket.handshake.auth.postId);
  if (!postId) {
    socket.disconnect(true);
    return;
  }

  const room = `liveblog:${postId}`;
  socket.join(room);
  socket.emit('tagdiv-liveblog:ready', {
    postId,
    recovered: Boolean(socket.recovered)
  });
});

async function ensureSubscription() {
  if (shuttingDown) return;
  try {
    await subscriber.subscribe(REDIS_CHANNEL);
    redisReady = true;
    console.log(`[realtime] subscribed to Redis channel ${REDIS_CHANNEL}`);
  } catch (error) {
    redisReady = false;
    console.error('[realtime] Redis subscribe failed:', error.message);
  }
}

subscriber.on('ready', ensureSubscription);
subscriber.on('close', () => {
  if (shuttingDown) return;
  redisReady = false;
  io.disconnectSockets(true);
  console.error('[realtime] Redis connection closed; Socket.IO clients disconnected for polling fallback');
});
subscriber.on('error', error => {
  redisReady = false;
  console.error('[realtime] Redis error:', error.message);
});
subscriber.on('message', (channel, raw) => {
  if (channel !== REDIS_CHANNEL || !redisReady) return;

  const event = validateEnvelope(raw);
  if (!event) {
    console.error('[realtime] ignored invalid Redis event envelope');
    return;
  }

  io.to(event.room).emit('tagdiv-liveblog:changed', {
    postId: event.postId,
    sentAt: event.sentAt
  });
});

httpServer.listen(PORT, HOST, () => {
  console.log(`[realtime] listening on http://${HOST}:${PORT}${SOCKET_PATH}`);
});

async function shutdown(signal) {
  if (shuttingDown) return;
  shuttingDown = true;
  redisReady = false;
  console.log(`[realtime] received ${signal}; shutting down`);

  io.disconnectSockets(true);
  io.close();

  try {
    await subscriber.quit();
  } catch (error) {
    subscriber.disconnect();
  }

  httpServer.close(() => process.exit(0));
  setTimeout(() => process.exit(1), 5000).unref();
}

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));
