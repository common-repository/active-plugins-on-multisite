<?php
/*
Plugin Name: Active Plugins
Plugin URI: http://trepmal.com/plugins/active-plugins-on-multisite/
Description: Get number of users for each active plugin (minus network-activated). Then break down by site.
Author: Kailey Lampert
Version: 1.8
Author URI: http://kaileylampert.com/
Network: true

Copyright (C) 2011-12 Kailey Lampert

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class Active_Plugins {

	/**
	 * Get hooked in
	 *
	 * @return void
	 */
	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize setup.
	 * Only prep admin page if we're on multisite
	 *
	 * @return void
	 */
	function init() {
		if ( ! is_multisite() ) {
			add_action( 'admin_notices',         array( $this, 'admin_notices' ) );
		}
		else {
			add_action( 'network_admin_menu',    array( $this, 'network_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
	}

	/**
	 * Print for-multisite-only notice
	 *
	 * @return void
	 */
	function admin_notices() {
		// Lazy notice. Dismissable, but we're not keeping track.
		echo '<div class="error fade notice is-dismissible"><p>';
		_e( 'Acitve Plugins is for multisite use only.', 'active-plugins' );
		echo '</p></div>';
	}

	/**
	 * Register menu page
	 *
	 * @return void
	 */
	function network_admin_menu() {
		add_submenu_page( 'settings.php', __( 'Active Plugins Across Network', 'active-plugins' ), __( 'Active Plugins', 'active-plugins' ), 'unfiltered_html', __FILE__, array( $this, 'page' ) );
	}

	/**
	 * Print admin page
	 *
	 * @return void
	 */
	function page() {

		echo '<div class="wrap">';
		echo '<h2>' . __( 'Active Plugins Across Network', 'active-plugins' ) . '</h2>';

		$links = array(
			sprintf( __( '<a href="%s">Network-Activated Plugins</a>*', 'active-plugins' ), network_admin_url( 'plugins.php?plugin_status=active' ) ),
			sprintf( __( '<a href="%s">MU Plugins</a>', 'active-plugins' ), network_admin_url( 'plugins.php?plugin_status=mustuse' ) ),
			);

		echo '<p>' . sprintf( __( 'List does not include: %s', 'active-plugins' ), implode( ', ', $links ) ) . '</p>';

		global $wpdb;

		$blog_list = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->blogs}, {$wpdb->registration_log} WHERE site_id = '%d' AND {$wpdb->blogs}.blog_id = {$wpdb->registration_log}.blog_id" ,
			$wpdb->siteid ),
		ARRAY_A ); // get blogs

		/* $all_plugins
		 * Multi-dimensional array of plugins. e.g.
		 *
		 *    [active-plugins-on-multisite/active-plugins.php] => Array
		 *        (
		 *            [Name] => Active Plugins
		 *            [PluginURI] => http://trepmal.com/plugins/active-plugins-on-multisite/
		 *            [Version] => 1.6
		 *            [Description] => Get number of users for each active plugin (minus network-activated). Then break down by site.
		 *            [Author] => Kailey Lampert
		 *            [AuthorURI] => http://kaileylampert.com/
		 *            [TextDomain] => active-plugins-on-multisite
		 *            [DomainPath] => 
		 *            [Network] => 1
		 *            [Title] => Active Plugins
		 *            [AuthorName] => Kailey Lampert
		 *        )
		 *
		 */
		$all_plugins = get_plugins();
		$plugins_list = array_keys( $all_plugins );

		// find which are network-activated
		$network_plugins = array_flip( get_site_option('active_sitewide_plugins') );

		/* $pi
		 * "Plugins Installed"
		 */
		$pi = array();

		// add main site to beginning
		$blog_list[-1] = array( 'blog_id' => 1 );
		ksort( $blog_list );

		// loop through the blogs
		foreach ( $blog_list as $k => $info ) {
			// store active plugins in giant array, index by blog id
			$bid        = $info['blog_id'];
			$pi[ $bid ] = get_blog_option( $bid, 'active_plugins' );
		}

		$pi_count = array();
		foreach ( $pi as $k => $v_array ) {
			// put all active plugins into one array, we can then count duplicate values
			$pi_count = array_merge( $pi_count, $v_array );
		}

		echo '<h3>';
		_e( 'Totals <span class="description">each active plugin and how many users</span>', 'active-plugins' );
		echo '</h3>';

		$totals = $tags = array_count_values( $pi_count );
		ksort( $totals );
		echo '<ul class="ul-disc">';
		foreach ( $totals as $name => $tot ) {

			/* Support for rudimentary tagging
			 * Will not be heavily maintained in future releases
			 */
			if ( strpos( $name, '/' ) !== false ) {
				$dir = WP_PLUGIN_DIR . '/' . dirname( $name );
				$dottags = ( glob( $dir . '/*.tag' ) );
				if ( ! empty( $dottags ) ) {
					$tags[ $name ] = str_replace( $dir . '/', '', str_replace( '.tag', '', $dottags['0'] ) );
				}
			}

			/* $plugins_list
			 * Remove active plugins from list, leaving us
			 * with a record of what's not installed on any site
			 */
			if ( in_array( $name, $plugins_list ) ) {
				$plugins_list = array_flip( $plugins_list );
				unset( $plugins_list[ $name ] );
				$plugins_list = array_flip( $plugins_list );
			}

			$version_number = isset( $all_plugins[ $name ]['Version'] ) ? $all_plugins[ $name ]['Version'] : '';
			$version_text   = sprintf( __( 'v%s', 'active-plugins' ), $version_number );

			$network_maybe = in_array( $name, $network_plugins ) ? ' <span class="description">' . __( '(network-activated)', 'active-plugins' ) . '</span>' : '';

			// Check if the active plugin is still installed
			if ( isset( $all_plugins[ $name ] ) ) {
				$label = sprintf( __( '%1$s %2$s %3$s', 'active-plugins' ), $all_plugins[ $name ]['Name'], $version_text, $network_maybe );
			} else {
				$label = sprintf( __( '%1$s (Uninstalled)', 'active-plugins' ), $name );
			}

			/* Part of legacy tagging
			 */
			$label .= is_numeric( $tags[ $name ] ) ? '' : sprintf( __( ' (tagged: %s)', 'active-plugins' ), $tags[ $name ] );

			$slug   = sanitize_title( $name );
			$fulllabel = sprintf( _n( '<strong>%s</strong> is used by %d site', '<strong>%s</strong> is used by %d sites', $tot, 'active-plugins' ), $label, $tot );
			echo "<li><label><input class='show-plugin hide-if-no-js' type='checkbox' value='{$slug}' checked /><style>.{$slug}{display:block;}</style>$fulllabel</label></li>";

		}
		echo '</ul>';

		$links = array(
			'<a href="#" class="select-all">' . __( 'Select all', 'active-plugins' ) . '</a>',
			'<a href="#" class="deselect-all">' . __( 'Deselect all', 'active-plugins' ) . '</a>',
			'<a href="#" class="toggle-all">' . __( 'Toggle', 'active-plugins' ) . '</a>',
		);
		echo '<p class="hide-if-no-js">' . implode( ' | ', $links ) . '</p>';

		// remove NA-plugins from our list of remaining inactive plugins
		$remove_network  = array_diff( $plugins_list, $network_plugins );

		// show which not-network-activated plugins have 0 users
		_e( 'Plugins with zero (0) users:', 'active-plugins' );
		echo '<ul class="ul-disc">';
		foreach ( $remove_network as $k => $inactive ) {
			$version_number = isset( $all_plugins[ $inactive ]['Version'] ) ? $all_plugins[ $inactive ]['Version'] : '';
			$version_text   = sprintf( __( 'v%s', 'active-plugins' ), $version_number );
			$realname       = sprintf( __( '%1$s %2$s', 'active-plugins' ), $all_plugins[ $inactive ]['Name'], $version_text );
			$unused[]       = "<li>{$realname}</li>";
		}
		echo empty( $unused ) ? '<li><em>' . __( 'none', 'active-plugins' ) . '</em></li>' : implode( $unused );
		echo '</ul>';

		/*
			Output plugins for each site
		*/
		echo '<hr />';
		echo '<p><a href="#" class="show-empty">' . __( 'Show sites with no active plugins', 'active-plugins' ) . '</a></p>';
		foreach ( $pi as $siteid => $list ) {

			switch_to_blog( $siteid );

			$edit    = network_admin_url( "site-info.php?id=$siteid" );
			$view    = home_url();
			$dash    = admin_url();
			$plugins = admin_url('/plugins.php');

			$blogname        = get_bloginfo('name');
			$edit_label      = __( 'Edit', 'active-plugins' );
			$view_label      = __( 'View', 'active-plugins' );
			$dashboard_label = __( 'Dashboard', 'active-plugins' );
			$plugins_label   = __( 'Plugins', 'active-plugins' );

			$group_class = $list ? '' : ' no-plugins';
			echo "<div class='site-group{$group_class}'>";
			echo "<h3>$blogname <span class='description'>(ID: $siteid) [<a href='$edit'>$edit_label</a>] [<a href='$view'>$view_label</a>] [<a href='$dash'>$dashboard_label</a>] [<a href='$plugins'>$plugins_label</a>]</span></h3>";
			echo $list ? '<ul class="ul-disc">' : '';
			$tagged = array();
			$nottagged = array();
			foreach ( $list as $name ) {
				$realname = isset( $all_plugins[ $name ] ) ? $all_plugins[ $name ]['Name'] : $name;
				$slug = esc_attr( sanitize_title( $name ) );
				$network_maybe = in_array( $name, $network_plugins ) ? ' <span class="description">' . __( '(network-activated)', 'active-plugins' ) . '</span>' : '';
				if ( is_numeric( $tags[ $name ] ) ) {
					$nottagged[] .= "<li class='hidden $slug'>{$realname}$network_maybe</li>";
				}
				else {
					$tagged["<li class='hidden $slug'>({$tags[ $name ]}) $realname$network_maybe</li>"] = $tags[ $name ];
				}
			}

			/* Part of legacy tagging
			 */
			asort( $tagged );
			$tagged = array_keys( $tagged );
			echo implode( $tagged );

			sort( $nottagged );
			echo implode( $nottagged );

			echo $list ? '</ul>' : '';
			echo '</div>';

			restore_current_blog();
		}

		echo '<p>';
		esc_html_e( '* The "network-activated" notation indicates that the plugin is also activated individually for a site. This can happen when a plugin is available for some time before being network-activated.', 'active-plugins' );
		echo '</p>';

		echo '</div>';

	} // end page()


	/**
	 * Enqueue JavaScript
	 * Inline script with jQuery dependency
	 *
	 * @return void
	 */
	function enqueue_scripts( $hook ) {
		if ( 'settings_page_active-plugins-on-multisite/active-plugins' !== $hook ) {
			return;
		}
		ob_start();
		?>
jQuery( document ).ready( function($) {
	$('a.select-all').click( function(ev) {
		ev.preventDefault();
		$('.show-plugin:not(:checked)').click();
	});
	$('a.deselect-all').click( function(ev) {
		ev.preventDefault();
		$('.show-plugin:checked').click();
	});
	$('a.toggle-all').click( function(ev) {
		ev.preventDefault();
		$('.show-plugin').click();
	});
	$('a.show-empty').click( function(ev) {
		ev.preventDefault();
		$('.no-plugins').toggle();
	});
	$('.show-plugin').change( function() {
		plugin = $(this).val();
		$('li.'+plugin).toggle();
	} );
});
		<?php
		$script = ob_get_clean();
		// wp_enqueue_script( 'jquery-core' );
		wp_add_inline_script( 'jquery-core', $script );

		ob_start();
		?>
.site-group.no-plugins {
	display: none;
}
		<?php
		$style = ob_get_clean();
		// wp_enqueue_style( 'admin-bar' );
		wp_add_inline_style( 'admin-bar', $style );
	}

}

/**
 * Do it!
 *
 * note: "kdl_" prefix since 'active_plugins' is generic with a decent probability of conflict
 */
$kdl_active_plugins = new Active_Plugins();
