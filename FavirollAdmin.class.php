<?php
/*
FavirollAdmin - Class for wordpress plugin "Faviroll" backend
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

require_once('Faviroll.class.php');
class FavirollAdmin extends Faviroll {

	/**
	 * PHP4 Constructor. Wrapper for __construct()
	 */
	function FavirollAdmin() {
		$args = func_get_args();
		call_user_func_array(array(&$this, '__construct'), $args);
	}

	/**
	 * PHP5 Constructor
	 */
	function __construct() {

		@ini_set('output_buffering', 0);
		@ini_set('zlib.output_compression', 0);
		@ini_set('implicit_flush', 1);

		parent::__construct();

		$this->initOptions();
		$this->migrate_before048();
	}


	/**
	 * The setting page in WordPress backend
	 */
	function settings() {

		$message = null;
		// sofortige Übernahme der mit '_' beginnenden Werte
		$is_renew = isset($_POST['_renew']);
		$is_remove = isset($_POST['_remove']);
		$nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : null;

		if (wp_verify_nonce($nonce, 'my-nonce')) {

			// Post Variablen mit '_' am Anfang entfernen, alle anderen Werte trimmen
			if (isset($_POST)) {
				foreach ($_POST as $k => $v) {
					if ($k[0] == '_') {
					 unset($_POST[$k]);
					} else {
						$_POST[$k] = trim($v);
					}
				}
			}

			if($is_remove) {

				$this->flush(true);
				$message = 'Plugin settings and cached icons removed.<br /><a href="./plugins.php?plugin_status=active">You may disable the plugin now</a>';

			} else {

				$update = array();
				// Wie war bisher die Transparenz gesetzt?
				$was_transparency = ($this->getopt('transparency'));

				if (isset($_POST['default_favicon']))
					$update['default_favicon'] = $_POST['default_favicon'];

				if (isset($_POST['revisit']))
					$update['revisit'] = (int)$_POST['revisit'];

				$update['transparency'] = isset($_POST['transparency']);
				$update['use_stylesheet'] = isset($_POST['use_stylesheet']);
				$update['debug'] = isset($_POST['debug']);

				$this->setopt(&$update);

				$message = 'Settings updated';
			}
		}

		// @see http://codex.wordpress.org/Function_Reference/wp_create_nonce
		$nonce = wp_create_nonce('my-nonce');

		extract($this->opts,EXTR_OVERWRITE);

		$is_debug =
		$is_transparency =
		$is_use_stylesheet = false;

		if ($is_remove) {
			$revisit = null;
			$default_favicon = null;
		} else {
			$is_debug          = (bool)$debug;
			$is_transparency   = (bool)$transparency;
			$is_use_stylesheet = (bool)$use_stylesheet;

			if (isset($was_transparency) && ($is_transparency != $was_transparency)) {
				$message = 'Transparency switched';
				$is_renew = true;
			}

			if($is_renew)
				$this->reset();
		}

		if (!is_null($message))
			$message = "<div class='updated fade below-h2' id='message'><p>$message</p></div>";


		$cacheCount = $this->cacheIconsCount();

		echo '<div class="wrap">
  <div id="icon-options-general" class="icon32"><br /></div>
  <h2>'.__('FAVIcons for blogROLL', 'faviroll').' Settings</h2>'.$this->get_message($message).'<br />
   [ '.$cacheCount.' ] links favirollized'.$this->sitesWithoutFavionMsg().'
	 <div style="float: right;margin:10px;padding-right:50px;">
	  <a href="http://donate.grobator.de/"><img src="https://www.paypal.com/en_GB/i/btn/btn_donate_SM.gif" border="0" alt="donate" title="Donations welcome" /></a
	 </div>
   <form id="faviroll" name="faviroll" method="post">
    <table class="form-table" summary="">
     <tr>
      <td style="padding-top:0px;" width="140">URL for FavIcon default:</td>
      <td style="padding-top:0px;"><input type="text" name="default_favicon" size="80" value="'.$default_favicon.'" /><br />If the favicon on the link is missing this will be shown instead</td>
     </tr>
     <tr>
      <td style="padding-top:0px;">revisit FavIcons every:</td>
      <td style="padding-top:0px;"><input type="text" name="revisit" size="4" value="'.$revisit.'" /> days</td>
     </tr>
     <tr>
      <td style="padding-top:0px;">background transparency:</td>
      <td style="padding-top:0px;"><input type="checkbox" name="transparency" value="on"'.($is_transparency ? ' checked="checked"' : null).' /></td>
     </tr>
     <tr>
      <td style="padding-top:0px;">use faviroll/style.css:</td>
      <td style="padding-top:0px;">
        <input type="checkbox" name="use_stylesheet" value="on"'.($is_use_stylesheet ? ' checked="checked"' : null).' />
         &nbsp;&nbsp;&nbsp;
        <label for="use_stylesheet"><a href="plugin-editor.php?file=faviroll/style.css&plugin=faviroll/faviroll.php" title="Edit the faviroll css-styles">Edit faviroll stylesheet</a></label>
      </td>
     </tr>
	   <tr>
	      <td style="padding-top:0px;" colspan="2" style="line-height:5px;padding:0px;"><hr size="1" width="90%" /></td>
	   </tr>
     <tr>
      <td><strong>Actions</strong></td>
     </tr>
     <tr>
      <td style="padding-top:0px;">(re)build FavIcon cache:</td>
      <td style="padding-top:0px;"><input type="checkbox" name="_renew" value="on"'.(($cacheCount == 0 && !$is_remove) ? ' checked="checked"' : null).' /></td>
     </tr>
     <tr>
      <td style="padding-top:0px;" title="This will remove plugin settings from database and drop the favicon cache">remove settings:</td>
      <td style="padding-top:0px;"><input type="checkbox" id="_remove" name="_remove" value="on" />
     </tr>
';

/*
<tr>
<td title="Write debug informations as comments into the HTML code">Debug mode:</td>
<td><input type="checkbox" name="debug" value="on" '.($is_debug ? ' checked="checked"' : null).' /> &nbsp; ( just for Developers, normally switch off )
</tr>
*/

		echo '     <tr>
      <td class="submit"><input type="submit" id="_submit" name="_submit" value="Submit" /><input type="hidden" name="_wpnonce" value="'.$nonce.'" /></td>
     </tr>
    </table>
   </form>
  </div>
';

	}


	/**
	 * @param $withsize [optional] If TRUE skip all "zero size" files
	 * @return The count of icons in cache directory.
	 */
	function sitesWithoutFavionMsg() {
		$zeroLen = 0;
		$zeroCount = count($this->getCacheIcons($zeroLen));

		if ($zeroCount)
			return ", [ $zeroCount ] links uses the default favicon</strong>";

		return null;
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
	 * @param $remove - TRUE remove options from DB too
	 */
	function flush($removeopts=false) {

		if ($removeopts)
			$this->setopt('drop');

		$withsize = false;
		$fullpath = true;
		foreach($this->getCacheIcons($withsize,$fullpath) as $filename) {
			if (is_file($filename))
				@unlink($filename);
		}

		return ($this->cacheIconsCount($withsize) == 0);
	}


	/**
	 * Check revisit date and refresh cached favicons if timeout is occured.
	 */
	function revisit() {

		$this->setopt(array('lastcheck' => time()));

		// Max. Laufzeit auf 10 Min. setzen
		@ini_set('max_execution_time',300);

		echo '<div class="updated fade below-h2" id="message"><p>
FAVIROLL is (re)building favicons from your blogroll.<br />
This may be take some time... stay tuned, please!<br />
<br />
Cache directory = '.$this->cachedir.'<br />
<div style="text-align:center;padding:20px;"><img src="'.$this->pluginurl.'/working.gif" /></div>
';

		$this->flushbuffer();

		foreach(get_bookmarks() as $link)
			$this->putIconIntoCache($link,true);

		echo '</p></div><script type="text/javascript">var t = document.getElementById("message"); if (t){ t.style.display = "none"; }</script>';
		$this->flushbuffer();

		return true;
	}


		/**
	 * Load options from DB to member. Initialize if not exist.
	 */
	function initOptions() {

		$startsum = md5(var_export($this->opts,true));

		// -------------------- [defaults]
		//
		if (!isset($this->opts['default_favicon']))
			$this->opts['default_favicon'] = $this->getFavirollDefaultIcon();

		if (!isset($this->opts['revisit']))
			$this->opts['revisit'] = 180;

		if (!isset($this->opts['transparency']))
			$this->opts['transparency'] = true;

		if (!isset($this->opts['use_stylesheet']))
			$this->opts['use_stylesheet'] = false;

		if (!isset($this->opts['debug']))
			$this->opts['debug'] = false;

		// --------------------------- [runtime values]
		//
		if (!isset($this->opts['lastcheck']))
			$this->opts['lastcheck'] = 0;


		// Wenn sich etwas an den Init-Werten geändert hat,
		// diese in DB zurückspeichern
		//
		$endsum = md5(var_export($this->opts,true));

		if ($endsum != $startsum) {
			update_option('faviroll',$this->opts);
			echo ';)';
		}

	}


	/**
	 * @see http://www.php.net/manual/de/function.flush.php#81749
	 */
	function flushbuffer() {
    echo '<script type="text/javascript">new String("Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.  Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.  Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi.  Nam liber tempor cum soluta nobis eleifend option congue nihil imperdiet doming id quod mazim placerat facer possim assum. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat.  Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis.  At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, At accusam aliquyam diam diam dolore dolores duo eirmod eos erat, et nonumy sed tempor et et invidunt justo labore Stet clita ea et gubergren, kasd magna no rebum. sanctus sea sed takimata ut vero voluptua. est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat.  Consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus. Lorem ipsum dolor sit amet, consetetur sadipscing elitr");</script>';

    @ob_flush();
    flush();
	}


	/**
	 * Check write permissions on cache directory.
	 * @return error message if cache directory is not writable, or the string is given into method
	 */
	function get_message($othermsg=null) {

		if ($this->can_write_cache())
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

		extract($this->getURLinfo($bookmark),EXTR_OVERWRITE);
		if (!isset($basename))
			return false;

		$icopath = $this->cachedir."/$basename";

		if ($verbose) {
			echo "<p>${bookmark}....";
			$this->flushbuffer();
		}

		// truncate cache icon file
		fclose(fopen($icopath,'w'));

		$icourl = $this->locateIcon($rooturl);
		if ($icourl) {

			if (!$this->loadClass('Ico','Ico.class.php'))
				return false;

			$ico = new Ico($icourl,$this->getopt('transparency'));

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
				$result = imagepng($image,$icopath);
				imagedestroy($image);
			}

		} // icon creation end


		$result =  (is_file($icopath) && (filesize($icopath) > 0));

		if ($verbose) {
			$defaulticon = $this->getopt('default_favicon');
			echo '<br />&nbsp;<img src="'.(($result) ? $this->cacheurl."/$basename" : $defaulticon).'" />';

			if (!strlen($icourl))
				$icourl = 'fallback to default favicon';

			echo "&nbsp; ( $icourl )</p>";
			$this->flushbuffer();
			ob_flush();
		}

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
	 * Set an option value and store this immediately into DB
	 * @param $opts - array with new key/value pairs. if "drop" opts-Array will be reset
	 * @param $v option value
	 * @return $v
	 */
	function setopt($opts) {

		$commit = ($opts == 'drop');

		if ($commit) {
			$this->opts = array();
			delete_option('faviroll');
		} elseif (is_array($opts)) {

			foreach ($opts as $k => $v) {
				$value = isset($this->opts[$k]) ? $this->opts[$k] : null;
				if ($v != $value) {
					$this->opts[$k] = $v;
					$commit = true;
				}
			}
			if ($commit)
				update_option('faviroll',$this->opts);
		}

		return $commit;
	}


	/**
	 * patch "old style" options (before 0.4.8) into bundles array format
	 */
	function migrate_before048() {

		$opts = get_option('faviroll');
		$done = ($opts && !get_option('faviroll_default_favicon'));

		if ($done)
			return true;

		if (!$opts)
			$opts = array();

		$migrated = false;
		$ao = get_alloptions();
		foreach(array_keys((array) $ao) as $key) {
			if (preg_match('/^faviroll_/',$key)) {
				$nkey = substr($key,9);
				$opts[$nkey] = get_option($key);
				delete_option($key);
				$migrated = false;
			}
		}
		if ($migrated) {
			update_option('faviroll',$opts);
			echo '<div class="updated fade below-h2" id="message"><p>Options successfully migrated</p></div>';
		}

		return true;
	}

} /* end of class */

?>