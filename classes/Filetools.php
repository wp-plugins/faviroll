<?php

/**
 * Static class with file and directory tools.
 * $Id: Filetools.php 879 2008-07-02 05:41:23Z aur $
 */
class Filetools {

	/**
	 * Wandelt Backslashes einheitlich in Slashes um.
	 * Säubert den Pfad von "/" Dubletten
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
	 * Erzeugt einen relativen Pfad vom akt. Verzeichnis zum $target.
	 */
	function relative($target) {

		// Pfadangabe ist schon relativ
		//
		if ($target{0} == '.') {
			return $target;		//-->> fast exit elevator
		}


		// akt. Verzeichnis
		$cwd = Filetools::normalize(getcwd());

    // Target absolut machen
		$target = Filetools::normalize(__SRV_DIR__.$target);

		// In Arrays zerlegen
		$ar_cwd    = explode('/',$cwd);
		$ar_target = explode('/',$target);

		// Im Falle einer Datei das letzte Element hier merken,
		// da ab jetzt mit Verzeichnissen gearbeitet wird.
		//
		$ar_tmp = explode('?',$target);
		$realFile = $ar_tmp[0];
		if (file_exists($realFile)) {
			if (is_file($realFile)) {
				$filename = array_pop($ar_target);
			}
		}

		// Endlosschleife, solange die beiden Pfade gleich sind...
		while(true) {

			if (!isset($ar_cwd[0])) {

				// Arbeitsverzeichnis hat seine Tiefe erreicht.
				break;

			} elseif (!isset($ar_target[0])) {

				// Targetverzeichnis hat seine Tiefe erreicht.
				break;

			} elseif (strcmp($ar_cwd[0],$ar_target[0]) != 0) {

				// Pfade trennen sich.
				break;

			}

			// nächste Ebene untersuchen
			array_shift($ar_cwd);
			array_shift($ar_target);

		}

		// Sonderfall:
		// das Arbeitsverzeichnis wurde aufgerieben, Rest des Targets mit führenden '.' zusammenbauen.
		//
		if (!isset($ar_cwd[0])) {
			array_unshift ($ar_target, '.');
			$ar_result = $ar_target;
		}

		// ansonsten die Reste relativieren.
		if (!isset($ar_result)) {
			$ar_result = array();

			$len = count($ar_cwd);
			for ($i=0; $i<$len; $i++) {
				array_push($ar_result, '..');
			}

			// Target-Reste übergeben
			$ar_result = array_merge($ar_result, $ar_target);
		}

		// evtl. eingangs gemerkten Dateinamen hier wieder zurückgeben
		if (isset($filename)) {
			array_push($ar_result, $filename);
		}

		return join('/',$ar_result);
	}


	/**
	 * from http://de3.php.net/is_writable
	 * Since looks like the Windows ACLs bug "wont fix" (see http://bugs.php.net/bug.php?id=27609) I propose this alternative function:
	 */
	function is__writable($path) {

		if ($path{strlen($path)-1}=='/')
			return Filetools::is__writable($path.uniqid(mt_rand()).'.tmp');

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


}

?>
