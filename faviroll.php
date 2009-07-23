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

class Faviroll {

	// Member Vars
	
	var $cachedir = null;
	var $cacheurl = null;
	var $pluginurl = null;
	var $plugindir = null;
	var $lastcheck = null;
	var $defaulticon = null;
	var $transparency = null;

	/**
	 * PHP4 Construktor. Wrapper for __construct()
	 */
	function Faviroll() {
		$args = func_get_args();
		call_user_func_array(array(&$this, '__construct'), $args);
	}


	/**
	 * PHP5 Construktor
	 * @param $init optional boolean - if TRUE environment will be initialisized
	 */
	function __construct($init=true) {

		$this->plugindir = dirname(__FILE__);
		$this->pluginurl = WP_CONTENT_URL.'/plugins/'.plugin_basename($this->plugindir);
		$this->cachedir = $this->plugindir.'/cache';
		$this->cacheurl = $this->pluginurl.'/cache';

		$this->defaulticon = get_option('faviroll_default_favicon');

		// initialize with useful default.
		if (empty($this->defaulticon)) {
			$this->defaulticon = $this->getFavirollDefaultIcon();
			$init && update_option('faviroll_default_favicon',$this->defaulticon);
		}

		$this->lastcheck = get_option('faviroll_lastcheck');

		// initialize with useful default.
		if (empty($this->lastcheck)) {
			$this->lastcheck = 0;
			$init && update_option('faviroll_lastcheck',$this->lastcheck);
		}

		// default is enabled background tranparency.
		$trans = get_option('faviroll_transparency');
		if (empty($trans))
			$init && update_option('faviroll_transparency','on');

		// initialize with useful default.
		$revisit = get_option('faviroll_revisit');
		if (empty($revisit))
			update_option('faviroll_revisit', 14);

		$this->gc();

	}



	/**
	 * Garbage collector
	 */
	function gc() {

		// Veraltetes cache Verzeichnis abr�umen
		// Cache dir until version 0.4.2
		//
		$dir = wp_upload_dir('2009/07');
		if (!isset($dir['path']))
			return true;
		
		$favidir = $dir['path'].'/faviroll_cache';
		if (!is_dir($favidir))
			return true;

		// MD5 Strings are always 32 characters
		foreach(@glob($favidir.'/????????????????????????????????') as $item) {
			if (is_file($item))
				@unlink($item);
		}

		@rmdir($favidir);
	}



	/**
	 * from http://de3.php.net/is_writable
	 * Since looks like the Windows ACLs bug "wont fix" (see http://bugs.php.net/bug.php?id=27609) I propose this alternative function:
	 */
	function is__writable($path) {

		if ($path{strlen($path)-1}=='/')
			return $this->is__writable($path.uniqid(mt_rand()).'.tmp');

		if (file_exists($path)) {
			if (!($f = @fopen($path, 'r+')))
				return false;
			fclose($f);
			return true;
		}

		if (!($f = @fopen($path, 'w')))
			return false;

		fclose($f);
		unlink($path);

		return true;
	}


	/**
	 * @return the factory default favicon URL
	 */
	function getFavirollDefaultIcon() {
		return $this->pluginurl.'/faviroll-favicon.png';
	}

