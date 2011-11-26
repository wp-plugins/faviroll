<?php
/*
Faviroll - Widget Class
Author: andurban.de
Version: latest
----------------------------------------------------------------------------------------
Copyright 2009-2011 andurban.de  (email: http://www.andurban.de/kontakt)

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


TODO:
+ wp-nonce ajax

*/

require_once('FavirollWorker.class.php');

class Faviroll extends WP_Widget {

	// Members
	var $worker = null;
	var $cacheurl = null;
	var $pluginurl = null;
	var $opts = array();


	/**
	 * Constructor
	 */
	function __construct() {

		$this->worker = new FavirollWorker($this->initPrefix());
		$this->initURLsAndDirs();
		
		$this->opts = get_option('faviroll');
		if (!$this->opts)
			$this->opts = array();

		parent::__construct(false, $name = 'Faviroll', $this->opts);
	}


	/**
	 * @return initialize member variables for dirnames and urls.
	 */
	function initURLsAndDirs() {

		// -------------- [Plugin URL ermitteln ] --------------
		$this->pluginurl = trim(rtrim(plugin_dir_url(__FILE__),'/')).'/';

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

		$this->cacheurl = $this->pluginurl.basename($this->getCacheDir());

		return true;
	}


	/**
	 * Get an option value
	 * @return option value, or NULL
	 */
	function getopt($k) {
		return (isset($this->opts[$k])) ? $this->opts[$k] : null;
	}


	/**
	 * Apply the favicons to the blogroll hyperlinks
	 * @param $content - The widget content
	 */
	function apply($content) {

		$nlines = array();
		
		// split bookmarks in lines
		$olines = explode("\n",$content);

		$favicons = $this->getFaviconsByURL();
		
		// analyze bookmark lines
		foreach($olines as $line) {

			$line = trim($line);

			// overhead stuff pass thru
			if (!(bool) @preg_match('/a[\s]+[^>]*?href[\s]?=[\s\"\']+(.*?)[\"\']+.*?>([^<]+|.*?)?<\/a>/i', $line, $matches)) {
				$nline[] = $line;
				continue;
			}

			// overhead stuff pass thru
			if (count($matches) < 3) {
				$nline[] = $line;
				continue;
			}

			$link_url = $matches[1];
			$key = html_entity_decode($link_url);
			$favicon = (isset($favicons[$key])) ? $favicons[$key] : null;

			 $token = preg_split('/<(li(\s*)|a(\s*))/',$line);

			if (count($token) == 3) {
				// evtl. vorhandene <IMG>-Tags entfernen
				$token = strip_tags($token[2]);
				$line = '<li class="faviroll"><a class="faviroll" style="background:url('.$favicon.') 0px center no-repeat;" '.$token.'</a></li>';
			}

			$nline[] = $line;
		}

		return "<!-- Begin:FaviRoll-->\n".implode("\n",$nline)."\n<!-- End:FaviRoll-->";
	}


	/**
	 * For WordPress MU. Any MU user has a separate blogid, which must be included into the cache file name.
	 */
	function initPrefix() {
		global $wpdb;

		$result = '';

		if (isset($wpdb->base_prefix) && isset($wpdb->blogid))
			$result = $wpdb->base_prefix.$wpdb->blogid.'-';

		return $result;
	}


	/**
	 * ®return cache url
	 */
	function getCacheURL() {
		return $this->cacheurl;
	}


	/**
	 * @return prefix fo a cache file name.
	 */
	function getCacheFilePrefix($customColumn=false) {
		return $this->worker->getCacheFilePrefix($customColumn);
	}
	
	
	/**
	 * @return server path of icon cache directory
	 */
	function getCacheDir() {
		return $this->worker->getCacheDir();
	}


	/**
	 * @see
	 */
	function getDefaultBasename() {
		return $this->getopt('default-icon');
	}

