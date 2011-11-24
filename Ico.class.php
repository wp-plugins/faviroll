<?php
/**
 * class.ico.php
 * @(#) $Header: /home/jeph/repository/classes/ico/class.ico.php,v 0.1 2005/06/08 15:12:24 jeph Exp $
 **/

/**
 * Class Ico
 * Open ICO files and extract any size/depth to PNG format
 *
 * @author Diogo Resende <me@diogoresende.net>
 * @version 0.1
 *
 * @method public  Ico($path = '')
 * @method public  LoadFile($path)
 * @method private LoadData($data)
 * @method public  TotalIcons()
 * @method public  GetIconInfo($index)
 * @method public  GetIcon($index)
 * @method private AllocateColor(&$im, $red, $green, $blue, $alpha = 0)
 **/

class Ico {
	/**
	 * Ico::rawdata
	 * contains the original data, if image format != "ico".
	 */
	var $rawdata = false;

	/**
	 * Ico::imagetype
	 * Will set to runtime if data format != "ico".
	 */
	var $imagetype = false;

	/**
	 * Ico::ortherformatimage
	 * Will set to runtime if data format != "ico".
	 */
	var $otherformatimage = false;

	/**
	 * Ico::bgcolor
	 * Background color on icon extraction
	 *
	 * @type array(R, G, B) = array(255, 255, 255)
	 * @var  public
	 **/
	var $bgcolor = array(255, 255, 255);

	/**
	 * Ico::bgcolor_transparent
	 * Is background color transparent?
	 *
	 * @type boolean = false
	 * @var  public
	 **/
	var $bgcolor_transparent = true;

	/**
	 * Ico::Ico()
	 * Class constructor
	 *
	 * @param   optional    string   $path   Path to ICO file
	 * @param   optional    boolean  $transparency   create PNG with transparent background
	 * @return              void
	 **/
	function Ico($path=null,$transparency=true) {
		if (!is_null($path))
			$this->LoadFile($path);

		$this->SetBackgroundTransparent($transparency);
	}

	/**
	 * Ico::LoadFile()
	 * Load an ICO file (don't need to call this is if fill the
	 * parameter in the class constructor)
	 *
	 * @param   string   $path   Path to ICO file
	 * @return  boolean          Success
	 **/
	function LoadFile($path) {
		$this->_filename = $path;
		if (($fp = @fopen($path, 'rb')) !== false) {
			$data = '';
			while (!feof($fp)) {
					$data .= fread($fp, 4096);
			}
			fclose($fp);

			return $this->LoadData($data);
		}
		return false;
	}

