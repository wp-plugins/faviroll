<?php
/*
FavirollWorker - Class for wordpress plugin "Faviroll" backend
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
*/

class FavirollWorker {

	var $homeurl = null;
	var $wpmu_prefix = null;
	var $cachedir = null;
	var $plugindir = null;
	var $factory_basename = null;


	/**
	 * Constructor
	 */
	function __construct($wpmu_prefix) {
		$this->wpmu_prefix = $wpmu_prefix;
		$this->plugindir = $this->normalize(dirname(__FILE__));
		$this->cachedir = $this->plugindir.'/cache/';

		$this->initHomeURL();

		$customColumn = 1;
		$this->factory_basename =  $this->getCacheFilePrefix($customColumn) . $this->getMD5($this->getHomeURL());		
	}

	#################
  #               #
	#    SETTER     #
	#               #
	#################
	

	/**
	 * Init member: homeurl by resoving self url from this wordpress installation
	 */	
	function initHomeURL() {

		$proto = explode('/', $_SERVER['SERVER_PROTOCOL']);
		$proto = strtolower(array_shift($proto));

		if (isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] == 'on')
			$proto.= 's';
		
		$port = ($_SERVER['SERVER_PORT'] == 80) ? '' : (':'.$_SERVER['SERVER_PORT']);

    $url = $proto.'://'.$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI'];
		$elems = explode('wp-admin',$url);
		$this->homeurl = array_shift($elems);

