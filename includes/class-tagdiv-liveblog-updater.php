<?php
/**
 * GitHub Releases update provider for tagDiv Liveblog.
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tagdiv_Liveblog_Updater {
	const UPDATE_URI      = 'https://github.com/lstamellos/tagdiv-liveblog';
	const API_BASE        = 'https://api.github.com/repos/lstamellos/tagdiv-liveblog/releases';
	const API_URL         = self::API_BASE . '/latest';
	const PLUGIN_SLUG     = 'tagdiv-liveblog';
	const PLUGIN_BASENAME = 'tagdiv-liveblog/tagdiv-liveblog.php';
	const CACHE_KEY       = 'tdlb_github_latest_release';
	const CACHE_TTL       = 21600;

	/**
	 * Register update provider hooks.
	 *
	 * Automatic updates are deliberately not forced here. WordPress Core remains
	 * authoritative through the user's normal per-plugin auto-update setting.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'filter_update' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'filter_plugin_information' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'verify_release_package' ), 20, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_upgrade' ), 10, 2 );
	}

	/**
	 * Supply update metadata for this plugin's Update URI host.
	 *
	 * Always return valid release metadata when GitHub lookup succeeds, including
	 * when the installed version is already current. WordPress Core compares the
	 * returned version with the installed version and stores current releases in
	 * the `no_update` response. That metadata is required for Core to recognise
	 * this third-party plugin as update-supported and expose the native per-plugin
	 * automatic-update control.
	 *
	 * Do not set the optional `autoupdate` response key. Core will therefore use
	 * the user's native per-plugin auto-update preference unchanged.
	 *
	 * @param array|false $update      Existing update response.
	 * @param array       $plugin_data Parsed plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @param string[]    $locales     Installed locales.
	 * @return array|false
	 */
	public static function filter_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $plugin_data, $locales );

		if ( self::PLUGIN_BASENAME !== $plugin_file ) {
			return $update;
		}

		$release = self::get_latest_release();
		if ( is_wp_error( $release ) || empty( $release['version'] ) ) {
			return $update;
		}

		return array(
			'id'           => self::UPDATE_URI,
			'slug'         => self::PLUGIN_SLUG,
			'version'      => $release['version'],
			'url'          => $release['html_url'],
			'package'      => $release['package'],
			'requires_php' => '7.4',
		);
	}

	/**
	 * Provide the native "View version details" modal from GitHub release data.
	 *
	 * @param false|object|array $result Existing plugin API result.
	 * @param string             $action API action.
	 * @param object             $args   API arguments.
	 * @return false|object|array
	 */
	public static function filter_plugin_information( $result, $action, $args ) {
		if (
			'plugin_information' !== $action
			|| ! is_object( $args )
			|| empty( $args->slug )
			|| self::PLUGIN_SLUG !== $args->slug
		) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( is_wp_error( $release ) ) {
			return $result;
		}

		$changelog = '';
		if ( ! empty( $release['body'] ) ) {
			$changelog = '<pre style="white-space:pre-wrap">' . esc_html( $release['body'] ) . '</pre>';
		}

		return (object) array(
			'name'          => 'tagDiv Liveblog',
			'slug'          => self::PLUGIN_SLUG,
			'version'       => $release['version'],
			'author'        => 'Loukas Stamellos',
			'homepage'      => self::UPDATE_URI,
			'requires'      => '6.4',
			'requires_php'  => '7.4',
			'download_link' => $release['package'],
			'last_updated'  => $release['published_at'],
			'sections'      => array(
				'description' => __( 'Newspaper / tagDiv Composer adapter for Automattic Liveblog.', 'tagdiv-liveblog' ),
				'changelog'   => $changelog,
			),
		);
	}

	/**
	 * Verify the SHA-256 digest published by GitHub before Core installs our ZIP.
	 *
	 * Verification is bound to the version encoded in the exact official release
	 * URL. This remains correct even if a newer release is published between the
	 * WordPress update check and the later package download.
	 *
	 * @param false|string|WP_Error $reply      Existing short-circuit result.
	 * @param string                $package    Package URL.
	 * @param WP_Upgrader           $upgrader   Upgrader instance.
	 * @param array                 $hook_extra Upgrader context.
	 * @return false|string|WP_Error
	 */
	public static function verify_release_package( $reply, $package, $upgrader, $hook_extra ) {
		unset( $upgrader, $hook_extra );

		if ( false !== $reply || ! is_string( $package ) || '' === $package ) {
			return $reply;
		}

		$version = self::version_from_package_url( $package );
		if ( false === $version ) {
			return false;
		}

		$release = self::get_release_by_version( $version );
		if ( is_wp_error( $release ) ) {
			return $release;
		}

		if ( $package !== $release['package'] || empty( $release['sha256'] ) ) {
			return new WP_Error(
				'tdlb_update_release_mismatch',
				__( 'The tagDiv Liveblog update was not installed because the package does not match the verified GitHub release asset.', 'tagdiv-liveblog' )
			);
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp_file = download_url( $package, 300 );
		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		$actual = hash_file( 'sha256', $tmp_file );
		if ( ! is_string( $actual ) || ! hash_equals( $release['sha256'], strtolower( $actual ) ) ) {
			wp_delete_file( $tmp_file );

			return new WP_Error(
				'tdlb_update_checksum_mismatch',
				__( 'The tagDiv Liveblog update was not installed because the downloaded ZIP failed SHA-256 verification.', 'tagdiv-liveblog' )
			);
		}

		return $tmp_file;
	}

	/**
	 * Extract a stable version from this repository's exact release-asset URL.
	 *
	 * @param string $package Package URL.
	 * @return string|false
	 */
	private static function version_from_package_url( $package ) {
		$pattern = '#^https://github\.com/lstamellos/tagdiv-liveblog/releases/download/v(\d+\.\d+\.\d+)/tagdiv-liveblog-\1\.zip$#';

		if ( ! preg_match( $pattern, $package, $matches ) ) {
			return false;
		}

		return $matches[1];
	}

	/**
	 * Clear cached release metadata after this plugin is upgraded.
	 *
	 * @param WP_Upgrader $upgrader   Upgrader instance.
	 * @param array       $hook_extra Upgrader context.
	 * @return void
	 */
	public static function clear_cache_after_upgrade( $upgrader, $hook_extra ) {
		unset( $upgrader );

		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return;
		}

		$plugins = array();
		if ( ! empty( $hook_extra['plugin'] ) ) {
			$plugins[] = $hook_extra['plugin'];
		}
		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$plugins = array_merge( $plugins, $hook_extra['plugins'] );
		}

		if ( in_array( self::PLUGIN_BASENAME, $plugins, true ) ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Get and cache the latest stable GitHub release.
	 *
	 * @param bool $force_refresh Ignore the cached response.
	 * @return array|WP_Error
	 */
	public static function get_latest_release( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_site_transient( self::CACHE_KEY );
			if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
				return $cached;
			}
		}

		$release = self::request_release( self::API_URL );
		if ( ! is_wp_error( $release ) ) {
			set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
		}

		return $release;
	}

	/**
	 * Fetch one stable release by exact semantic version.
	 *
	 * @param string $version Stable semantic version.
	 * @return array|WP_Error
	 */
	private static function get_release_by_version( $version ) {
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			return new WP_Error( 'tdlb_github_release_version', __( 'Invalid tagDiv Liveblog release version.', 'tagdiv-liveblog' ) );
		}

		$cache_key = 'tdlb_github_release_' . md5( $version );
		$cached    = get_site_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['version'] ) && $version === $cached['version'] ) {
			return $cached;
		}

		$release = self::request_release( self::API_BASE . '/tags/v' . rawurlencode( $version ), $version );
		if ( ! is_wp_error( $release ) ) {
			set_site_transient( $cache_key, $release, self::CACHE_TTL );
		}

		return $release;
	}

	/**
	 * Request and normalize one GitHub release.
	 *
	 * Only a published, non-prerelease release with the exact installable asset
	 * name and a GitHub-provided SHA-256 digest is accepted.
	 *
	 * @param string       $url              GitHub release API URL.
	 * @param string|false $expected_version Optional exact semantic version.
	 * @return array|WP_Error
	 */
	private static function request_release( $url, $expected_version = false ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 3,
				'headers'     => array(
					'Accept'               => 'application/vnd.github+json',
					'User-Agent'           => 'tagdiv-liveblog/' . TAGDIV_LIVEBLOG_VERSION,
					'X-GitHub-Api-Version' => '2026-03-10',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new WP_Error(
				'tdlb_github_release_http',
				sprintf(
					/* translators: %d: HTTP response status. */
					__( 'GitHub release lookup returned HTTP %d.', 'tagdiv-liveblog' ),
					$status
				)
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return new WP_Error( 'tdlb_github_release_invalid', __( 'GitHub returned invalid release metadata.', 'tagdiv-liveblog' ) );
		}

		if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return new WP_Error( 'tdlb_github_release_unstable', __( 'The GitHub release is not a stable published release.', 'tagdiv-liveblog' ) );
		}

		$version = ltrim( trim( (string) $data['tag_name'] ), 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			return new WP_Error( 'tdlb_github_release_version', __( 'The GitHub release tag is not a stable semantic version.', 'tagdiv-liveblog' ) );
		}

		if ( false !== $expected_version && $version !== $expected_version ) {
			return new WP_Error( 'tdlb_github_release_version_mismatch', __( 'GitHub returned a different tagDiv Liveblog version than requested.', 'tagdiv-liveblog' ) );
		}

		$expected_asset = 'tagdiv-liveblog-' . $version . '.zip';
		$package        = '';
		$sha256         = '';

		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if (
					! is_array( $asset )
					|| empty( $asset['name'] )
					|| $expected_asset !== $asset['name']
					|| empty( $asset['browser_download_url'] )
					|| ( isset( $asset['state'] ) && 'uploaded' !== $asset['state'] )
				) {
					continue;
				}

				$package = esc_url_raw( $asset['browser_download_url'] );

				$digest = isset( $asset['digest'] ) ? strtolower( trim( (string) $asset['digest'] ) ) : '';
				if ( preg_match( '/^sha256:([a-f0-9]{64})$/', $digest, $matches ) ) {
					$sha256 = $matches[1];
				}

				break;
			}
		}

		if ( '' === $package || '' === $sha256 ) {
			return new WP_Error(
				'tdlb_github_release_asset',
				__( 'The GitHub release does not contain the expected verified tagDiv Liveblog ZIP asset.', 'tagdiv-liveblog' )
			);
		}

		return array(
			'version'      => $version,
			'package'      => $package,
			'sha256'       => $sha256,
			'html_url'     => ! empty( $data['html_url'] ) ? esc_url_raw( $data['html_url'] ) : self::UPDATE_URI,
			'body'         => isset( $data['body'] ) ? (string) $data['body'] : '',
			'published_at' => isset( $data['published_at'] ) ? sanitize_text_field( $data['published_at'] ) : '',
		);
	}
}
