<?php
/**
 * Google Drive test block.
 *
 * @link          https://wpmudev.com/
 * @since         1.0.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2025, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\App\Admin_Pages;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;

/**
 * Google Drive admin page class.
 */
class Google_Drive extends Base {
	/**
	 * The page title.
	 *
	 * @var string
	 */
	private $page_title;

	/**
	 * The menu title.
	 *
	 * @var string
	 */
	private $menu_title;

	/**
	 * The page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'wpmudev_plugintest_drive';

	/**
	 * Google Drive auth credentials.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $creds = array();

	/**
	 * Option name for credentials (reusing the same as original auth).
	 *
	 * @var string
	 */
	private $option_name = 'wpmudev_plugin_tests_auth';

	/**
	 * Page Assets.
	 *
	 * @var array
	 */
	private $page_scripts = array();

	/**
	 * Assets version.
	 *
	 * @var string
	 */
	private $assets_version = '';

	/**
	 * A unique string id to be used in markup and jsx.
	 *
	 * @var string
	 */
	private $unique_id = '';

	/**
	 * Current screen ID.
	 *
	 * @var string
	 */
	private $screen_id = '';

	/**
	 * Initializes the page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		$this->page_title     = __( 'Google Drive Test', 'wpmudev-plugin-test' );
		$this->menu_title     = __( 'Google Drive Test', 'wpmudev-plugin-test' );
		$this->creds          = get_option( $this->option_name, array() );
		$this->assets_version = ! empty( $this->script_data( 'version' ) ) ? $this->script_data( 'version' ) : WPMUDEV_PLUGINTEST_VERSION;
		$this->unique_id      = "wpmudev_plugintest_drive_main_wrap-" . sanitize_html_class( $this->assets_version );

		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		// Add body class to admin pages.
		add_filter( 'admin_body_class', array( $this, 'admin_body_classes' ) );
	}

	/**
	 * Register admin page.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		$this->screen_id = add_menu_page(
			$this->page_title,
			$this->menu_title,
			'manage_options',
			$this->page_slug,
			array( $this, 'callback' ),
			'dashicons-cloud',
			7
		);

		add_action( 'load-' . $this->screen_id, array( $this, 'prepare_assets' ) );
	}

	/**
	 * The admin page callback method.
	 *
	 * @return void
	 */
	public function callback() {
		// Check capabilities again for security.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpmudev-plugin-test' ) );
		}