		return true;
	}
	
	
	#################
  #               #
	#    GETTER     #
	#               #
	#################


	/**
	 * Getter:
	 * @param $customColumn - colum number of the custom icon. If FALSE, the site icon prefix will be returned
	 * @return cache icon file prefix
	 */
	function getCacheFilePrefix($customColumn=false) {
		$result = array('faviroll');

		if (is_numeric($customColumn)) {
			$result[] = $customColumn;
		} elseif ($customColumn === '*') {
			$result[0].=$customColumn;
		}

		$result[] = $this->getWPMUPrefix();

		return implode('-',$result);
	}


	/**
	 * Getter for member: wpmu_prefix
	 */
	function getWPMUPrefix() {
		return $this->wpmu_prefix;
	}


	/**
	 * Getter for member: cachedir
	 */
	function getCacheDir() {
		return $this->cachedir;
	}


	/**
	 * Getter for member: plugindir
	 */
	function getPluginDir() {
		return $this->plugindir;
	}

	/**
	 * @see
	 */
	function getFactoryBasename() {
		return $this->factory_basename;
	}

	/**
	 * 
	 */
	function getHomeURL() {
		return $this->homeurl;
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
	 * make shure that default icon exists
	 */
	function initDefaultIcon($reset=false) {

		$this->initFactoryIcon($reset);
		$homeurl = $this->getHomeURL();
		
		$customColumn = false;
		extract($this->getURLinfo($homeurl,$customColumn), EXTR_OVERWRITE);
		if (!isset($basename))
			return false;

		$cacheDir = $this->getCacheDir();
		$default_cache = $cacheDir.$basename;
		if (!$reset AND file_exists($default_cache))
			return $basename;

		$isOk = $this->putIconIntoCache($homeurl,$customColumn);
		if (!$isOk)
			return false;
	
		return $basename;
	}
	

	/**
	 * 
	 */
	function initFactoryIcon($reset=false) {

		$cacheDir = $this->getCacheDir();

		// name of favicon cache file basename
		$basename = $this->factory_basename;

		$factory_cache = $cacheDir.$basename;
		if (file_exists($factory_cache)) {
			if($reset) {
			 	@unlink($factory_cache);
			} else {
				return $basename;
			}
		}

		// Make shure that default icon cache is up to date
		$factory_src = $this->getPluginDir().'/img/default.png';
		if (!file_exists($factory_src))
			return false;

		@copy($factory_src, $factory_cache);
		@clearstatcache();

		return file_exists($factory_cache) ? $basename : false;
	}


	/**
	 * Place a favicon into local cache directory
	 * @param $bookmark - Website-Hyperlink
	 * @param $customColumn - 
	 * @return TRUE, if cache file is created
	 */
	function putIconIntoCache($bookmark,$customColumn=false) {

		extract($this->getURLinfo($bookmark,$customColumn), EXTR_OVERWRITE);
		if (!isset($basename))
			return false;

		$icourl = $this->locateIcon($rooturl);
			
		return $this->makeIcon($icourl,$basename);
	}


	/**
	 * Create an Icon Image into cache directory
	 * @param $icourl the icon image
	 * @param $basename the name of the cache file
	 */
	function makeIcon($icourl,$basename) {

		if (!$icourl)
			return false;
		
		$icopath = $this->getCacheDir().$basename;
		fclose(fopen($icopath,'w'));		// truncate cache icon file

		if (!$this->loadClass('Ico','Ico.class.php'))
			return false;

		$ico = new Ico($icourl);

		// init
		$image = null;

		if ($ico->TotalIcons() > 0) {
			// Convert first ICO to PNG Format
			$image = $ico->GetIcon(0);
		} elseif ($ico->imagetype) {

			// GD-Image wurde aus NICHT-ICO Format erzeugt
			// Dieses wird wie ein "normales" Ico weiterverarbeitet.
			//
			if ($ico->otherformatimage) {
				$image = $ico->otherformatimage;
			} else {
				// Falls das Rohformat nicht in ein Image-Objekt eingelesen werden konnte,
				// schreibe die Rohdaten 1 zu 1 heraus.
				//
				if ($ico->rawdata) {
					$fp = @fopen($icopath,'wb');
					if ($handle !== false) {
						fwrite($handle,$ico->rawdata);
						fflush($handle);
						fclose($handle);
					}
				}
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
			imagepng($image,$icopath);
			imagedestroy($image);
		}

		return (is_file($icopath) && (filesize($icopath) > 0));		
	}


	/**
	 * Detect the URL to the favicon
	 * @param $i_url Website URL which (hopefully) contains the favicon href
	 */
	function locateIcon($i_url) {

		if (!$this->loadClass('Snoopy','Snoopy.class.php'))
			return false;

		$snoopy = new Snoopy();
		$snoopy->read_timeout = 5;
		$result = @$snoopy->fetch($i_url);

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
	 * load and analyze an image file from url
	 * @param $img_src the image url
	 */
	function locateCustomImageOrFallbackFavicon($i_url) {

		if (!$this->loadClass('Snoopy','Snoopy.class.php'))
			return false;
			
		$snoopy = new Snoopy();
		$snoopy->read_timeout = 5;
		$result = @$snoopy->fetch($i_url);

		if (!$result)
			return false;

		// minimal requirements are okay,
		// now it's worth to going foreward

		// Get website html code
		$data = $snoopy->results;
		if (!$data)
			return false;

		if ($this->is_text($data)) {
			extract($this->getURLinfo($i_url), EXTR_OVERWRITE);
			return (isset($rooturl)) ? $this->locateIcon($rooturl) : false;
		}

		return $i_url;
	}

			
	/**
	 * @see http://www.patshaping.de/hilfen_ta/codeschnipsel/php-binaryfile.htm
	 */
	function is_text(&$s) {

		if(strpos($s,"\0") === true) return false;
		if(!$s)                      return false;

		$text_characters = array_merge(array_map('chr',range(32,127)),array("\012","\015","\t","\b"));

		$t = $s;

		foreach($text_characters as $text_character) {
			$t = str_replace($text_character,'',$t);
		}

		return (strlen($t) / strlen($s) < 0.3);
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
	 * @param $url URL which generates an unique MD5 Key per Site
	 */
	function getMD5($url) {
		return md5( strtolower( html_entity_decode( $url ) ) );
	}


	/**
	 * @return reference to Hash-Array with the keys: [basename], [rooturl]
	 */
	function &getURLinfo($_bookmark,$_customColumn=false) {

		$result = array();

		$link = parse_url($_bookmark);
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
		$result['basename'] = $this->getCacheFilePrefix($_customColumn).$this->getMD5($_bookmark);
		$result['rooturl'] = $rooturl;

		return $result;
	}

} /* end of class */
?>