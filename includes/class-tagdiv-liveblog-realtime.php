<?php
/**
 * Optional modern realtime transport for Automattic Liveblog.
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges authoritative Automattic Liveblog changes to an external Socket.IO
 * service without enabling Automattic Liveblog's legacy Socket.IO 1.x stack.
 */
final class Tagdiv_Liveblog_Realtime {
	const DEFAULT_REDIS_CHANNEL = 'tagdiv-liveblog:events';
	const DEFAULT_SOCKET_PATH   = '/socket.io/';

	/**
	 * Posts changed during the current request.
	 *
	 * @var array<int,bool>
	 */
	private static $pending_posts = array();

	/**
	 * Register hooks when the opt-in transport is enabled.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_configured_enabled() ) {
			return;
		}

		// Run before Automattic Liveblog enqueues its footer app at priority 10.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 4 );

		// Queue authoritative changes and publish only at shutdown, after the
		// upstream CRUD method has completed all comment/meta mutations.
		add_action( 'liveblog_insert_entry', array( __CLASS__, 'queue_change' ), 100, 2 );
		add_action( 'liveblog_update_entry', array( __CLASS__, 'queue_change' ), 100, 2 );
		add_action( 'liveblog_delete_entry', array( __CLASS__, 'queue_change' ), 100, 2 );
		add_action( 'shutdown', array( __CLASS__, 'publish_pending_changes' ), 10 );
	}

	/**
	 * Whether the adapter's realtime transport is explicitly enabled.
	 *
	 * Native Automattic Socket.IO and this transport are mutually exclusive to
	 * avoid duplicate update paths.
	 *
	 * @return bool
	 */
	public static function is_configured_enabled() {
		if ( ! defined( 'TAGDIV_LIVEBLOG_REALTIME_ENABLED' ) || ! TAGDIV_LIVEBLOG_REALTIME_ENABLED ) {
			return false;
		}

		if ( defined( 'LIVEBLOG_USE_SOCKETIO' ) && LIVEBLOG_USE_SOCKETIO ) {
			return false;
		}

		return true;
	}

	/**
	 * Enqueue the store bridge on public, active Liveblog posts only.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		$post_id = absint( get_queried_object_id() );

		if ( ! self::is_public_active_liveblog( $post_id ) ) {
			return;
		}

		$settings = array(
			'url'               => self::socket_origin(),
			'path'              => self::socket_path(),
			'client_url'        => self::socket_client_url(),
			'post_id'           => $post_id,
			'room'              => 'liveblog:' . $post_id,
			'reconcile_timeout' => self::reconcile_timeout_ms(),
		);

		/**
		 * Filters browser-side realtime settings for a Liveblog post.
		 *
		 * @param array $settings Realtime settings.
		 * @param int   $post_id  Liveblog post ID.
		 */
		$settings = apply_filters( 'tagdiv_liveblog_realtime_frontend_settings', $settings, $post_id );

		wp_enqueue_script(
			'tagdiv-liveblog-realtime',
			TAGDIV_LIVEBLOG_URL . 'assets/js/tagdiv-liveblog-realtime.js',
			array(),
			TAGDIV_LIVEBLOG_VERSION,
			true
		);

