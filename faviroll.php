<?php
/*
Plugin Name: FAVIROLL - FAVIcons for blogROLL
Plugin URI: http://www.grobator.de/wordpress-stuff/plugins/faviroll
Description: Locally caches all favicon.ico in PNG format and use this into the blogroll. Native ICO Images are not supported from all browsers/operating systems. <strong><a href="link-manager.php?page=faviroll.php">For Settings jump to: Links &raquo; Faviroll</a></strong>
Author: grobator
Version:  0.4.7
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


/**
 * Main function
 */
function faviroll_list_bookmarks($output) {
	$faviroll = new Faviroll();
	return $faviroll->apply($output);
}
add_filter('wp_list_bookmarks', 'faviroll_list_bookmarks');
add_filter('wp_list_bookmarks_plus', 'faviroll_list_bookmarks');

/**
 * The admin-page renew the favicons after the configured time
 */
function faviroll_revisit() {

	if (!(bool) get_option('faviroll_lastcheck'))
		return false;

	$faviroll = new Faviroll();
	return $faviroll->revisit();
}
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
add_action('edit_link', 'faviroll_single_favicon');
add_action('add_link' , 'faviroll_single_favicon');


/**
 * Add option page
 */
function faviroll_options(){

	$message = null;
	$removeSettings = isset($_REQUEST['faviroll_remove_settings']);

	// Initialize plugin options if not remove settings is requested
	$faviroll = new Faviroll(!$removeSettings);

	$nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : null;
	if (wp_verify_nonce($nonce, 'my-nonce') ) {

		if($removeSettings) {

			$faviroll->flush(true);
			$faviroll->removeSettings();

			$message = 'Plugin settings and cached icons removed.<br /><a href="./plugins.php?plugin_status=active">Now you can deactivate the plugin</a>';

		} else {

			$was_transparency = (get_option('faviroll_transparency') == 'on');

			$defico = trim($_REQUEST['faviroll_default_favicon']);
			update_option('faviroll_default_favicon', (empty($defico) ? $faviroll->getFavirollDefaultIcon() : $defico));

			update_option('faviroll_revisit'       , (int) trim($_REQUEST['faviroll_revisit']));
			update_option('faviroll_transparency'  , (isset($_REQUEST['faviroll_transparency']) ? 'on' : 'off') );
			update_option('faviroll_debug'         , (isset($_REQUEST['faviroll_debug']) ? 'on' : 'off') );
			update_option('faviroll_use_stylesheet', (isset($_REQUEST['faviroll_use_stylesheet']) ? 'on' : 'off') );

			$message = 'Settings updated';
		}
	}

	// create nonce
	$nonce = wp_create_nonce('my-nonce');

	$default_favicon = get_option('faviroll_default_favicon');
	$revisit = ($removeSettings) ? null : (int) get_option('faviroll_revisit');

	$is_transparency = (get_option('faviroll_transparency') == 'on');
	$is_debugMode    = (get_option('faviroll_debug') == 'on');
	$use_stylesheet  = (get_option('faviroll_use_stylesheet') == 'on');

	if (!$removeSettings) {

		if (isset($was_transparency) && ($is_transparency != $was_transparency)) {
			$message = 'Transparency switched';
			$_REQUEST['faviroll_renew_icons'] = 'true';
		}
		if(isset($_REQUEST['faviroll_renew_icons']))
			$faviroll->reset();

	}

	if (!is_null($message))
		$message = '<div class="updated fade below-h2" id="message"><p>'.$message.'</p></div>';

	echo '
 <div class="wrap">
  <div id="icon-options-general" class="icon32"><br /></div>
  <h2>'.__('FAVIcons for blogROLL', 'faviroll').' Settings</h2>'.$faviroll->get_message($message).'<br />
  <h4 style="display:inline;">Currently [ '.$faviroll->cacheIconsCount().' ] icons in the cache directory.</h4>

	 <div style="float: right;margin:10px;padding-right:50px;">
	  <a href="http://donate.grobator.de/"><img src="https://www.paypal.com/en_GB/i/btn/btn_donate_SM.gif" border="0" alt="donate" title="Sollte Ihnen das Plugin gefallen, w&auml;re ich &uuml;ber eine kleine Spende sehr erfreut" /></a
	 </div>
   <form id="faviroll" name="faviroll" method="post">
    <table class="form-table" summary="">
     <tr>
      <td width="150">Default Favicon URL:</td>
      <td><input type="text" name="faviroll_default_favicon" size="80" value="'.$default_favicon.'" /><br />(If the favicon on the link is missing this will be shown instead.)</td>
     </tr>
     <tr>
      <td>Favions revisit after:</td>
      <td><input type="text" name="faviroll_revisit" size="4" value="'.$revisit.'" /> days</td>
     </tr>
     <tr>
      <td>make Favicon<br />background transparent:</td>
      <td><input type="checkbox" name="faviroll_transparency" value="on"'.($is_transparency ? ' checked="checked"' : null).' /></td>
     </tr>
     <tr>
      <td>Use faviroll/style.css:</td>
      <td>
        <input type="checkbox" name="faviroll_use_stylesheet" value="on"'.($use_stylesheet ? ' checked="checked"' : null).' />
         &nbsp;&nbsp;&nbsp;
        <label for="faviroll_use_stylesheet"><a href="plugin-editor.php?file=faviroll/style.css&plugin=faviroll/faviroll.php" title="Edit the faviroll css-styles">Edit faviroll stylesheet</a></label>
      </td>
     </tr>
	   <tr>
	      <td colspan="2" style="line-height:5px;padding:0px;"><hr size="1" width="90%" /></td>
	   </tr>
     <tr>
      <td><strong>Actions</strong></td>
     </tr>
     <tr>
      <td>(re)build FavIcons now:</td>
      <td><input type="checkbox" name="faviroll_renew_icons" value="true"'.(($faviroll->cacheIconsCount() == 0) ? ' checked="checked"' : null).' /></td>
     </tr>
     <tr>
      <td title="This will remove plugin settings from database and drop the favicon cache">Remove settings:</td>
      <td><input type="checkbox" name="faviroll_remove_settings" value="true" />
     </tr>
';
/*
				<tr>
					<td title="Write debug informations as comments into the HTML code">Debug mode:</td>
			    <td><input type="checkbox" name="faviroll_debug" value="true" '.($is_debugMode ? ' checked="checked"' : null).' /> &nbsp; ( just for Developers, normally switch off )
				</tr>
*/
	echo '     <tr>
      <td class="submit"><input type="submit" name="submit_button" value="Submit" /><input type="hidden" name="_wpnonce" value="'.$nonce.'" /></td>
     </tr>
    </table>
   </form>
  </div>
';

		ob_flush();
		flush();
}


