<?php
/*
faviroll-ajax - AJAX stub for backend
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

/**
 * 
 */
function set_option($key, $value) {
	if (!function_exists('get_option'))
		return false;

	$opts = get_option('faviroll');
	if (!$opts)
		$opts = array();

	$opts[$key] = $value;

	update_option('faviroll',$opts);
	
	return $value;
}


/**
 * 
 */
function updateLinkImage($link_id,$newicon) {
	global $wpdb;
	$result = "basename:";

	$sqlcmd = "UPDATE $wpdb->links SET link_image = %s WHERE link_id = %s";
	$replaced = $wpdb->query( $wpdb->prepare($sqlcmd, $newicon, $link_id));
	if ($replaced)
		$result.= $newicon;
	
	return $result;	
}

// ------------------------------------------------------------
// error_log(var_export($_POST,true));

	if (!$_POST)
		exit(0);

	import_request_variables('P','req_');

	if (!isset($req_wpmu_prefix))
		exit(0);

	require_once('FavirollWorker.class.php');
  
	if (!isset($req_action))	
		$req_action = false;
		
	$result = array(
		"action:$req_action"
	);

	switch ($req_action) {
			case 'restore':
					require_once('../../../wp-blog-header.php');  
					if (isset($wpdb)) {
						$sqlcmd = "UPDATE $wpdb->links SET link_image = null WHERE link_image like '%faviroll-%'";
						$replaced = $wpdb->query($sqlcmd);
					}
					
					break;

			case 'reload':
				if($req_url && $req_siteid) {
					require_once('../../../wp-blog-header.php');  
					
					$fa = new FavirollWorker($req_wpmu_prefix);
					// Factory-Icon erneuern
					if (strstr($req_siteid,'site-0-') == $req_siteid) {
						$basename = $fa->initDefaultIcon(true);
					} elseif ($fa->putIconIntoCache($req_url)) {
						extract($fa->getURLinfo($req_url),EXTR_OVERWRITE);
					}

					// Bisher kein basename, also Fallback aus der Datenbank
					if (!isset($basename)) {
							if (function_exists('get_option')) {
								$opts = get_option('faviroll');
								if (!$opts)
									$opts = array();

								$basename = (isset($opts['default-icon'])) ? $opts['default-icon'] : 'invalid default icon';
							}
					}

					$result[] = "basename:$basename";
					$result[] = "siteid:$req_siteid";
					
				}
				break;

			case 'useicon':
				if($req_basename && $req_siteid) {
					require_once('../../../wp-blog-header.php');  

					// Default-Icon speichern
					if (strstr($req_siteid,'site-0-') == $req_siteid) {
						$basename = set_option('default-icon',$req_basename);
						if (!$basename) 
							$basename = 'invalid default icon';
	
						$result[] = "basename:$basename";
						$result[] = "siteid:$req_siteid";

					} else {
	
						$elems = explode('-',$req_siteid);
						if (count($elems) == 3) {
							list($dummy, $link_id, $md5) = $elems;
							$result[] = updateLinkImage($link_id,$req_basename);
						}
	
						$result[] = "siteid:$req_siteid";
					}
				}
				break;

			case 'customicon':
				if($req_url && $req_custid) {

					$fa = new FavirollWorker($req_wpmu_prefix);
					$image_url = $fa->locateCustomImageOrFallbackFavicon($req_url);

					$customColumn = 1;
					$custMD5 = explode('-',$req_custid);
					$basename = $fa->getCacheFilePrefix($customColumn).array_pop($custMD5);

					if ($fa->makeIcon($image_url,$basename)) {
						$result[] = "basename:$basename";
					} else {
						$result[] = "basename:invalid";
					}

					$result[] = "custid:$req_custid";
					
				}
				break;
									
			default:
				break;
	};

//	error_log($req_url. "---".$result[count($result)-1]);
	echo implode('|', $result);		
	flush();
	exit(0);
?>
