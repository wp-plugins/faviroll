<?php
/*
Faviroll - Main Class for the wordpress plugin "Faviroll"
Author: grobator
Version: latest
----------------------------------------------------------------------------------------
Copyright 2009-2010 grobator  (email: http://www.grobator.de/kontakt)

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
	var $opts = array();


	/**
	 * PHP4 Constructor. Wrapper for __construct()
	 */
	function Faviroll() {
		$args = func_get_args();
		call_user_func_array(array(&$this, '__construct'), $args);
	}


	/**
	 * PHP5 Constructor
	 */
	function __construct() {
		$this->setPrefix();
		$this->initURLsAndDirs();

		$this->opts = get_option('faviroll');
		if (!$this->opts)
			$this->opts = array();

	}


	/**
	 * Get an option value
	 * Notice: setopt() is in FavirollAdmin.class
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

		// get list of cached icons
		$cacheIcons = $this->getCacheIcons();

		// split bookmarks in lines
		$lines = explode("\n",$content);

		$default_favicon =  $this->getopt('default_favicon');
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

		return "<!-- Begin:FaviRoll-->\n".implode("\n",$newContent)."\n<!-- End:FaviRoll-->";
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
	 * @param $withsize [optional] If TRUE skip all "zero size" files
	 * @param $fullpath [optional] If TRUE full filepath is returned, instead just the basename
	 * @return reference to List-Array with all favicon file basenames from cache directory.
	 */
	function &getCacheIcons($withsize=true,$fullpath=false) {

		$result = array();

		$zeroFilesOnly = ($withsize === 0);

		// MD5 Strings are always 32 characters f.e. cc33ac77c986e91fb30604dd516a61c7
		$pattern = $this->cachedir.'/'.$this->prefix.'????????????????????????????????';
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
	 * @param $withsize [optional] If TRUE skip all "zero size" files
	 * @return The count of icons in cache directory.
	 */
	function cacheIconsCount($withsize=true) {
		return count($this->getCacheIcons($withsize));
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


} /* end of class */
?>