/**
 * Register Faviroll menu in general options menu
 */
function faviroll_menu() {
	add_submenu_page('link-manager.php', __('FaviRoll', 'faviroll'), __('FaviRoll', 'faviroll'), 8, basename(__FILE__), 'faviroll_options');
}
add_action('admin_menu', 'faviroll_menu');


/**
 * Register Enqueue CSS, if option is activated in Faviroll-Settings
 */
if (get_option('faviroll_use_stylesheet') == 'on') {
	function faviroll_enqueue_scripts() {
		wp_enqueue_style('faviroll', WP_PLUGIN_URL.'/faviroll/style.css', false, false, 'all');
	}
	add_action('wp_enqueue_scripts', 'faviroll_enqueue_scripts');
}

/**
 * Actions on plugin activation
 */
function faviroll_activate() {
	$faviroll = new Faviroll();
	return $faviroll->patchPlugin('wp-render-blogroll-links',true);
}
register_activation_hook(__FILE__, 'faviroll_activate');


/**
 * Actions on plugin deactivation
 */
function faviroll_deactivate() {
	$faviroll = new Faviroll();
	return $faviroll->patchPlugin('wp-render-blogroll-links',false);
}
register_deactivation_hook(__FILE__, 'faviroll_deactivate');

/* eof */
?>
