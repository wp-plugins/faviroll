<?php
/*
Faviroll - Main Class for the wordpress plugin "Faviroll"
Author: grobator
Version: latest
----------------------------------------------------------------------------------------
Copyright 2009 grobator  (email: http://www.grobator.de/kontakt)

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

class Faviroll {

	// Members
	var $prefix = '';
	var $cachedir = null;
	var $cacheurl = null;
	var $pluginurl = null;
	var $lastcheck = null;
	var $defaulticon = null;
	var $transparency = null;
	var $debug = false;


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

		$this->setPrefix();
		$this->initURLsAndDirs();

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
			$init && update_option('faviroll_revisit', 180);

		// default is enabled background tranparency.
		$this->debug = get_option('faviroll_debug');
		if (empty($this->debug))
			$init && update_option('faviroll_debug','off');

		// default is use faviroll/style.css.
		$css = get_option('faviroll_use_stylesheet');
		if (empty($css))
			$init && update_option('faviroll_use_stylesheet','on');


		$this->debug = (get_option('faviroll_debug') == 'on');
	}


	/**
	 * Remove all options with 'faviroll_' prefix from database.
	 */
	function removeSettings() {

		$opts = get_alloptions();

		foreach(array_keys((array) $opts) as $key) {
			if (preg_match('/^faviroll_/',$key)) {
				delete_option($key);
			}
		}

	}

	/**
	 * For WordPress MU. Any MU user has a blogid, which must be included into the cache file name.
	 */
	function setPrefix() {
		global $wpdb;

		if (isset($wpdb->base_prefix) && isset($wpdb->blogid))
			$this->prefix = $wpdb->base_prefix.$wpdb->blogid.'-';

	}


	/**
	 * @return the factory default favicon URL
	 */
	function getFavirollDefaultIcon() {
		return $this->pluginurl.'/faviroll-favicon.png';
	}


	/**
	 * Place a favicon into local cache directory
	 * @param &$linkref - Reference to WP Bookmark Link Object
	 * @param $verbose [optional] if TRUE echoing some working feedback
	 * @return TRUE, if cache file is created
	 */
	function putIconIntoCache(&$linkref,$verbose=false) {

		$bookmark = $linkref->link_url;

		if ($verbose) {
			echo "<p>${bookmark}....";
			ob_flush();
			flush();
		}

		extract($this->getURLinfo($bookmark),EXTR_OVERWRITE);
		if (!isset($basename))
			return false;

		$icopath = $this->cachedir."/$basename";

		// truncate cache icon file
		fclose(fopen($icopath,'w'));

		$icourl = $this->locateIcon($rooturl);
		if ($icourl) {

			if (!$this->loadClass('Ico','Ico.class.php'))
				return false;

			// Make shure that transparency flag is set
			if (is_null($this->transparency))
				$this->transparency = (get_option('faviroll_transparency') == 'on');

			$ico = new Ico($icourl,$this->transparency);

			// init
			$image = null;

			if ($ico->TotalIcons() > 0) {
				// Convert first ICO to PNG Format
				$image = $ico->GetIcon(0);
			} elseif (isset($ico->rawdata)) {

				$handle = @fopen($icopath,'wb');
				if ($handle !== false) {
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

		} // icon creation end


		$result = (is_file($icopath) && (filesize($icopath) > 0));

		if ($verbose) {
			echo '<br />&nbsp;<img src="'.(($result) ? $this->cacheurl."/$basename" : $this->defaulticon).'" />';

			if (!strlen($icourl))
				$icourl = 'fallback to default favicon';

			echo "&nbsp; ( $icourl )</p>";

			ob_flush();
			flush();
		}

		return $result;
	}


	/**
	 * @return reference to Hash-Array with the keys: [basename], [rooturl]
	 */
	function &getURLinfo($bookmark) {

		$result = array();

		$link = parse_url($bookmark);
		extract($link,EXTR_PREFIX_ALL|EXTR_OVERWRITE|EXTR_REFS,'lk');

		if (!isset($lk_path))
			$lk_path = '/';

		if ($lk_path != '/') {
			$pathinfo = pathinfo($lk_path);
			extract($pathinfo,EXTR_PREFIX_ALL|EXTR_OVERWRITE|EXTR_REFS,'pi');

			$lk_path = ($pi_basename == $pi_filename) ? '/' : dirname($lk_path).'/';
		}


		// cached favicons filenames are build with prefix (for WPMU) and MD5 checksum from the favicon
		$rooturl = '';
		if (isset($lk_scheme))
			$rooturl.= "${lk_scheme}://";
		if (isset($lk_host))
			$rooturl.= $lk_host;
		if (isset($lk_path))
			$rooturl.= $lk_path;

		// md5 cecksum of root-URL is name of favicon cache file
		$result['basename'] = $this->prefix.md5(strtolower($rooturl));
		$result['rooturl'] = $rooturl;

		return $result;
	}


	/**
	 * Loads the given filename if the needed class isn't defined already
	 * @return TRUE if the PHP class is already present
	 */
	function loadClass($class,$filename) {

		if (!class_exists($class))
			require_once($filename);

		return class_exists($class);
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
	 * @param $dropLastCheck [optional] If TRUE, faviroll_lastcheck will be removed from database
	 */
	function flush($dropLastCheck=false) {

		$this->lastcheck = 0;

		if ($dropLastCheck) {
			delete_option('faviroll_lastcheck');
		} else {
			update_option('faviroll_lastcheck',$this->lastcheck);
		}

		$withsize = false;
		$fullpath = true;
		foreach($this->getCacheIcons($withsize,$fullpath) as $filename) {
			if (is_file($filename))
				@unlink($filename);
		}

		return ($this->cacheIconsCount($withsize) == 0);
	}


	/**
	 * @param $withsize [optional] If TRUE skip all "zero size" files
	 * @param $fullpath [optional] If TRUE full filepath is returned, instead just the basename
	 * @return reference to List-Array with all favicon file basenames from cache directory.
	 */
	function &getCacheIcons($withsize=true,$fullpath=false) {

		$result = array();

		// MD5 Strings are always 32 characters f.e. cc33ac77c986e91fb30604dd516a61c7
		$pattern = $this->cachedir.'/'.$this->prefix.'????????????????????????????????';
		$items = @glob($pattern);
		if ($items === false)
			return $result;

		foreach($items as $item) {
			$basename = basename($item);

			// just collect file names with alphanumeric characters
			if (is_file($item) && preg_match('/^[0-9A-Z_\-a-z]+$/',$basename)) {
				if ($withsize && filesize($item) == 0)
					continue;

				$result[] = ($fullpath) ? $item : $basename;
			}
		}

		return $result;
	}


	/**
	 * @param $withsize [optional] If TRUE skip all "zero size" files
	 * @return The count of icons in cache directory.
	 */
	function cacheIconsCount($withsize=true) {
		return count($this->getCacheIcons($withsize));
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
FAVIROLL is (re)building the favicons from your blogroll links.<br />
This may be take some time... stay tuned, please!<br />
<br />
Cache directory = '.$this->cachedir.'<br /></b>';


		# ---------- next stage
		foreach(get_bookmarks() as $link) {
			$this->putIconIntoCache($link,true);
		}

		update_option('faviroll_lastcheck',time());

		echo '</p></div><script type="text/javascript">var t = document.getElementById("message"); if (t){ t.style.display = "none"; }</script>';
		ob_flush();
		flush();

		return true;
	}


	/**
	 * @return initialize member variables for dirnames and urls.
	 */
	function initURLsAndDirs() {

		$cache = '/cache';

		$this->cachedir = $this->normalize(plugin_dir_path(__FILE__).$cache);

		// -------------- [Plugin URL ermitteln ] --------------
		$this->pluginurl = trim(rtrim(plugin_dir_url(__FILE__),'/'));

		$elems = parse_url($this->pluginurl);

		if (!isset($elems['path']))
			return false;

		$pURL = $elems['path'];

		// -------------- [Request URL analysieren] --------------

		if (!isset($_SERVER['REQUEST_URI']))
			return false;

		$request = parse_url($_SERVER['REQUEST_URI']);
		if (!isset($request['path']))
			return false;


		$cURL = trim(rtrim($request['path'],'/'));

		// ------------- [try to shorten url paths on user sites] -------------
		if (!is_admin()) {
			$relpath = $this->getRelativePluginPath($cURL,$pURL,$request['path']);
			if ($relpath)
				$this->pluginurl = $relpath;
		}

		$this->cacheurl = $this->pluginurl.$cache;
		return true;
	}


	/**
	 * @return relative URL to plugin path in condition to current request URL.
	 */
	function getRelativePluginPath($current_url, $plugin_url, $request_path) {

		$result = false;

		$cElems = explode('/',$current_url);
		$pElems = explode('/',$plugin_url);


		// eleminate identically path elements from both arrays
		while (count($cElems) > 0) {
			if ($cElems[0] == $pElems[0]) {
				array_shift($cElems);
				array_shift($pElems);
			} else {
				break;
			}
		}

		if (count($cElems) == 0) {
			$result = join('/',array_merge(array('.'),$pElems));
		} else {

			// Wenn der Request Path nicht mit Slash endet, entferne den letzten Namen,
			// da dann das Verzeichnis darüber gilt. Quasi der "dirname()"
			if (substr($request_path,-1) != '/')
				array_pop($cElems);

			$relpath = array();
			foreach ($cElems as $item) {
				$relpath[] = '..';
			}

			$result = join('/',array_merge($relpath,$pElems));
		}

		return $result;
	}


	/**
	 * Wandelt Backslashes einheitlich in Slashes um.
	 * Säubert den Pfad von "/" Dubletten
	 * @param $path string contains the pathname
	 */
	function normalize($path) {
		$result = str_replace('\\','/',$path);

		// Alle Slashes, die mehr als 1x vorkommen,
		// zu einem "zusammendampfen".
		//
		$result = preg_replace('-/{2,}-','/',$result);

		// evtl. endende Slashes entfernen.
		//
		return rtrim($result,'/');
	}


	/**
	 * Check write permissions on cache directory.
	 * @return error message if cache directory is not writable, or the string is given into method
	 */
	function get_message($othermsg=null) {

		// non-admin
		if (!is_admin() || $this->can_write_cache())
			return $othermsg;

		return "<div class='updated fade'><b>CAUTION</b>, no file permission to create icon-cache.
<p>You have to change the permissions.<br />
Use your ftp client, or the following command to fix it:<br />
<br />
<code># chmod 0775 ".$this->cachedir."</code></p></div>";

	}


	/**
	 * @return TRUE if cache directory is writable
	 */
	function can_write_cache() {
		return $this->is__writable(rtrim($this->cachedir,'/').'/');
	}


	/**
	 * from http://de3.php.net/is_writable
	 * Since looks like the Windows ACLs bug "wont fix" (see http://bugs.php.net/bug.php?id=27609) I propose this alternative function:
	 * For directory check $path must end with a slash
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
	 * Detect the URL to the favicon
	 * @param $i_url Website URL which (hopefully) contains the favicon href
	 */
	function locateIcon($i_url) {

		if (!$this->loadClass('Snoopy',ABSPATH.WPINC.'/class-snoopy.php'))
			return false;

		$snoopy = new Snoopy();
		$result = $snoopy->fetch($i_url);

		if (!$result)
			return false;

		// minimal requirements are okay,
		// now it's worth to going foreward

		// Get website html code
		$html = $snoopy->results;

		// get segments from main-url
		$url_elems = parse_url($i_url);
		extract($url_elems,EXTR_PREFIX_ALL|EXTR_OVERWRITE|EXTR_REFS,'url');

		// Default: hard coded location to "/favicon.ico"
		$faviconURL = "${url_scheme}://${url_host}/favicon.ico";
		$typeOfURL = "hard coded to: $faviconURL";

		// scan html code for things like: <link rel="shortcut icon" href="...." />
		if (preg_match('/<link[^>]+rel=["\'](?:shortcut )?icon["\'][^>]+?href=["\']([^"\']+?)["\']/si', $html, $matches)) {

			$codeURL = html_entity_decode($matches[1]);
			$link = parse_url($codeURL);
			extract($link,EXTR_PREFIX_ALL|EXTR_OVERWRITE|EXTR_REFS,'lk');

			// Im Favicon Href ist eine voll qualifizierte URL angegeben
			if (isset($lk_scheme) && isset($lk_host)) {

				$typeOfURL = 'full qualified URL';
				$faviconURL = $codeURL;
			} elseif (isset($lk_path)) {

				$faviconURL = "${url_scheme}://${url_host}";
				if ($lk_path{0} == '/') {

					// path is absolute (has leading slash)
					$faviconURL.= $lk_path;
					$typeOfURL = 'absolute PATH on server';
				} else {

					// path is relative to 'i_url' path
					$faviconURL.= rtrim($url_path,'/').'/'.$lk_path;
					$typeOfURL = 'relative PATH on server';
				}
			}
		}

		if($this->validateURL($faviconURL))
			return $faviconURL;

		return false;
	}


	/**
	 * Validate an URL
	 * @param $url - the URL to be validate
	 * @return TRUE, if URL is valid and online
	 */
	function validateURL( $url ) {

		$url_parts = @parse_url( $url );

		if ( empty( $url_parts['host'] ) )
			return false;

		if ( !empty( $url_parts['path'] ) ) {
			$documentpath = $url_parts['path'];
		} else {
			$documentpath = '/';
		}

		if ( !empty( $url_parts['query'] ) )
			$documentpath .= '?' . $url_parts['query'];

		$host = $url_parts['host'];
		$port = isset($url_parts['port']) ? $url_parts['port'] : 80;

		$socket = @fsockopen( $host, $port, $errno, $errstr, 30 );

		if ( !$socket )
			return false;

		fwrite($socket, 'HEAD '.$documentpath." HTTP/1.0\r\nHost: $host\r\n\r\n");
		$http_response = fgets($socket,6);
		fclose($socket);

		return ($http_response == 'HTTP/');
	}


	/**
	 * Patcht fremde Plugins, um die Zusammenarbeit mit Faviroll herzustellen
	 * @param $plugindir Plugin directory of the foreign plugin
	 * @param boolean $activate TRUE = Faviroll plugin is activated, FALSE = Faviroll plugin is disabled
	 */
	function patchPlugin($plugindir,$activate=false) {

		$result = null;

		foreach (get_plugins() as $path => $data) {
			if (strpos($path,$plugindir) === 0) {
				$pluginfile = $path;
				break;
			}
		}

		if (!isset($pluginfile))
			return true;

		$pluginfile = WP_PLUGIN_DIR."/$pluginfile";
		if (!is_file($pluginfile) || !is_writable($pluginfile))
			return true;


		switch ($plugindir) {
			case 'wp-render-blogroll-links':
				// In diesem Plugin die Zeile 164 patchen.

				$lines = @file($pluginfile);
				if ($lines === false || count($lines) < 165)
					return true;

				$line = trim($lines[164]);

				// # Die betreffende Zeile erkennen.
				if (!preg_match("/(apply_filters)+.*('wp_list_bookmarks_plus')+/",$line))
					return true;

				break;
			default:
				break;
		}

		if ($activate) {

			switch ($plugindir) {
				case 'wp-render-blogroll-links':

					// Patch ausführen, Kommentar entfernen
					$lines[164] = "	".preg_replace('#^[/\s]+#','',$line)."\n";
					$handle = @fopen($pluginfile,'w');
					if ($handle !== false) {
						fwrite($handle,join('',$lines));
						fflush($handle);
						fclose($handle);
					}

					break;
				default:
					break;
			}
		} else {
			switch ($plugindir) {
				case 'wp-render-blogroll-links':

					// Kommentar wieder davorschreiben
					$lines[164] = "	// $line\n";

					$handle = @fopen($pluginfile,'w');
					if ($handle !== false) {
						fwrite($handle,join('',$lines));
						fflush($handle);
						fclose($handle);
					}

					break;
				default:
					break;
			}

		}

		return true;
	}






// -----------------------Non - Admin Functions -----------------------------------------------------------------------

	/**
	 * Apply the favicons to the blogroll hyperlinks
	 * @param $content - The widget content
	 */
	function apply($content) {

		// get default icon from database
		$default_favicon = get_option('faviroll_default_favicon');

		// get list of cached icons
		$cacheIcons = $this->getCacheIcons();

		// split bookmarks in lines
		$lines = explode("\n",$content);

		$newContent = array();

		// analyze bookmark lines
		foreach($lines as $line) {

			$line = trim($line);

			if (!(bool) @preg_match('/a[\s]+[^>]*?href[\s]?=[\s\"\']+(.*?)[\"\']+.*?>([^<]+|.*?)?<\/a>/i', $line, $matches)) {
				// overhead stuff
				$newContent[] = $line;
				continue;
			}

			if (count($matches) < 3) {
				// overhead stuff
				$newContent[] = $line;
				continue;
			}

			$urlInfo = $this->getURLinfo($matches[1]);
			extract($urlInfo,EXTR_OVERWRITE);

			// Es konnte keine Checksumme ermittelt werden
			// also einfach die Zeile as-is übernehmen
			if (!isset($basename)) {
				$newContent[] = $line;
				continue;
			}

			// set favicon from cache or fallback to default
			$favicon = (in_array($basename,$cacheIcons)) ? $this->cacheurl."/$basename" : $default_favicon;

			$token = preg_split('/<(li(\s*)|a(\s*))/',$line);

			if (count($token) == 3)
				$line = '<li class="faviroll"><a style="padding-left:18px; background:url('.$favicon.') 0px center no-repeat;" class="faviroll" '.$token[2];

			$newContent[] = $line;
		}

		return "<!-- Begin:FaviRoll Plugin -->\n".implode("\n",$newContent)."\n<!-- End:FaviRoll Plugin -->";
	}

}
/* - EOC - end of class */
?>
