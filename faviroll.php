<?php
/*
 Plugin Name: FAVIROLL - FAVIcons for blogROLL
 Plugin URI: http://www.grobator.de/wordpress-stuff/plugins/faviroll
 Description: Locally caches all favicon.ico in PNG format and use this into the blogroll. Native ICO Images are not supported from all browsers/operating systems.
 Author: grobator
 Version:  0.4.8.2
 Author URI:  http://www.grobator.de/
 ----------------------------------------------------------------------------------------
 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Debug only on localhost
if ($_SERVER['HTTP_HOST'] == 'localhost') error_reporting(E_ALL);  // |E_STRICT

require_once('Faviroll.class.php');

if (is_admin()) {
  #################
  # Backend Area  #
  #################

	require_once('FavirollAdmin.class.php');

	/**
	 * Add Settings link to plugin page
	 * @param unknown_type $links
	 */
	function faviroll_addConfigureLink( $links ) {
		$settings_link = '<a href="link-manager.php?page='.basename(__FILE__).'">'. __('Settings').'</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
	$plugin = plugin_basename(__FILE__);
	add_filter("plugin_action_links_$plugin", 'faviroll_addConfigureLink' );


	/**
	 * Faviroll options menu
	 */
	function faviroll_settings() {
		$fa = new FavirollAdmin();
		$fa->settings();
	}


	/**
	 * Register Faviroll menu in general options menu
	 */
	function faviroll_menu() {
		add_submenu_page('link-manager.php', __('Faviroll', 'faviroll'), __('Faviroll', 'faviroll'), 'manage_options', basename(__FILE__), 'faviroll_settings');
	}
	add_action('admin_menu', 'faviroll_menu');


	/**
	 * The admin-page renew the favicons after the configured time
	 */
	function faviroll_revisit() {

		$opts = get_option('faviroll');
		$isOk = ($opts && isset($opts['lastcheck']) && isset($opts['revisit']));
		if (!$isOk)
			return false;

		$lastcheck = (float)$opts['lastcheck'];
		$revisit = (int)$opts['revisit'];

		$offsetFromNow = @strtotime("$revisit days ago midnight");
		if ($offsetFromNow === false)
			$offsetFromNow = time('0 days ago midnight');

		if ($offsetFromNow < $lastcheck)
			return false;

		$fa = new FavirollAdmin();
		return $fa->revisit();

	}
	add_action('admin_notices', 'faviroll_revisit');


	/**
	 * Compute a favicon of a single bookmark
	 * @param $link_id - Database id of the current bookmark
	 */
	function faviroll_single_favicon($link_id) {

		$fa = new FavirollAdmin();
		$fa->putIconIntoCache(get_link($link_id));
	}
	add_action('edit_link', 'faviroll_single_favicon');
	add_action('add_link' , 'faviroll_single_favicon');

} else {

  ##################
  # Fromtend Area  #
  ##################

	/**
	 * Main function
	 */
	function faviroll_list_bookmarks($output) {
		$fr = new Faviroll();
		return $fr->apply($output);
	}
	add_filter('wp_list_bookmarks', 'faviroll_list_bookmarks');
	add_filter('wp_list_bookmarks_plus', 'faviroll_list_bookmarks');

	/**
	 * Register Enqueue CSS, if option is activated in Faviroll-Settings
	 */
	function faviroll_enqueue_scripts() {
		$opts = get_option('faviroll');

		if ($opts && isset($opts['use_stylesheet']) && (bool)$opts['use_stylesheet'])
			wp_enqueue_style('faviroll', WP_PLUGIN_URL.'/faviroll/style.css');
	}
	add_action('wp_enqueue_scripts', 'faviroll_enqueue_scripts');

} // end if is_admin()


/* eof */
?>