	/**
	 * Place a favicon into local cache directory
	 * @param &$link - Reference to WP Bookmark Link Object
	 * @param $verbose [optional] if TRUE echoing some working feedback
	 * @return TRUE, if cache file is created
	 */
	function putIconIntoCache(&$link,$verbose=false) {

		require_once('faviroll-ico-class.php');

		if (is_null($this->transparency))
			$this->transparency = (get_option('faviroll_transparency') == 'on');

		$url = $link->link_url;
	
		preg_match('/http:\/\/[a-zA-Z0-9-\.]+(\/|\s|$|\")/', $url,$elems);

		$rooturl = trim(rtrim($elems[0],'/')).'/';
		$iconame = md5(strtolower($rooturl));

		$icopath = $this->cachedir."/$iconame";
		if ($verbose) {
			echo "<br />detecting: $url....";
			flush();
			ob_flush();
		}

echo "+++".$icopath."+++<br />";
ob_flush();

		$icourl = $this->locateIcon($rooturl);
		$image = null;

		$ico = new Ico($icourl,$this->transparency);

		if ($ico->TotalIcons() > 0) {
			// Convert first ICO to PNG Format
			$image = $ico->GetIcon(0);
		} elseif (isset($ico->rawdata)) {

			$handle = @fopen($icopath,'wb');
			if ($handle) {
				fwrite($handle,$ico->rawdata);
				fflush($handle);
				fclose($handle);
			}

		} else {
			// icon url contains no valid data
			$icourl = null;
		}

		if (!is_null($image)) {
			// Make shure that image size = 16x16
			$width = imagesx($image);
			$height = imagesy($image);
			if ($width != 16 || $height != 16) {
				$image_tmp = imagecreatetruecolor(16,16);

				imagecopyresampled($image_tmp, $image, 0, 0, 0, 0, 16, 16, $width, $height);
				imagedestroy($image);					
				$image = $image_tmp;
			}
		}


		if (!is_null($image)) {
			$result = imagepng($image,$icopath);
			imagedestroy($image);					
		}

		// Create empty file if cache icon file are not exists
		if (!is_file($icopath))
			fclose(fopen($icopath,'w'));

		$result = (is_file($icopath) && (filesize($icopath) > 0));

		if ($verbose) {
			echo '&nbsp;<img src="'.(($result) ? $this->cacheurl."/$iconame" : $this->defaulticon).'" />';

			if (!strlen($icourl))
				$icourl = 'fallback to default favicon';

			echo "&nbsp; ( $icourl )";

			flush();
			ob_flush();
		}

		return $result;
	}


	/**
	 * Renew all PNG-icons
	 */
	function reset() {
		$this->flush();
		$this->revisit();
	}


	/**
	 * Delete all files from cache
	 */
	function flush() {

		$this->lastcheck = 0;
		update_option('faviroll_lastcheck',$this->lastcheck);

		foreach($this->getCacheIcons() as $item) {
			if (is_file($item))
				@unlink($item);
		}

		return ($this->getCacheIconsCount() == 0);
	}


	/**
	 * @return Reference on Array-List with all favicon file basenames from cache.
	 */
	function &getCacheIcons() {

		$result = array();
		                               // MD5 Strings are always 32 characters
		foreach(@glob($this->cachedir.'/????????????????????????????????') as $item) {
			if (is_file($item) && filesize($item))
				$result[] = basename($item);
		}

		return $result;
	}


	/**
	 * @return The count of icons in cache directory.
	 */
	function cacheIconsCount() {
		$result = $this->getCacheIcons();
		return count($result);
	}


	/**
	 * Check revisit date and refresh cached favicons if timeout is occured.
	 */
	function revisit() {
		$revisit = (int) get_option('faviroll_revisit');

		$offsetFromNow = strtotime("$revisit days ago midnight");

		if ($offsetFromNow === false)
			$offsetFromNow = time('0 days ago midnight');

		if ($offsetFromNow < $this->lastcheck)
			return false;


		// Max. Laufzeit auf 5 Min. setzen
		@ini_set('max_execution_time',300);


		echo '<div class="updated fade below-h2" id="message"><p>
<b>
FAVIROLL is (re)building the favicons from your blogroll links...<br />
This may be take some time... stay tuned, please!</b><br />';

		flush();
		ob_flush();

		# ---------- next stage 
		foreach(get_bookmarks() as $link) {
			$this->putIconIntoCache($link,true);
		}

		echo'</p></div><script type="text/javascript">var t = document.getElementById("message"); if (t){ t.style.display = "none"; }</script>';
		flush();
		ob_flush();

		update_option('faviroll_lastcheck',time());

		return true;
	}