		wp_localize_script(
			'tagdiv-liveblog-realtime',
			'tagdiv_liveblog_realtime',
			$settings
		);
	}

	/**
	 * Queue a changed Liveblog post for one shutdown publication.
	 *
	 * @param int $comment_id Liveblog comment ID.
	 * @param int $post_id    Liveblog post ID.
	 * @return void
	 */
	public static function queue_change( $comment_id, $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Hook signature.
		$post_id = absint( $post_id );

		if ( self::is_public_active_liveblog( $post_id ) ) {
			self::$pending_posts[ $post_id ] = true;
		}
	}

	/**
	 * Publish one small change signal per post after upstream mutations finish.
	 *
	 * The browser always reconciles against Automattic Liveblog's authoritative
	 * endpoint. Redis never carries rendered entry HTML or permission state.
	 *
	 * @return void
	 */
	public static function publish_pending_changes() {
		if ( empty( self::$pending_posts ) || ! class_exists( 'Redis' ) ) {
			return;
		}

		foreach ( array_keys( self::$pending_posts ) as $post_id ) {
			if ( ! self::is_public_active_liveblog( $post_id ) ) {
				continue;
			}

			self::publish_change( $post_id );
		}

		self::$pending_posts = array();
	}

	/**
	 * Publish a single Liveblog change signal.
	 *
	 * Publication errors intentionally fail open: the Liveblog CRUD request must
	 * never fail because the optional realtime transport is unavailable. Socket
	 * clients fall back to native polling when their Node/Redis path disconnects.
	 *
	 * @param int $post_id Liveblog post ID.
	 * @return void
	 */
	private static function publish_change( $post_id ) {
		$payload = array(
			'version' => 1,
			'event'   => 'liveblog.changed',
			'room'    => 'liveblog:' . $post_id,
			'post_id' => (int) $post_id,
			'sent_at' => microtime( true ),
		);

		/**
		 * Filters the Redis event envelope before publication.
		 *
		 * Keep the envelope small: clients use it only as an invalidation signal
		 * and then fetch authoritative state from Automattic Liveblog.
		 *
		 * @param array $payload Event envelope.
		 * @param int   $post_id Liveblog post ID.
		 */
		$payload = apply_filters( 'tagdiv_liveblog_realtime_event', $payload, $post_id );
		$json    = wp_json_encode( $payload );

		if ( ! is_string( $json ) || '' === $json ) {
			return;
		}

		$redis = null;

		try {
			$redis = new Redis();
			$ok    = @$redis->connect( self::redis_host(), self::redis_port(), self::redis_timeout() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Optional best-effort localhost transport.

			if ( ! $ok ) {
				throw new RuntimeException( 'Unable to connect to the realtime Redis endpoint.' );
			}

			self::authenticate_redis( $redis );
			$redis->publish( self::redis_channel(), $json );
		} catch ( Throwable $exception ) {
			/**
			 * Fires when optional realtime publication fails.
			 *
			 * @param string $message Error message.
			 * @param int    $post_id Liveblog post ID.
			 */
			do_action( 'tagdiv_liveblog_realtime_publish_error', $exception->getMessage(), $post_id );
		} finally {
			if ( $redis instanceof Redis ) {
				try {
					$redis->close();
				} catch ( Throwable $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Best-effort close.
					// Connection is already unusable; there is nothing else to clean up.
				}
			}
		}
	}

	/**
	 * Authenticate a Redis connection when credentials are configured.
	 *
	 * @param Redis $redis Connected Redis client.
	 * @return void
	 */
	private static function authenticate_redis( $redis ) {
		$password = defined( 'TAGDIV_LIVEBLOG_REALTIME_REDIS_PASSWORD' ) ? (string) TAGDIV_LIVEBLOG_REALTIME_REDIS_PASSWORD : '';
		$username = defined( 'TAGDIV_LIVEBLOG_REALTIME_REDIS_USERNAME' ) ? (string) TAGDIV_LIVEBLOG_REALTIME_REDIS_USERNAME : '';

		if ( '' === $password ) {
			return;
		}

		if ( '' !== $username ) {
			$redis->auth( array( $username, $password ) );
			return;
		}

		$redis->auth( $password );
	}

	/**
	 * Whether a post is both publicly viewable and actively liveblogging.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function is_public_active_liveblog( $post_id ) {
		if ( $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== get_post_status( $post ) ) {
			return false;
		}

		if ( function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $post ) ) {
			return false;
		}

		$key   = class_exists( 'WPCOM_Liveblog' ) ? WPCOM_Liveblog::KEY : 'liveblog';
		$state = get_post_meta( $post_id, $key, true );

		if ( 1 === $state || '1' === $state ) {
			$state = 'enable';
		}

		return 'enable' === $state;
	}

	/**
	 * Public Socket.IO origin.
	 *
	 * @return string
	 */
	private static function socket_origin() {
		$configured = defined( 'TAGDIV_LIVEBLOG_REALTIME_URL' ) ? (string) TAGDIV_LIVEBLOG_REALTIME_URL : home_url( '/' );
		$parts      = wp_parse_url( $configured );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			$parts = wp_parse_url( home_url( '/' ) );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$scheme = in_array( $scheme, array( 'http', 'https' ), true ) ? $scheme : 'https';
		$host   = isset( $parts['host'] ) ? $parts['host'] : '';
		$port   = isset( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '';

		return $scheme . '://' . $host . $port;
	}

	/**
	 * Socket.IO HTTP path.
	 *
	 * @return string
	 */
	private static function socket_path() {
		$path = defined( 'TAGDIV_LIVEBLOG_REALTIME_PATH' ) ? (string) TAGDIV_LIVEBLOG_REALTIME_PATH : self::DEFAULT_SOCKET_PATH;
		$path = '/' . trim( $path, '/' ) . '/';

		return '/' === $path ? self::DEFAULT_SOCKET_PATH : $path;
	}

	/**
	 * Browser client bundle URL served by Socket.IO itself.
	 *
	 * @return string
	 */
	private static function socket_client_url() {
		return untrailingslashit( self::socket_origin() ) . self::socket_path() . 'socket.io.js';
	}

	/**
	 * Redis host.
	 *
	 * @return string
	 */
	private static function redis_host() {
		return defined( 'TAGDIV_LIVEBLOG_REALTIME_REDIS_HOST' ) ? (string) TAGDIV_LIVEBLOG_REALTIME_REDIS_HOST : '127.0.0.1';
	}

	/**
	 * Redis port.
	 *
	 * @return int
	 */
	private static function redis_port() {
		$port = defined( 'TAGDIV_LIVEBLOG_REALTIME_REDIS_PORT' ) ? absint( TAGDIV_LIVEBLOG_REALTIME_REDIS_PORT ) : 6379;
		return $port > 0 ? $port : 6379;
	}

	/**
	 * Redis Pub/Sub channel.
	 *
	 * @return string
	 */
	private static function redis_channel() {
		$channel = defined( 'TAGDIV_LIVEBLOG_REALTIME_REDIS_CHANNEL' ) ? (string) TAGDIV_LIVEBLOG_REALTIME_REDIS_CHANNEL : self::DEFAULT_REDIS_CHANNEL;
		$channel = trim( $channel );

		return '' !== $channel ? $channel : self::DEFAULT_REDIS_CHANNEL;
	}

	/**
	 * Short Redis connect timeout in seconds.
	 *
	 * @return float
	 */
	private static function redis_timeout() {
		$timeout = defined( 'TAGDIV_LIVEBLOG_REALTIME_REDIS_TIMEOUT' ) ? (float) TAGDIV_LIVEBLOG_REALTIME_REDIS_TIMEOUT : 0.2;
		return $timeout > 0 ? $timeout : 0.2;
	}

	/**
	 * Browser reconciliation timeout in milliseconds.
	 *
	 * @return int
	 */
	private static function reconcile_timeout_ms() {
		$timeout = defined( 'TAGDIV_LIVEBLOG_REALTIME_RECONCILE_TIMEOUT' ) ? absint( TAGDIV_LIVEBLOG_REALTIME_RECONCILE_TIMEOUT ) : 8000;
		return $timeout >= 1000 ? $timeout : 8000;
	}
}
