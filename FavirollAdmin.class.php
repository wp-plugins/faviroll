<?php
/*
 FavirollAdmin - Class for wordpress plugin "Faviroll" backend
 Author: andurban.de
 Version: latest
 ----------------------------------------------------------------------------------------
 Copyright 2009-2013 andurban.de  (email: http://www.andurban.de/kontakt)

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
	 * Constructor
	 */
	function __construct() {
		parent::__construct();

		$this->initOptions();
		$this->initDefaultIcon();
	}


	#################
	#               #
	#    SETTER     #
	#               #
	#################

	/**
	 * Init and load options from DB to member: opts.
	 */ 
	function initOptions() {

		// remove orphaned stuff from previous versions
		// if the key 'revisit' is still present, drop all
		if (isset($this->opts['revisit'])) {

			$this->removeAll();

			$pattern = $this->getCacheDir().'/*';
			$files = @glob($pattern);
			if ($files) {
				foreach($files as $filename) {
					if (is_file($filename))
					@unlink($filename);
				}
			}
		}

		return true;
	}


	/**
	 * make shure that default icon exists
	 */
	function initDefaultIcon() {
		$reset = false;
		$basename = $this->worker->initDefaultIcon($reset);
		if (!$basename)
		return false;

		$cacheDir = $this->getCacheDir();
		$default_cache = $cacheDir.$basename;

		$key = 'default-icon';
		$default_icon = $this->getopt($key);
		if (!$default_icon OR (!file_exists($default_cache))) {
			$this->opts[$key] = $basename;
			update_option('faviroll', $this->opts);
		}

		return $basename;
	}


	/**
	 * Remove whole faviroll plugin stuff
	 * Delete faviroll options
	 * Delete all files from cache directory
	 * Remove link_image from $wpdb->links
	 */
	function removeAll() {

		// Delete all options, from DB too
		$this->opts = array();
		delete_option('faviroll');

		// Remove all files from cache diretory
		$withsize = false;
		$fullpath = true;
		foreach($this->getCacheIcons($withsize,$fullpath) as $filename) {
			if (is_file($filename))
			@unlink($filename);
		}


	 // Update $wpdb->link_image
		global $wpdb;
		$sqlcmd = "UPDATE $wpdb->links SET link_image = null WHERE (link_image like %s OR link_image like %s)";
		$replaced = $wpdb->query( $wpdb->prepare($sqlcmd, 'faviroll-%_%','%/faviroll-%_%'));
		//echo "$replaced rows updated";

		return true;
	}

	#################
	#               #
	#    GETTER     #
	#               #
	#################

	/**
	 * @return prefix fo a cache file name.
	 */
	function getWPMUPrefix() {
		return $this->worker->getWPMUPrefix();
	}

	/**
	 * @see FavirollWorker#getPluginDir()
	 */
	function getPluginDir() {
		return $this->worker->getPluginDir();
	}

	/**
	 * @see FavirollWorker#getFactoryBasename()
	 */
	function getFactoryBasename() {
		return $this->worker->getFactoryBasename();
	}

	/**
	 * @see FavirollWorker#getMD5()
	 */
	function getMD5($url) {
		return $this->worker->getMD5($url);
	}

	/**
	 * @see FavirollWorker#getHomeURL()
	 */
	function getHomeURL() {
		return $this->worker->getHomeURL();
	}


	/**
	 * Check write permissions on cache directory.
	 * @return error message if cache directory is not writable, or the string is given into method
	 */
	function getPermCheckMsg($passtru_message=null) {

		$result = '';

		if (!$this->can_url_fopen())
			$result.= '<p><strong style="color:#aa0000;background-color:#fff000;padding:0px 5px 0px 5px;">CAUTION:</strong> no permission to open external URLs.<br />
Faviroll works just properly if you (your webmaster) enable <strong>"allow_url_fopen"</strong> in php.ini.<br />
For further informations see: http://www.php.net/manual/en/configuration.changes.php
</p>';

		
		if (!$this->can_write_cache())
			$result.= '<p><strong style="color:#aa0000;background-color:#fff000;padding:0px 5px 0px 5px;">CAUTION:</strong> no file permission to create icon-cache.<br />
You have to change the permissions.<br />
Use your ftp client, or the following command to fix it:<br />
<br />
<code># chmod 0775 '.$this->getCacheDir().'</code></p>';

		if (empty($result))
			return $passtru_message;
	
		return $result; 
	}


	/**
	 * @return TRUE if cache directory is writable
	 */
	function can_write_cache() {
		return $this->is__writable($this->getCacheDir());
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
	 * Check url_fopen is possible, try to enable if not
	 * @return TRUE if url_fopen is possible
 	 */
	function can_url_fopen() {
	
		$inikey = 'allow_url_fopen';
	
		// Is usr_fopen allowed?
		$isOk = ini_get($inikey);
		if ($isOk)
			return $isOk;
	
		// Is it possible to enable url_fopen?
		$opts = ini_get_all();
		if (isset($opt[$inikey]) && isset($opt[$inikey]['access']))
			$isOk = ($opt[$inikey]['access'] & INI_USER);
	
		// set url_fopen
		if ($isOk)
			$isOk = ini_set($inikey,'1');
	
		return $isOk;
		
	}
	
	
	/**
	 * @return the factory default favicon URL
	 */
	function getImgURL($name) {
		return $this->pluginurl.'img/'.$name;
	}


	/**
	 * @return The bookmark table for option page.
	 */
	function getBookmarkTable() {

		$isCacheInitMode = (defined('DO_INIT_CACHE'));

		$result = '';

		if ($isCacheInitMode)
		$result.= '<script type="text/javascript">var FAVIROLL_INITCACHE = true;</script>
';

		$result.= '<!-- Faviroll Helper Environment--> <input type="hidden" id="wpmu_prefix" value="'.$this->getWPMUPrefix().'" />
 <div style="text-align:center;">
  <table class="faviroll-table"><tbody>
  <tr>
   <td class="icon"><input type="button" title="remove plugin settings from database and drop all favicon cache files" value="remove settings and icon cache" onclick="return favi.roll.removeSettingsAndCache();" /></td><td></td><td class="notice" title="Click refresh icon to update favicons from their websites"></td>
  </tr>
  <tr>
   <th>Bookmark<span style="padding-left:40px;"><input type="button" name="_restore" id="_restore" value="restore bookmark icons to their origins" onclick="return favi.roll.restoreOriginalIcons(this);" /></span></th><th>Choose</th><th id="_reload" name="_reload" title="Refresh icon cache from their origin websites" onclick="return favi.roll.reloadIcons(this);">Site Icon</th><th id="custom" title="Custom icons">Custom Icon</th>
  </tr>
';

		$bookmarks = $this->getBookmarks();
		$default = $bookmarks[0];

		$emptyIcon = $this->getImgURL('empty.png');
		$loadIcon	= $this->getImgURL('spinner.gif');

		$cacheIcons = $this->getCacheIcons();
		$cacheurl = $this->getCacheURL();
		foreach ($bookmarks as $bm) {
			$customColumn=false;
			extract($this->getURLinfo($bm->link_url, $customColumn), EXTR_OVERWRITE);
			if (empty($basename))
				continue;

			// Cache Init Modus bekommen alle erstmal das Spin-Icon
			if ($isCacheInitMode) {
				$siteIcon = $loadIcon;
			} else {
				$siteIcon = "$cacheurl/";
				$siteIcon.= (in_array($basename,$cacheIcons)) ? $basename : $default->basename;
			}

			$md5key = $this->getMD5($bm->link_url);
			$id = $bm->link_id;
			$siteId = "site-$id-$md5key";

			$currentBasename = strstr($bm->link_image,'faviroll-');  // alles links vom String "faviroll-" entfernen
			$image_url = ($currentBasename) ? "$cacheurl/$currentBasename" : $basename;

			// Cache Init Modus bekommen alle erstmal das Spin-Icon
			if ($isCacheInitMode) {
				$currentIcon = $loadIcon;
			} else {
				// Wenn cacheurl im Pfad enthalten ist, nimm das Cache-Icon sonst das Fallback auf das Site-Icon.
				$currentIcon = (strstr($image_url,$cacheurl)) ? $image_url : $siteIcon;
			}

			// Spalte Custom Icon
			$custId = "cust-$id-$md5key";

			// cached custonm favicon files are in custom columm 1
			$customColumn = 1;
			extract($this->getURLinfo($bm->link_url, $customColumn), EXTR_PREFIX_ALL|EXTR_OVERWRITE|EXTR_REFS,'cust');

			// custon favicon from cache or fallback to empty icon
			$custIcon = (in_array($cust_basename,$cacheIcons)) ? "$cacheurl/$cust_basename" : $emptyIcon;

			// Link Adresse steht als Untertitel in der Link-Spalte
			$smallText = ($bm->link_id) ? $bm->link_url : 'the current default icon';


			$result.= ' <tr>
   <td class="link"><strong style="background-image:url('.$currentIcon.');" />'.$bm->link_name.'</strong><small>'.$smallText.'</small></td>
   <td class="setcolumn" onclick="return favi.roll.use(this);"><span class="hidden">&lArr;<img class="icon" src="'.$emptyIcon.'" alt="" title="Use this icon" /></span></td>
   <td id="'.$siteId.'"><input class="radio" type="radio" name="selected" value="" onclick="return favi.roll.setupSelectionIcons(this);" /><img class="icon" src="'.$siteIcon.'" alt="" title="Original icon from this site" /></td>
   <td id="'.$custId.'"><input class="radio" type="radio" name="selected" value="" onclick="return favi.roll.setupSelectionIcons(this);" /><img class="icon" src="'.$custIcon.'" alt="" title="Custom icon for this site" /><input type="button" value="..." onclick="return favi.roll.setCustomIcon(this);" /></td>
  </tr>
';
		}

		$result.= '</tbody></table></div>
';

		return $result;
	}


	#################
	#               #
	#    PAINTER    #
	#               #
	#################


	/**
	 * The Backend option page
	 */
	function option_page() {

		$message = null;
		if ($_POST) {

			if ($_POST['_remove']) {
				$this->removeAll();
				$this->initDefaultIcon();
				$message = 'Plugin settings and cached icons removed.<br /><a href="./plugins.php?plugin_status=active">You may disable the plugin now</a>';
			} elseif ($_POST['_initialize']) {
				define('DO_INIT_CACHE',true);
			}
		}

		$message = $this->getPermCheckMsg($message);
		if (!is_null($message))
		$message = "<div class='updated fade below-h2' id='message'><p>$message</p></div>";
			
		$myDir = plugin_dir_url(__FILE__);
		$title = __('FAVIcons for blogROLL', 'faviroll');

		echo <<<EOT
  <div id="_faviroll_countdown" class="updated fade faviroll-hidden">0 icons left</div>
  <div class="wrap">
   <div id="icon-options-general" class="icon32"><br /></div>
    <h2>${title}</h2>${message}    
    <div style="float:right;margin:10px;padding-right:50px;">
	  <a href="http://donate.andurban.de/"><img src="${myDir}/img/donate.gif" border="0" alt="donate" title="Donations welcome" /></a>
    </div>
  <!--BEGIN Faviroll-Form-->
   <form id="faviform" name="faviform" method="post" action="">
EOT;

		if (!defined('DO_INIT_CACHE') && $this->cacheIconsCount() < 3) {

			echo <<<EOT
    <div style="text-align:center;padding-top:120px;">
     <h2>Welcome to FAVIROLL</h2>
		 <input id="_faviroll_initbutton" type="submit" title="" value="Click here to start" onclick="return favi.roll.initCache();" />
    </div>
EOT;

		} else {
			echo $this->getBookmarkTable();
		}

		echo <<<EOT
   <input type="hidden" name="_remove" id="_remove" value="" />
   <input type="hidden" name="_initialize" id="_initialize" value="" />
   </form>
  </div>
EOT;

	}


} /* end of class */
?>