	/**
	 * Ico::LoadData()
	 * Load an ICO data. If you prefer to open the file
	 * and return the binary data you can use this function
	 * directly. Otherwise use LoadFile() instead.
	 *
	 * @param   string   $data   Binary data of ICO file
	 * @return  boolean          Success
	 **/
	function LoadData($data) {
		$this->formats = array();

		$type = $this->detectImageType($data);
		switch ($type) {
			case 'txt':
			case false:
				return false;
				break;

			case 'ico':
				break;

			default:
				$this->imagetype = $type;
				$image = $this->getOtherFormatImage($data,$type);
				if ($image === false) {
					$this->rawdata = $data;
				} else {
					$this->otherformatimage = $image;
				}

				return true;
				break;
		}

		/**
		 * ICO header
		 **/
		$icodata = unpack("SReserved/SType/SCount", $data);

		$header = $icodata;

		// cut off header
		$data = substr($data, 6);

		/**
		 * Extract each icon header
		 **/
		for ($i = 0; $i < $header['Count']; $i ++) {

				if (strlen($data) < 1)
					continue;

				$icodata = unpack("CWidth/CHeight/CColorCount/CReserved/SPlanes/SBitCount/LSizeInBytes/LFileOffset", $data);

				$icodata['FileOffset'] -= ($header['Count'] * 16) + 6;
				if ($icodata['ColorCount'] == 0) $icodata['ColorCount'] = 256;
				$this->formats[] = $icodata;

				$data = substr($data, 16);
		}

		/**
		 * Extract aditional headers for each extracted icon header
		 **/
		for ($i = 0; $i < count($this->formats); $i++) {
				$icodata = unpack("LSize/LWidth/LHeight/SPlanes/SBitCount/LCompression/LImageSize/LXpixelsPerM/LYpixelsPerM/LColorsUsed/LColorsImportant", substr($data, $this->formats[$i]['FileOffset']));

				$this->formats[$i]['header'] = $icodata;
				$this->formats[$i]['colors'] = array();

				$this->formats[$i]['BitCount'] = $this->formats[$i]['header']['BitCount'];

				switch ($this->formats[$i]['BitCount']) {
						case 32:
						case 24:
								$length = $this->formats[$i]['header']['Width'] * $this->formats[$i]['header']['Height'] * ($this->formats[$i]['BitCount'] / 8);
								$this->formats[$i]['data'] = substr($data, $this->formats[$i]['FileOffset'] + $this->formats[$i]['header']['Size'], $length);
								break;
						case 8:
						case 4:
								$icodata = substr($data, $this->formats[$i]['FileOffset'] + $icodata['Size'], $this->formats[$i]['ColorCount'] * 4);
								$offset = 0;
								for ($j = 0; $j < $this->formats[$i]['ColorCount']; $j++) {
										$this->formats[$i]['colors'][] = array(
												'red'     => ord($icodata[$offset]),
												'green'    => ord($icodata[$offset + 1]),
												'blue'      => ord($icodata[$offset + 2]),
												'reserved' => ord($icodata[$offset + 3])
										);
										$offset += 4;
								}
								$length = $this->formats[$i]['header']['Width'] * $this->formats[$i]['header']['Height'] * (1 + $this->formats[$i]['BitCount']) / $this->formats[$i]['BitCount'];
								$this->formats[$i]['data'] = substr($data, $this->formats[$i]['FileOffset'] + ($this->formats[$i]['ColorCount'] * 4) + $this->formats[$i]['header']['Size'], $length);
								break;
						case 1:
								$icodata = substr($data, $this->formats[$i]['FileOffset'] + $icodata['Size'], $this->formats[$i]['ColorCount'] * 4);

								$this->formats[$i]['colors'][] = array(
												'blue'     => ord($icodata[0]),
												'green'    => ord($icodata[1]),
												'red'      => ord($icodata[2]),
												'reserved' => ord($icodata[3])
								);
								$this->formats[$i]['colors'][] = array(
												'blue'     => ord($icodata[4]),
												'green'    => ord($icodata[5]),
												'red'      => ord($icodata[6]),
												'reserved' => ord($icodata[7])
								);

								$length = $this->formats[$i]['header']['Width'] * $this->formats[$i]['header']['Height'] / 8;
								$this->formats[$i]['data'] = substr($data, $this->formats[$i]['FileOffset'] + $this->formats[$i]['header']['Size'] + 8, $length);
								break;
				}
				$this->formats[$i]['data_length'] = strlen($this->formats[$i]['data']);
		}

		return true;
	}

	/**
	 * Ico::TotalIcons()
	 * Return the total icons extracted at the moment
	 *
	 * @return  integer   Total icons
	 **/
	function TotalIcons() {
			if (!isset($this->formats))
				return 0;

			return count($this->formats);
	}

	/**
	 * Ico::GetIconInfo()
	 * Return the icon header corresponding to that index
	 *
	 * @param   integer   $index    Icon index
	 * @return  resource            Icon header
	 **/
	function GetIconInfo($index) {
			if (isset($this->formats[$index])) {
					return $this->formats[$index];
			}
			return false;
	}

	/**
	 * Ico::SetBackground()
	 * Changes background color of extraction. You can set
	 * the 3 color components or set $red = '#xxxxxx' (HTML format)
	 * and leave all other blanks.
	 *
	 * @param   optional   integer   $red     Red component
	 * @param   optional   integer   $green   Green component
	 * @param   optional   integer   $blue    Blue component
	 * @return             void
	 **/
	function SetBackground($red = 255, $green = 255, $blue = 255) {
			if (is_string($red) && preg_match('/^\#[0-9a-f]{6}$/', $red)) {
					$green = hexdec($red[3] . $red[4]);
					$blue = hexdec($red[5] . $red[6]);
					$red = hexdec($red[1] . $red[2]);
			}

			$this->bgcolor = array($red, $green, $blue);
	}