	/**
	 * @see
	 */
	function getFactoryBasename() {
		return $this->worker->getFactoryBasename();
	}

	/**
	 * @see 
	 */
	function putIconIntoCache($bookmark) {
		return $this->worker->putIconIntoCache($bookmark);
	}

	/**
	 * @see 
	 */
	function getHomeURL() {
		return $this->worker->getHomeURL();
	}

	/**
	 * @see 
	 */
	function &getURLinfo($bookmark,$customColumn=false) {
			return $this->worker->getURLinfo($bookmark,$customColumn);
	}


	/**
	 * get array with the default icon at first following by get_bookmarks()
	 * @see http://codex.wordpress.org/Template_Tags/get_bookmarks
	 */
	function &getBookmarks() {

		// set "virtual" default link on the top of the table
		$default = new stdClass();
		$default->link_id = 0;
		$default->basename = $this->getDefaultBasename();
		$default->link_url = $this->getHomeURL();
		$default->link_image = $default->basename;
		$default->link_name ='Default Icon';

		// append the list of bookmarks after default icon
		$result = array_merge(array($default), get_bookmarks() );
		
		return $result;
	}
	
	/**
	 * @param $withsize [optional] If TRUE skip all "zero size" files
	 * @param $fullpath [optional] If TRUE full filepath is returned, instead just the basename
	 * @return reference to List-Array with all favicon file basenames from cache directory.
	 */
	function &getCacheIcons($withsize=true,$fullpath=false) {

		$result = array();

		$zeroFilesOnly = ($withsize === 0);

		// MD5-Strings are always 32 characters f.e. cc33ac77c986e91fb30604dd516a61c7
		// because of the flexible part of custom-columns there is a "*" before the 32 questions marks
		$pattern = $this->getCacheDir().'/'.$this->getCacheFilePrefix('*').'????????????????????????????????';

		$items = @glob($pattern);
		if ($items === false)
			return $result;

		foreach($items as $item) {
			$basename = basename($item);

			// just collect file names with alphanumeric characters
			if (is_file($item) && preg_match('/^[0-9A-Z_\-a-z]+$/',$basename)) {

				$fsize = filesize($item);

				// Nur die Null-Byte Dateien registrieren
				if ($zeroFilesOnly) {
				 if ($fsize == 0)
				 		$result[] = ($fullpath) ? $item : $basename;
					continue;
				}

				if ($withsize && $fsize == 0)
					continue;

				$result[] = ($fullpath) ? $item : $basename;
			}
		}

		return $result;
	}


	/**
	 * 
	 */
	function &getFaviconsByURL() {
		
		$result = array();

		$bms = $this->getBookmarks();
		$default = array_shift($bms);

		$cacheIcons = $this->getCacheIcons();
		$cacheurl = $this->getCacheURL();

		foreach ($bms as $bm) {

			$customColumn=false;
			extract($this->getURLinfo($bm->link_url, $customColumn), EXTR_OVERWRITE);
			
			if (!(in_array($basename,$cacheIcons)))
				$basename = $default->basename;
			
			$link_image = strstr($bm->link_image,'faviroll-'); // alles links vom String "faviroll-" entfernen
			if (empty($link_image) || $link_image === false) {
				$favicon = $basename;
			} else {
				$favicon = $link_image;
			}

			$key = html_entity_decode($bm->link_url);
			$result[$key] = "$cacheurl/$favicon";
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
			$result = implode('/',array_merge(array('.'),$pElems));
		} else {

			// Wenn der Request Path nicht mit Slash endet, entferne den letzten Namen,
			// da dann das Verzeichnis darüber gilt. Quasi der "dirname()"
			if (substr($request_path,-1) != '/')
				array_pop($cElems);

			$relpath = array();
			foreach ($cElems as $item) {
				$relpath[] = '..';
			}

			$result = implode('/',array_merge($relpath,$pElems));
		}

		return $result;
	}


} /* end of class */
?>
