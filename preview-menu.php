<?php
/**
 * @author  Jory Hogeveen <info@keraweb.nl>
 * @package Preview_Menu
 * @since   0.1
 * @version 0.2
 * @licence GPL-2.0+
 * @link    https://github.com/JoryHogeveen/preview-menu/
 *
 * @wordpress-plugin
 * Plugin Name:       Preview Menu
 * Plugin URI:        https://github.com/JoryHogeveen/preview-menu
 * Description:       Preview menu's on selected locations.
 * Version:           0.2
 * Author:            Jory Hogeveen
 * Author URI:        https://www.keraweb.nl
 * Text Domain:       preview-menu
 * Domain Path:       /languages/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/JoryHogeveen/preview-menu
 *
 * @copyright 2018-2019 Jory Hogeveen
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * ( at your option ) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

Preview_Menu::get_instance();

class Preview_Menu
{
	/**
	 * The registered menu locations.
	 * @var    array
	 * @since  0.1
	 */
	private $menu_locations = array();

	/**
	 * The default menu location.
	 * @var    string
	 * @since  0.2
	 */
	private $default_location = '';

	/**
	 * The current menu location.
	 * @var    string
	 * @since  0.2
	 */
	private $current_location = '';

	/**
	 * The capability required to access this plugin.
	 * @var    string
	 * @since  0.1.1
	 */
	private $capability = '';

	/**
	 * Preview menu value.
	 * @var    mixed
	 * @since  0.2
	 */
	private $preview_menu = null;

	/**
	 * Preview menu db option.
	 * @var    string
	 * @since  0.2
	 */
	private $option = 'preview_menu';

	/**
	 * Nonce.
	 * @var    string
	 * @since  0.2
	 */
	private $nonce = 'preview_menu';

	/**
	 * The single instance of the class.
	 *
	 * @var    \Preview_Menu
	 * @since  0.1
	 */
	protected static $_instance = null;

	/**
	 * Class Instance.
	 * Ensures only one instance of this class is loaded or can be loaded.
	 *
	 * @since   0.1
	 * @static
	 * @return  \Preview_Menu
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Class constructor.
	 * @since  0.1
	 * @access private
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );
	}

	/**
	 * Init plugin.
	 * @since  0.1
	 */
	public function action_plugins_loaded() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$this->capability = apply_filters( 'preview_menu_capability', $this->capability );

		if ( ! current_user_can( $this->capability ) ) {
			return;
		}

		add_action( 'wp_loaded', array( $this, 'wp_loaded' ), 1 ); // Before Polylang.

		if ( ! is_admin() ) {
			$this->front();
		} else {
			add_action( 'admin_init', array( $this, 'admin_init' ), 1 );
		}
	}

	/**
	 * Store data after WP is loaded.
	 * @since  0.2
	 */
	public function wp_loaded() {

		// Store menu location before Polylang modifications.
		$this->menu_locations = get_registered_nav_menus();

		foreach ( $this->menu_locations as $location => $info ) {
			$this->default_location = $location;
			break;
		}
	}

	/**
	 * Plugin admin.
	 * @since  0.1
	 */
	public function admin_init() {

		if ( apply_filters( 'preview_menu_enable_meta_box', true ) ) {
			$this->add_meta_box();
			add_filter( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'wp_ajax_preview_menu', array( $this, 'ajax' ) );
		}
	}

	/**
	 * Plugin frontend.
	 * @since  0.1
	 */
	public function front() {
		if ( ! empty( $_GET['preview_menu'] ) ) {
			$this->preview_menu = $_GET['preview_menu'];
		}

		/**
		 * Option to switch to another menu preview for users who can manage menu's.
		 */
		if ( $this->preview_menu ) {
			if ( is_numeric( $this->preview_menu ) && 1 === (int) $this->preview_menu ) {
				$this->preview_menu = get_option( $this->option );
				add_filter( 'wp_get_nav_menu_items', array( $this, 'filter_front_wp_get_nav_menu_items' ), 1, 3 );
			}
			add_filter( 'wp_nav_menu_args', array( $this, 'filter_front_wp_nav_menu_args' ), 1 );

			$this->default_location = apply_filters( 'preview_menu_default_location', $this->default_location );
		}
	}

	/**
	 * Change menu.
	 * @since  0.1
	 * @param  array  $args
	 * @return array
	 */
	public function filter_front_wp_nav_menu_args( $args ) {

		$location = $this->default_location;

		if ( isset( $_GET['preview_menu_location'] ) ) {
			$location = $_GET['preview_menu_location'];
		}

		$this->current_location = $args['theme_location'];

		if ( $location !== $this->current_location || ! is_scalar( $this->preview_menu ) ) {
			return $args;
		}

		$args['menu'] = $this->preview_menu;

		return $args;
	}

	/**
	 * Change menu items.
	 * @since  0.2
	 * @param  object[] $items
	 * @param  object   $menu
	 * @param  array    $args
	 * @return object[] mixed
	 */
	public function filter_front_wp_get_nav_menu_items( $items, $menu, $args ) {

		$location               = $this->default_location;
		$current_location       = $this->current_location;
		$this->current_location = ''; // Reset.

		if ( isset( $_GET['preview_menu_location'] ) ) {
			$location = $_GET['preview_menu_location'];
		}

		// Replace menu based on location.
		if ( $location && $location === $current_location ) {
			return $this->get_preview_menu_items( $args );
		}
		// No location given, replace the menu on it's current location.
		if (
			! empty( $menu->term_id )
			&& ! empty( $this->preview_menu['menu'] )
			&& (int) $menu->term_id === (int) $this->preview_menu['menu']
		) {
			return $this->get_preview_menu_items( $args );
		}

		return $items;
	}

	/**
	 * Get menu items.
	 * @since  0.2
	 * @param  array    $args
	 * @return object[]|array[] mixed
	 */
	public function get_preview_menu_items( $args ) {
		static $items;

		if ( ! $items ) {

			if ( empty( $this->preview_menu['items'] ) ) {
				return array();
			}

			foreach ( $this->preview_menu['items'] as $key => $item ) {
				foreach ( $item as $meta => $value ) {
					$underscore_meta = str_replace( '-', '_', $meta );
					$private_meta    = '_' . $underscore_meta;

					// Store metadata alias.
					$item[ $underscore_meta ] = $value;
					$item[ $private_meta ]    = $value;
				}
				$item['ID']               = $key;
				$item['post_type']        = 'nav_menu_item';
				$item['post_title']       = $item['menu_item_title'];
				$item['menu_item_parent'] = $item['menu_item_parent_id'];
				$item['object_id']        = $item['menu_item_object_id'];
				$item['object']           = $item['menu_item_object'];
				$item['type']             = $item['menu_item_type'];

				$this->preview_menu['items'][ $key ] = new WP_Post( (object) $item );
				$this->preview_menu['items'][ $key ] = wp_setup_nav_menu_item( $this->preview_menu['items'][ $key ] );
			}

			$items = $this->preview_menu['items'];
		}

		// @see wp_get_nav_menu_items().
		if ( ARRAY_A === $args['output'] ) {
			$items = wp_list_sort( $items, array(
				$args['output_key'] => 'ASC',
			) );

			$i = 1;
			foreach ( $items as $k => $item ) {
				$items[ $k ]->{ $args['output_key'] } = $i++;
			}
		}

		return $items;
	}

	/**
	 * @param  null    $null
	 * @param  int     $id
	 * @param  string  $key
	 * @return mixed
	 */
	public function filter_front_get_nav_metadata( $null, $id, $key ) {
		if ( ! empty( $this->preview_menu['items'][ $id ]->{ $key } ) ) {
			return $this->preview_menu['items'][ $id ]->{ $key };
		}
		return $null;
	}

	/**
	 * Enqueue scripts.
	 * @since  0.2
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();
		if ( empty( $screen ) ) {
			return;
		}

		$version = defined( 'WP_DEBUG' ) && WP_DEBUG ? '0.2' : time();
		$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( 'nav-menus' === $screen->base ) {
			wp_enqueue_script(
				'preview-menu',
				plugins_url( '/preview-menu' . $suffix . '.js', __FILE__ ),
				array( 'jquery', 'jquery-serialize-object' ),
				$version,
				true
			);
			wp_localize_script(
				'preview-menu',
				'PreviewMenu',
				array(
					'_nonce' => wp_create_nonce( $this->nonce ),
				)
			);
		}
	}

	/**
	 * Store preview menu.
	 * @since  0.2
	 */
	public function ajax() {
		if (
			empty( $_POST['_nonce'] )
			|| empty( $_POST['preview_menu'] )
			|| ! wp_verify_nonce( $_POST['_nonce'], $this->nonce )
		) {
			die;
		}

		$data = $_POST['preview_menu'];

		if ( empty( $data['items'] ) || empty( $data['menu'] ) ) {
			die;
		}

		$data['items'] = $this->parse_post_input( json_decode( stripcslashes( html_entity_decode( $data['items'] ) ), true ) );

		$items = array();
		foreach ( $data['items'] as $field => $values ) {
			foreach ( $values as $id => $value ) {
				if ( empty( $items[ $id ] ) ) {
					$items[ $id ] = array();
				}
				$items[ $id ][ $field ] = $value;
			}
		}

		$option = array(
			'menu'  => $data['menu'],
			'items' => $items,
		);

		update_option( $this->option, $option );

		wp_send_json_success( $option );
	}

	/**
	 * Convert multilevel post input data to arrays.
	 * @param  array  $data
	 * @return array
	 */
	public function parse_post_input( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		foreach ( $data as $key => $val ) {
			if ( is_array( $val ) ) {
				$val = $this->parse_post_input( $val );
			}
			if ( false !== strpos( $key, '[' ) ) {
				$parts = str_replace( ']', '', $key );
				$parts = explode( '[', $parts );
				$parts = array_reverse( $parts );
				$new   = array();
				foreach ( $parts as $p ) {
					if ( empty( $new ) ) {
						$new = array( $p => $val );
					} else {
						$new = array( $p => $new );
					}
				}
				$val = $new;
			} else {
				$val = array( $key => $val );
			}
			unset( $data[ $key ] );
			$data = array_replace_recursive( $data, $val );
		}
		return $data;
	}

	/**
	 * Add the meta box to the menu options.
	 * @since  0.1
	 */
	public function add_meta_box() {
		add_meta_box(
			'preview-menu',
			esc_html__( 'Preview Menu', 'preview-menu' ),
			array( $this, 'meta_box' ),
			'nav-menus',
			'side',
			'high'
		);
	}

	/**
	 * Meta box HTML.
	 * @since  0.1
	 */
	public function meta_box() {
		//global $nav_menu_selected_id;
		$url = trailingslashit( get_bloginfo( 'url' ) );
		//$url = add_query_arg( 'preview_menu', $nav_menu_selected_id, $url );

		?>
		<label for="preview_menu_url"><?php esc_html_e( 'URL', 'preview-menu' ) ?></label>
		<input type="url" name="preview_menu_url" id="preview_menu_url" class="widefat" value="<?php echo $url; ?>">
		<label for="preview_menu_location"><?php esc_html_e( 'Select location', 'preview-menu' ) ?></label>
		<select name="preview_menu_location" id="preview_menu_location" class="widefat">
			<option value="0">- <?php esc_html_e( 'Current menu location(s)', 'preview-menu' ) ?> -</option>
			<?php
			foreach ( $this->menu_locations as $location => $name ) {
				?>
				<option value="<?php echo $location; ?>"><?php echo esc_html( $name ) ?></option>
				<?php
			}
			?>
		</select>
		<p class="button-controls wp-clearfix">
			<span class="add-to-menu">
				<button id="preview_menu_btn" class="button button-primary right"><?php esc_html_e( 'Preview Menu', 'preview-menu' ) ?></button>
				<span class="spinner"></span>
			</span>
		</p>
		<?php
	}

}
