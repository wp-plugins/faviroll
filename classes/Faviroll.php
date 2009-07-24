<?php
/*************************************************
Faviroll - Main Class for the wordpress plugin "Faviroll"
Author: grobator
Copyright (c): 2009, all rights reserved
Version: latest

 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*************************************************/

require_once('Utils.php');

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

		// Veraltetes cache Verzeichnis abräumen
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

		require_once('Ico.php');

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

		$relPath = $this->getRelativeCachePath();

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
?>
