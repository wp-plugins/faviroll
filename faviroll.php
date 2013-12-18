<?php
/*
 Plugin Name: FAVIROLL - FAVIcons for blogROLL
 Plugin URI: http://www.andurban.de/wordpress-stuff/plugins/faviroll
 Description: Caches all favicon.ico in PNG format and use this in your blogroll.
 Author: andurban.de
 Version:  0.5.2
 Author URI:  http://www.andurban.de/
 Plugin URI:  http://www.andurban.de/tag/faviroll
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

	########################
	#     BEGIN Common     #
	########################

	/**
	 * Faviroll CSS in a enqueue way
	 */
	function enqueueFavirollScriptsAndStyles() {

		$plugin_dir = plugin_dir_path(__FILE__);
		
		if (is_admin()) {
		
			// @see http://codex.wordpress.org/Function_Reference/wp_enqueue_style
			//
			$myUrl = plugins_url('css/style-be.css', __FILE__); 
			$myFile = "${plugin_dir}css/style-be.css";
	
			if (file_exists($myFile)) {
				wp_register_style('faviroll-be', $myUrl);
				wp_enqueue_style('faviroll-be',false, array('admin-bar-css'));
			}
	
			// @see http://codex.wordpress.org/Function_Reference/wp_enqueue_script
			//
			$myUrl = plugins_url('js/faviroll.js', __FILE__); 
			$myFile = "${plugin_dir}js/faviroll.js";
		
			if (file_exists($myFile)) {
				wp_register_script('faviroll', $myUrl, array('jquery'), '1.0');
				wp_enqueue_script('faviroll');
			}
		}
	
		// queueing Common CSS styles
		$myUrl = plugins_url('css/style.css', __FILE__); 
		$myFile = "${plugin_dir}css/style.css";

		if (file_exists($myFile)) {
			wp_register_style('faviroll', $myUrl);
			wp_enqueue_style('faviroll');
		}
		
	}	
	enqueueFavirollScriptsAndStyles();
	
	########################
	#      END Common      #
	########################


if (is_admin()) {

	########################
	#  BEGIN Backend Area  #
	########################
	
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
	function faviroll_option_page() {
		require_once('FavirollAdmin.class.php');
		$fa = new FavirollAdmin();
		$fa->option_page();
	}


	/**
	 * Register Faviroll options in 'Links' options menu
	 */
	function faviroll_menu() {
		add_submenu_page('link-manager.php', __('Faviroll', 'faviroll'), __('Faviroll', 'faviroll'), 'manage_options', basename(__FILE__), 'faviroll_option_page');
	}
	add_action('admin_menu', 'faviroll_menu');


	/**
	 * Compute a favicon of a single bookmark
	 * @param $link_id - Database id of the current bookmark
	 */
	function faviroll_single_favicon($link_id) {
		require_once('Faviroll.class.php');

		$bm = get_bookmark($link_id);
		$bookmark = $bm->link_url;

		$fr = new Faviroll();
		$fr->putIconIntoCache($bookmark);
	}
	add_action('edit_link', 'faviroll_single_favicon');
	add_action('add_link' , 'faviroll_single_favicon');


	/**
	 * Insert the favicon column before column:Namen in Link-Admin Menu
	 */
	function faviroll_edit_link_columns($columns){

		$result = array();

		foreach ($columns as $key => $value) {
			switch($key) {
				case 'name':
					$result['faviroll'] = '';
				default:
					$result[$key] = $value;
					break;
			}
		}
		return $result;
	}
	add_filter('manage_link-manager_columns', 'faviroll_edit_link_columns');


	/**
	 * 
	 */
	function faviroll_manage_link_columns($column_name, $id) {
		static $fr;

		$bookmark = get_bookmark($id);
		switch($column_name) {
		case 'faviroll':
			require_once('Faviroll.class.php');
		
			if (!isset($fr))
				$fr = new Faviroll();

			echo '<img src="'.$fr->getFaviconByBookmark($bookmark).'" title="" alt="" />';
			break;
		default:
			break;
		}
	}
	add_action('manage_link_custom_column', 'faviroll_manage_link_columns', 10, 2);

	
	
	######################
	#  END Backend Area  #
	######################
	

} else {

	#########################
	#  BEGIN Frondend Area  #
	#########################
	
	/**
	 * Main function
	 */
	function faviroll_list_bookmarks($output) {
		require_once('Faviroll.class.php');
		
		$fr = new Faviroll();
		return $fr->apply($output);
	}
	add_filter('wp_list_bookmarks', 'faviroll_list_bookmarks');
	add_filter('wp_list_bookmarks_plus', 'faviroll_list_bookmarks');

	#######################
	#  END Frondend Area  #
	#######################
	

} // end if is_admin()


/* eof */
?>