	/**
	 * Ico::SetBackgroundTransparent()
	 * Set background color to be saved as transparent
	 *
	 * @param   optional   boolean   $is_transparent   Is Transparent or not
	 * @return             boolean                     Is Transparent or not
	 **/
	function SetBackgroundTransparent($is_transparent = true) {
		return ($this->bgcolor_transparent = $is_transparent);
	}

	/**
	 * Ico::GetImage()
	 * Return an image resource with the icon stored
	 * on the $index position of the ICO file
	 *
	 * @param   integer    $index   Position of the icon inside ICO
	 * @return  resource            Image resource
	 **/
	function &GetIcon($index) {
			if (!isset($this->formats[$index])) {
					return false;
			}

			/**
			 * create image
			 **/
			$im = imagecreatetruecolor($this->formats[$index]['Width'], $this->formats[$index]['Height']);

			/**
			 * paint background
			 **/
			$bgcolor = $this->AllocateColor($im, $this->bgcolor[0], $this->bgcolor[1], $this->bgcolor[2]);
			imagefilledrectangle($im, 0 , 0, $this->formats[$index]['Width'], $this->formats[$index]['Height'], $bgcolor);

			/**
			 * set background color transparent
			 **/
			if ($this->bgcolor_transparent) {
					imagecolortransparent($im, $bgcolor);
			}


			/**
			 * allocate pallete and get XOR image
			 **/
			if (in_array($this->formats[$index]['BitCount'], array(1, 4, 8, 24))) {
					if ($this->formats[$index]['BitCount'] != 24) {
							/**
							 * color pallete
							 **/
							$c = array();
							for ($i = 0; $i < $this->formats[$index]['ColorCount']; $i++) {

/*
  # Original Code
	$c[$i] = $this->AllocateColor($im, $this->formats[$index]['colors'][$i]['red'],
						 												 $this->formats[$index]['colors'][$i]['green'],
																		 $this->formats[$index]['colors'][$i]['blue'],
																		 round($this->formats[$index]['colors'][$i]['reserved'] / 255 * 127));
*/

							  // Thanks to: http://www.tom-reitz.com/2009/02/17/php-ico-to-png-conversion/
							  //
								$c[$i] = $this->AllocateColor($im, $this->formats[$index]['colors'][$i]['blue'],
    																					     $this->formats[$index]['colors'][$i]['green'],
																					         $this->formats[$index]['colors'][$i]['red'],
																					         round($this->formats[$index]['colors'][$i]['reserved'] / 255 * 127));

							}
					}

					/**
					 * XOR image
					 **/
					$width = $this->formats[$index]['Width'];
					if (($width % 32) > 0) {
							 $width += (32 - ($this->formats[$index]['Width'] % 32));
					}
					$offset = $this->formats[$index]['Width'] * $this->formats[$index]['Height'] * $this->formats[$index]['BitCount'] / 8;
					$total_bytes = ($width * $this->formats[$index]['Height']) / 8;
					$bits = '';
					$bytes = 0;
					$bytes_per_line = ($this->formats[$index]['Width'] / 8);
					$bytes_to_remove = (($width - $this->formats[$index]['Width']) / 8);
					for ($i = 0; $i < $total_bytes; $i++) {
							$bits .= str_pad(decbin(ord($this->formats[$index]['data'][$offset + $i])), 8, '0', STR_PAD_LEFT);
							$bytes++;
							if ($bytes == $bytes_per_line) {
									$i += $bytes_to_remove;
									$bytes = 0;
							}
					}
			}

			/**
			 * paint each pixel depending on bit count
			 **/
			switch ($this->formats[$index]['BitCount']) {
					case 32:
							/**
							 * 32 bits: 4 bytes per pixel [ B | G | R | ALPHA ]
							 **/
							$offset = 0;
							for ($i = $this->formats[$index]['Height'] - 1; $i >= 0; $i--) {
									for ($j = 0; $j < $this->formats[$index]['Width']; $j++) {
											$color = substr($this->formats[$index]['data'], $offset, 4);
											if (ord($color[3]) > 0) {
													$c = $this->AllocateColor($im, ord($color[2]),
																												 ord($color[1]),
																												 ord($color[0]),
																												 127 - round(ord($color[3]) / 255 * 127));
													imagesetpixel($im, $j, $i, $c);
											}
											$offset += 4;
									}
							}
							break;
					case 24:
							/**
							 * 24 bits: 3 bytes per pixel [ B | G | R ]
							 **/
							$offset = 0;
							$bitoffset = 0;
							for ($i = $this->formats[$index]['Height'] - 1; $i >= 0; $i--) {
									for ($j = 0; $j < $this->formats[$index]['Width']; $j++) {
											if ($bits[$bitoffset] == 0) {
													$color = substr($this->formats[$index]['data'], $offset, 3);
													$c = $this->AllocateColor($im, ord($color[2]), ord($color[1]), ord($color[0]));
													imagesetpixel($im, $j, $i, $c);
											}
											$offset += 3;
											$bitoffset++;
									}
							}
							break;
					case 8:
							/**
							 * 8 bits: 1 byte per pixel [ COLOR INDEX ]
							 **/
							$offset = 0;
							for ($i = $this->formats[$index]['Height'] - 1; $i >= 0; $i--) {
									for ($j = 0; $j < $this->formats[$index]['Width']; $j++) {
											if ($bits[$offset] == 0) {
													$color = ord(substr($this->formats[$index]['data'], $offset, 1));
													imagesetpixel($im, $j, $i, $c[$color]);
											}
											$offset++;
									}
							}
							break;
					case 4:
							/**
							 * 4 bits: half byte/nibble per pixel [ COLOR INDEX ]
							 **/
							$offset = 0;
							$maskoffset = 0;
							$leftbits = true;
							for ($i = $this->formats[$index]['Height'] - 1; $i >= 0; $i--) {
									for ($j = 0; $j < $this->formats[$index]['Width']; $j++) {
											if ($leftbits) {
													$color = substr($this->formats[$index]['data'], $offset, 1);
													$color = array(
															'High' => bindec(substr(decbin(ord($color)), 0, 4)),
															'Low' => bindec(substr(decbin(ord($color)), 4))
													);
													if ($bits[$maskoffset++] == 0) {
															imagesetpixel($im, $j, $i, $c[$color['High']]);
													}
													$leftbits = false;
											} else {
													if ($bits[$maskoffset++] == 0) {
															imagesetpixel($im, $j, $i, $c[$color['Low']]);
													}
													$offset++;
													$leftbits = true;
											}
									}
							}
							break;
					case 1:
							/**
							 * 1 bit: 1 bit per pixel (2 colors, usually black&white) [ COLOR INDEX ]
							 **/
							$colorbits = '';
							$total = strlen($this->formats[$index]['data']);
							for ($i = 0; $i < $total; $i++) {
									$colorbits .= str_pad(decbin(ord($this->formats[$index]['data'][$i])), 8, '0', STR_PAD_LEFT);
							}

							$total = strlen($colorbits);
							$offset = 0;
							for ($i = $this->formats[$index]['Height'] - 1; $i >= 0; $i--) {
									for ($j = 0; $j < $this->formats[$index]['Width']; $j++) {
											if ($bits[$offset] == 0) {
													imagesetpixel($im, $j, $i, $c[$colorbits[$offset]]);
											}
											$offset++;
									}
							}
							break;
			}

			return $im;
	}