		$this->view();
	}

	/**
	 * Prepares assets.
	 *
	 * @return void
	 */
	public function prepare_assets() {
		if ( ! is_array( $this->page_scripts ) ) {
			$this->page_scripts = array();
		}

		$handle       = 'wpmudev_plugintest_drivepage';
		$src          = WPMUDEV_PLUGINTEST_ASSETS_URL . '/js/drivetestpage.min.js';
		$style_src    = WPMUDEV_PLUGINTEST_ASSETS_URL . '/css/drivetestpage.min.css';
		
		// Check if assets exist before registering
		$js_file_path  = WPMUDEV_PLUGINTEST_DIR . 'assets/js/drivetestpage.min.js';
		$css_file_path = WPMUDEV_PLUGINTEST_DIR . 'assets/css/drivetestpage.min.css';
		
		if ( ! file_exists( $js_file_path ) ) {
			error_log( 'Google Drive Test: JavaScript file not found: ' . $js_file_path );
			return;
		}

		$dependencies = $this->get_script_dependencies();

		$this->page_scripts[ $handle ] = array(
			'src'          => $src,
			'style_src'    => file_exists( $css_file_path ) ? $style_src : '',
			'deps'         => $dependencies,
			'ver'          => $this->assets_version,
			'strategy'     => 'defer',
			'in_footer'    => true,
			'localize'     => array(
				'dom_element_id'       => $this->unique_id,
				'restEndpointSave'     => rest_url( 'wpmudev/v1/drive/save-credentials' ),
				'restEndpointAuth'     => rest_url( 'wpmudev/v1/drive/auth' ),
				'restEndpointFiles'    => rest_url( 'wpmudev/v1/drive/files' ),
				'restEndpointUpload'   => rest_url( 'wpmudev/v1/drive/upload' ),
				'restEndpointDownload' => rest_url( 'wpmudev/v1/drive/download' ),
				'restEndpointCreate'   => rest_url( 'wpmudev/v1/drive/create-folder' ),
				'nonce'                => wp_create_nonce( 'wp_rest' ),
				'authStatus'           => $this->get_auth_status(),
				'redirectUri'          => home_url( '/wp-json/wpmudev/v1/drive/callback' ),
				'hasCredentials'       => ! empty( $this->creds['client_id'] ) && ! empty( $this->creds['client_secret'] ),
				'i18n'                 => array(
					'error' => __( 'An error occurred. Please try again.', 'wpmudev-plugin-test' ),
				),
			),
		);
	}

	/**
	 * Get script dependencies.
	 *
	 * @return array
	 */
	private function get_script_dependencies(): array {
		$dependencies = ! empty( $this->script_data( 'dependencies' ) )
			? $this->script_data( 'dependencies' )
			: array(
				'react',
				'react-dom',
				'wp-element',
				'wp-i18n',
				'wp-is-shallow-equal',
				'wp-polyfill',
				'wp-api-fetch',
			);

		return array_map( 'sanitize_key', $dependencies );
	}

	/**
	 * Checks if user is authenticated with Google Drive.
	 *
	 * @return bool
	 */
	private function get_auth_status(): bool {
		$access_token = get_option( 'wpmudev_drive_access_token', '' );
		
		if ( empty( $access_token ) || ! is_array( $access_token ) ) {
			return false;
		}

		// Check if token is expired
		$expires_at = get_option( 'wpmudev_drive_token_expires', 0 );
		
		if ( time() >= $expires_at ) {
			// Token is expired, check if we can refresh it
			$refresh_token = get_option( 'wpmudev_drive_refresh_token', '' );
			return ! empty( $refresh_token );
		}

		return true;
	}

	/**
	 * Gets assets data for given key.
	 *
	 * @param string $key The data key to retrieve.
	 *
	 * @return string|array
	 */
	protected function script_data( string $key = '' ) {
		$raw_script_data = $this->raw_script_data();

		if ( empty( $key ) ) {
			return $raw_script_data;
		}

		return $raw_script_data[ $key ] ?? '';
	}

	/**
	 * Gets the script data from assets php file.
	 *
	 * @return array
	 */
	protected function raw_script_data(): array {
		static $script_data = null;

		if ( is_null( $script_data ) ) {
			$asset_file = WPMUDEV_PLUGINTEST_DIR . 'assets/js/drivetestpage.min.asset.php';
			
			if ( file_exists( $asset_file ) ) {
				$script_data = include $asset_file;
			} else {
				error_log( 'Google Drive Test: Asset file not found: ' . $asset_file );
				$script_data = array(
					'dependencies' => array(
						'react',
						'react-dom',
						'wp-element',
						'wp-i18n',
						'wp-is-shallow-equal',
						'wp-polyfill',
						'wp-api-fetch',
					),
					'version'     => WPMUDEV_PLUGINTEST_VERSION,
				);
			}
		}

		return (array) $script_data;
	}

	/**
	 * Enqueues assets.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only load assets on our specific admin page
		if ( $hook !== $this->screen_id ) {
			return;
		}

		if ( ! empty( $this->page_scripts ) ) {
			foreach ( $this->page_scripts as $handle => $page_script ) {
				// Register and enqueue script
				wp_register_script(
					$handle,
					esc_url( $page_script['src'] ),
					$page_script['deps'],
					$page_script['ver'],
					array(
						'strategy'  => $page_script['strategy'] ?? false,
						'in_footer' => $page_script['in_footer'] ?? false,
					)
				);

				if ( ! empty( $page_script['localize'] ) ) {
					wp_localize_script( 
						$handle, 
						'wpmudevDriveTest', 
						$this->sanitize_localize_data( $page_script['localize'] ) 
					);
				}

				wp_enqueue_script( $handle );

				// Set translation domain
				if ( function_exists( 'wp_set_script_translations' ) ) {
					wp_set_script_translations( $handle, 'wpmudev-plugin-test' );
				}

				// Enqueue style if exists
				if ( ! empty( $page_script['style_src'] ) ) {
					wp_enqueue_style( 
						$handle, 
						esc_url( $page_script['style_src'] ), 
						array(), 
						$this->assets_version 
					);
				}
			}
		}
	}

	/**
	 * Sanitize localize data for security.
	 *
	 * @param array $data The data to sanitize.
	 * @return array
	 */
	private function sanitize_localize_data( array $data ): array {
		$sanitized = array();
		
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$sanitized[ sanitize_key( $key ) ] = $this->sanitize_localize_data( $value );
			} elseif ( is_string( $value ) ) {
				$sanitized[ sanitize_key( $key ) ] = sanitize_text_field( $value );
			} else {
				$sanitized[ sanitize_key( $key ) ] = $value;
			}
		}
		
		return $sanitized;
	}

	/**
	 * Prints the wrapper element which React will use as root.
	 *
	 * @return void
	 */
	protected function view() {
		?>
		<div class="wrap">
			<div id="<?php echo esc_attr( $this->unique_id ); ?>" class="sui-wrap">
				<div class="sui-box">
					<div class="sui-box-header">
						<h1 class="sui-box-title"><?php echo esc_html( $this->page_title ); ?></h1>
					</div>
					<div class="sui-box-body">
						<div id="<?php echo esc_attr( $this->unique_id ); ?>-content">
							<!-- React app will render here -->
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Adds the SUI class on markup body.
	 *
	 * @param string $classes Existing body classes.
	 * @return string
	 */
	public function admin_body_classes( $classes = '' ) {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $classes;
		}

		$current_screen = get_current_screen();

		if ( empty( $current_screen->id ) || $current_screen->id !== $this->screen_id ) {
			return $classes;
		}

		$classes .= ' sui-' . str_replace( '.', '-', WPMUDEV_PLUGINTEST_SUI_VERSION ) . ' ';
		$classes .= ' wpmudev-google-drive-test ';

		return $classes;
	}
}