<?php
/**
 * @author  Jory Hogeveen <info@keraweb.nl>
 * @package Preview_Menu
 * @since   0.1
 * @version 0.1.1
 * @licence GPL-2.0+
 * @link    https://github.com/JoryHogeveen/preview-menu/
 *
 * @wordpress-plugin
 * Plugin Name:       Preview Menu
 * Plugin URI:        https://github.com/JoryHogeveen/preview-menu
 * Description:       Preview menu's on selected locations.
 * Version:           0.1.1
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
	 * The capability required to access this plugin.
	 * @var    string
	 * @since  0.1.1
	 */
	private $capability = '';

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

		if ( ! is_admin() ) {
			$this->front();
		}

		if ( ! current_user_can( $this->capability ) ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'admin_init' ), 1 ); // Before Polylang.
	}

	/**
	 * Plugin admin.
	 * @since  0.1
	 */
	public function admin_init() {

		// Store menu location before Polylang modifications.
		$this->menu_locations = get_registered_nav_menus();

		if ( apply_filters( 'preview_menu_enable_meta_box', true ) ) {
			$this->add_meta_box();
		}
	}

	/**
	 * Plugin frontend.
	 * @since  0.1
	 */
	public function front() {

		/**
		 * Option to switch to another menu preview for users who can manage menu's.
		 */
		if ( ! empty( $_GET['preview_menu'] ) && current_user_can( $this->capability ) ) {
			add_filter( 'wp_nav_menu_args', array( $this, 'filter_wp_nav_menu_args' ), 1 );
		}
	}

	/**
	 * Change menu.
	 * @since  0.1
	 * @param  array  $args
	 * @return array
	 */
	public function filter_wp_nav_menu_args( $args ) {

		// @todo Improve default location.
		$location = apply_filters( 'preview_menu_default_location', 'primary', $args );

		if ( ! empty( $_GET['preview_menu_location'] ) ) {
			$location = $_GET['preview_menu_location'];
		}

		if ( $location !== $args['theme_location'] ) {
			return $args;
		}

		$menu = $_GET['preview_menu'];
		$args['menu'] = $menu;

		return $args;
	}

	/**
	 * Add the meta box to the menu options.
	 * @since  0.1
	 */
	public function add_meta_box() {
		add_meta_box(
			'preview-menu-meta-box',
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
		global $nav_menu_selected_id;
		$url = get_bloginfo( 'url' );
		$url = add_query_arg( 'preview_menu', $nav_menu_selected_id, $url );

		?>
		<label for="preview_menu_location"><?php esc_html_e( 'Select location', 'preview-menu' ) ?></label>
		<select name="preview_menu_location" id="preview_menu_location" class="widefat">
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
				<a href="<?php echo $url; ?>" id="preview_menu_btn" target="_blank" class="button button-primary"><?php esc_html_e( 'Preview Menu', 'preview-menu' ) ?></a>
			</span>
		</p>
		<script>
			jQuery( function( $ ) {
				$('#preview_menu_btn').on( 'click', function( e ) {
					e.preventDefault();
					e.stopPropagation();

					var location = $('#preview_menu_location').val(),
						url = $(this).attr('href');

					url += '&preview_menu_location=' + location;

					window.open( url, '_blank' );
				} );
			} );
		</script>
		<?php
	}

}
