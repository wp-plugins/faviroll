<?php
/*
Plugin Name: FAVIcons for blogROLL
Plugin URI: http://www.grobator.de/wordpress-stuff/plugins/faviroll
Description: Locally caches all favicon.ico in PNG format and use this into the blogroll. Native ICO Images are not supported from all browsers/operating systems. <strong><a href="options-general.php?page=faviroll.php">Settings &raquo; Faviroll</a></strong>
Author: grobator
Version:  [[ **BETA** ]]
Author URI:  http://www.grobator.de/
*/

// Debug only on localhost 
if ($_SERVER['HTTP_HOST'] == 'localhost') error_reporting(E_ALL);


require_once('classes/Faviroll.php');

// - - - - - - - - - - - - - - [Wordpress plugin stuff] - - - - - - - - - - - - - - - - - - - -

/**
 * Main function
 */
function faviroll_list_bookmarks($output) {

	$faviroll = new Faviroll();
	return $faviroll->apply($output);
}
// Add Filter
add_filter('wp_list_bookmarks', 'faviroll_list_bookmarks');



/**
 * The admin-page renew the favicons after the configured time 
 */
function faviroll_revisit() {

	if (!(bool) get_option('faviroll_lastcheck'))
		return false;

	$faviroll = new Faviroll();
	return $faviroll->revisit();
}

// Add Action
add_action('admin_notices', 'faviroll_revisit');

/**
 * Compute a favicon of a single bookmark
 * @param $link_id - Database id of the current bookmark
 */
function faviroll_single_favicon($link_id) {

	if (!(function_exists('is_admin') && is_admin()))
		return false;

	$faviroll = new Faviroll();
	$faviroll->putIconIntoCache(get_link($link_id));
}

// Add Action
add_action('edit_link', 'faviroll_single_favicon');
add_action('add_link' , 'faviroll_single_favicon');


// ------------------------------------------------------------------------------------

/**
 * Add option page
 */
function faviroll_options(){

	// Initialize plugin options if not remove settings is requested
	$faviroll = new Faviroll(!isset($_REQUEST['faviroll_remove_settings']));

	$nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : null;
	if (wp_verify_nonce($nonce, 'my-nonce') ) {

		if(isset($_REQUEST['faviroll_remove_settings'])) {

			$faviroll->flush();

			delete_option('faviroll_default_favicon');
			delete_option('faviroll_revisit');
			delete_option('faviroll_transparency');
			delete_option('faviroll_lastcheck');

			$msg = 'Plugin settings and cached icons removed.<br /><a href="./plugins.php?plugin_status=active">Switch to the active plugins</a>';

		} else {

			$was_transparency = (get_option('faviroll_transparency') == 'on');

			$defico = trim($_REQUEST['faviroll_default_favicon']);
			update_option('faviroll_default_favicon', (empty($defico) ? $faviroll->getFavirollDefaultIcon() : $defico));

			update_option('faviroll_revisit'     , (int) trim($_REQUEST['faviroll_revisit']));
			update_option('faviroll_transparency', (isset($_REQUEST['faviroll_transparency']) ? 'on' : 'off') );

			$msg = 'Settings updated';
		}
	}

	$msg = null;
	$nonce = wp_create_nonce('my-nonce');
	$default_favicon = get_option('faviroll_default_favicon');
	$revisit = (int) get_option('faviroll_revisit');
	$is_transparency = (get_option('faviroll_transparency') == 'on');


	if (isset($was_transparency) && ($is_transparency != $was_transparency)) {
		$msg = 'Transparency switched';
		$_REQUEST['faviroll_renew_icons'] = 'true';
	}

	if(isset($_REQUEST['faviroll_renew_icons']))
		$faviroll->reset();

	if (isset($msg))
		$msg = '<div class="updated fade below-h2" id="message"><p>'.$msg.'</p></div>';

	echo '
			<div class="wrap">
				<h2>'.__('FAVIcons for blogROLL', 'faviroll').'</h2>'.$msg.'
				<form id="faviroll" name="faviroll" method="post">
				<table class="form-table">
				<tr>
					<td colspan="2" >
						<p><strong>Settings</strong></p>
					</td>
				</tr>
				<tr> 
					<td scope="row" valign="top">Default Favicon URL:</td>
			    	<td><input type="text" name="faviroll_default_favicon" size="120" value="'.$default_favicon.'" /><br />(If the favicon on the link is missing this will be shown instead.)</td>
				</tr>
				<tr>
					<td scope="row">Favions revisit after:</td>
			    	<td><input type="text" name="faviroll_revisit" size="4" value="'.$revisit.'" /> days</td>
				</tr>
				<tr>
					<td scope="row">Use transparent background:</td>
			    	<td><input type="checkbox" name="faviroll_transparency" value="on"'.($is_transparency ? ' checked="checked"' : null).' /></td>
				</tr>
				<tr>
					<td colspan="2" width="98%"><hr size="1" /></td>
				</tr>
				<tr>
					<td><p><strong>Actions</strong></p></td>
				</tr>
				<tr>
					<td scope="row">(re)build FavIcons now:</td>
			    	<td><input type="checkbox" name="faviroll_renew_icons" value="true"'.(($faviroll->cacheIconsCount() == 0) ? ' checked="checked"' : null).' /></td>
				</tr>
				<tr>
					<td scope="row">Remove settings and cache:</td>
			    	<td><input type="checkbox" name="faviroll_remove_settings" value="true" />
				</tr>
				<tr> 
					<td colspan="2">
						<p class="submit">
							<input type="submit" name="submit_button" value="Submit" />
						</p>
						<input type="hidden" name="_wpnonce" value="'.$nonce.'" />
					</td>
				</tr>		
				</table>
				</form>
			</div>
		 ';

		flush();
		ob_flush();
}


/**
 * Register Faviroll menu in general options menu
 */
function faviroll_menu() {
	add_submenu_page('options-general.php', __('Faviroll', 'faviroll'), __('Faviroll', 'faviroll'), 8, basename(__FILE__), 'faviroll_options');
}
add_action('admin_menu', 'faviroll_menu');


/* eof */
?>