	/**
	 * Apply the favicons to the blogroll hyperlinks
	 * @param $content - The widget content
	 */
	function apply($content) {

		$relPath = $this->getRelativePath();

		// get default icon from database
		$default_favicon = get_option('faviroll_default_favicon');

		// get cached icon list
		$cacheIcons = $this->getCacheIcons();

		// split bookmarks in lines
		$lines = explode("\n",$content);

		$newContent = array();

		// analyze bookmark lines
		foreach($lines as $line) {

			$line = trim($line);

			if (!(preg_match('/href="(.*)"/', $line, $matches) && !preg_match('/img/', $line))) {
				// overhead stuff
				$newContent[] = $line;
				continue;
			}

			preg_match('/http:\/\/[a-zA-Z0-9-\.]+(\/|\s|$|\")/', $matches[1],$urls);

			$rooturl = str_replace('"', "", $urls[0]);
			$rooturl = trim(rtrim($rooturl,'/')).'/';

			$fullurl = trim($matches[1]);

			// md5 cecksum of root-URL is name of favicon cache file
			$favicon = md5(strtolower($rooturl));

			// set favicon from cache or fallback to default
			$favicon = (in_array($favicon,$cacheIcons)) ? "$relPath/$favicon" : $default_favicon;

			$token = preg_split('#(<li |<a )#',$line);
			if (count($token) == 3)
				$line = '<li '.$token[1].'<a style="padding-left:18px; background:url('.$favicon.') 0px center no-repeat;" class="faviroll"'.$token[2];


			$newContent[] = $line;
		}

echo "<pre>";
print_r($_SERVER);

echo "<h1>".dirname($_SERVER['PHP_SELF'])."</h1>";


		return "<!-- Begin:FaviRoll Plugin -->\n".implode("\n",$newContent)."\n<!-- End:FaviRoll Plugin -->";
	}


	/**
	 * @return relative path to favicon cache
	 */
	function getRelativeCachePath() {

		$this->cacheurl();

echo "<h1>".dirname($_SERVER['PHP_SELF'])."</h1>";


	}



	/**
	 * Detect the URL to the favicon
	 */
	function locateIcon($url) {

		require_once( ABSPATH . 'wp-includes/class-snoopy.php');
		$snoopy = new Snoopy();
		$result = $snoopy->fetch($url);

		if (!$result)
			return false;

		$html = $snoopy->results;

		if (preg_match('/<link[^>]+rel="(?:shortcut )?icon"[^>]+?href="([^"]+?)"/si', $html, $matches)) {

			$linkUrl = html_entity_decode($matches[1]);
			if (substr($linkUrl, 0, 1) == '/') {
				$urlParts = parse_url($url);
				$faviconURL = $urlParts['scheme'].'://'.$urlParts['host'].$linkUrl;
			} else if (substr($linkUrl, 0, 7) == 'http://') {
				$faviconURL = $linkUrl;
			} else if (substr($url, -1, 1) == '/') {
				$faviconURL = $url.$linkUrl;
			} else {
				$faviconURL = $url.'/'.$linkUrl;
			}

		} else {

			$urlParts = parse_url($url);
			$faviconURL = $urlParts['scheme'].'://'.$urlParts['host'].'/favicon.ico';

		}

		if($this->validateURL($faviconURL))
			return $faviconURL;

		return false;
	}


	/**
	 * Validate an URL
	 * @param $url - the URL to be validate
	 * @return TRUE, if the URL is valid
	 */
	function validateURL( $url ) {

		$url_parts = @parse_url( $url );

		if ( empty( $url_parts["host"] ) )
			return false;

		if ( !empty( $url_parts["path"] ) ) {
			$documentpath = $url_parts["path"];
		} else {
			$documentpath = "/";
		}

		if ( !empty( $url_parts['query'] ) )
			$documentpath .= '?' . $url_parts['query'];

		$host = $url_parts['host'];
		$port = isset($url_parts['port']) ? $url_parts['port'] : 80;

		$socket = @fsockopen( $host, $port, $errno, $errstr, 30 );

		if ( !$socket )
			return false;

		fwrite ($socket, "HEAD ".$documentpath." HTTP/1.0\r\nHost: $host\r\n\r\n");

		$http_response = fgets( $socket, 22 );

		$responses = "/(200 OK)|(200 ok)|(30[0-9] Moved)/";
		if ( preg_match($responses, $http_response) ) {
			fclose($socket);
			return true;
		}

		return false;
	}


}
/* - EOC - end of class */


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

?>