	/**
	 * Ico::AllocateColor()
	 * Allocate a color on $im resource. This function prevents
	 * from allocating same colors on the same pallete. Instead
	 * if it finds that the color is already allocated, it only
	 * returns the index to that color.
	 * It supports alpha channel.
	 *
	 * @param               resource    $im       Image resource
	 * @param               integer     $red      Red component
	 * @param               integer     $green    Green component
	 * @param               integer     $blue     Blue component
	 * @param   optional    integer     $alphpa   Alpha channel
	 * @return              integer               Color index
	 **/
	function AllocateColor(&$im, $red, $green, $blue, $alpha = 0) {
			$c = imagecolorexactalpha($im, $red, $green, $blue, $alpha);
			if ($c >= 0)
					return $c;

			return imagecolorallocatealpha($im, $red, $green, $blue, $alpha);
	}


// ---------------------------- [extended functions by grobator] ----------------------------

	/**
	 * @return the image type as string or FALSE if $data is empty.
	 */
	function detectImageType(&$data) {

		if ($this->is_text($data)) {
			return 'txt';
		} elseif ($this->is_png($data)) {
			return 'png';
		} elseif ($this->is_ico($data)) {
			return 'ico';
		} elseif ($this->is_jpg($data)) {
			return 'jpg';
		} elseif ($this->is_gif($data)) {
			return 'gif';
		} elseif ($this->is_tif($data)) {
			return 'tif';
		} elseif ($this->is_bmp($data)) {
			return 'bmp';
		}

		return false;

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
	 * @see http://www.onicos.com/staff/iz/formats/gif.html
	 * @see http://de.wikipedia.org/wiki/Magische_Zahl_%28Informatik%29#Magische_Zahlen_zur_Kennzeichnung_von_Dateitypen
	 * @TODO: documentation
	 */
	function is_gif(&$data) {

		if (strlen($data) < 10)
			return false;

		return (preg_match('/GIF8[79]a$/',substr($data,0,6)));
	}

	/**
	 * @see http://de.wikipedia.org/wiki/Magische_Zahl_%28Informatik%29#Magische_Zahlen_zur_Kennzeichnung_von_Dateitypen
	 * magic: \211 P N G \r \n \032 \n (0x89504e470d0a1a0a)
	 */
	function is_png(&$data) {

		if (strlen($data) < 10)
			return false;

		$header = implode('',unpack('H16', $data));

		return ($header == '89504e470d0a1a0a');
	}


	/**
	 * @see http://de.wikipedia.org/wiki/Magische_Zahl_%28Informatik%29#Magische_Zahlen_zur_Kennzeichnung_von_Dateitypen
	 */
	function is_jpg(&$data) {

		if (strlen($data) < 22)
			return false;

		$header = implode('',unpack('H20', $data));

		return (substr($header,0,6) == 'ffd8ff' && strstr($header,'4a464946') !== false);
	}

	/**
	 * @param &$data - the image binary data
	 *
	 * @see http://en.wikipedia.org/wiki/ICO_%28file_format%29#Header
	 * Offset# 	Size 	Purpose
	 * 0        2     reserved. should always be 0
	 * 2        2     type. 1 for icon (.ICO), 2 for cursor (.CUR) file
	 * 4        2     count; number of images in the file
	 */
	function is_ico(&$data) {
		if (strlen($data) < 10)
			return false;

		extract(unpack('Sreserved/Stype/Scount', $data),EXTR_OVERWRITE|EXTR_REFS);

		$isOk = ($reserved === 0);
		$isOk = ($isOk && ($type > 0 && $type < 3));

		return ($isOk && ($count > 0));
	}


	/**
	 * @see http://de.wikipedia.org/wiki/Magische_Zahl_%28Informatik%29#Magische_Zahlen_zur_Kennzeichnung_von_Dateitypen
	 */
	function is_tif(&$data) {

		if (strlen($data) < 10)
			return false;

		return preg_match('/^(II|MM)$/',substr($data,0,2));
	}


	/**
	 * @see http://de.wikipedia.org/wiki/Windows_Bitmap#Dateikopf
	 */
	function is_bmp(&$data) {

		if (strlen($data) < 4)
			return false;

		return (substr($data,0,2) == 'BM');
	}

	/**
	 * Try to import binary string to an image object
	 * @return image object if data could be successfully imported or FALSE
	 */
	function getOtherFormatImage(&$data,$type) {

		// Zuerst versuchen mit der Standard PHP Methode das Image aus dem binary string zu erzeugen
		$image = @imagecreatefromstring($data);

		// schlägt dieses fehl, versuche es mit dem BMP-Import
		if ($image === false) {
			if ($type == 'bmp') {
				if (!class_exists('Bmp'))
					require_once('PHPBmp.class.php');

					$bmp = new PHPBmp();
					$image = $bmp->bmp2gd($data);
			}
		}

		return $image;
	}

}
?>
