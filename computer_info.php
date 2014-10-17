<?php
/*
Script Name: Your Computer Information
Author: Harald Hope, Website: http://TechPatterns.com/
Script Source URI: http://TechPatterns.com/downloads/browser_detection.php
Version: 1.3.4
Copyright (C) 2014-02-16

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

Get the full text of the GPL here: http://www.gnu.org/licenses/gpl.txt

This script requires the Full Featured Browser Detection and the Javascript Cookies scripts
to function.
You can download them here.
http://TechPatterns.com/downloads/browser_detection_php_ar.txt
http://TechPatterns.com/downloads/javascript_cookies.txt

Please note: this version requires the php browser_detection script version 5.4.0 or
newer.
*/
include_once('browser_detection.php');
function getComputerInfo($useragent){
	$os = '';
	$os_starter = 'Operating System: ';
	$full = '';
	$handheld = '';
	$tablet = '';
	// change this to match your include path/and file name you give the script
	$browser_info = browser_detection('full', '', $useragent);

	// $mobile_device, $mobile_browser, $mobile_browser_number, $mobile_os, $mobile_os_number, $mobile_server, $mobile_server_number
	if ( $browser_info[8] == 'mobile' ) {
		$handheld = 'Handheld Device: ';
		if ( $browser_info[13][8] ) {
			if ( $browser_info[13][0] ) {
				$tablet = ' (tablet)';
			} else {
				$handheld .= ucwords($browser_info[13][8]) . ' Tablet';
			}
		}
		if ( $browser_info[13][0] ) {
			$handheld .= 'Type: ' . ucwords( $browser_info[13][0] );
			if ( $browser_info[13][7] ) {
				$handheld = $handheld  . ' v: ' . $browser_info[13][7];
			}
			$handheld = $handheld  . $tablet . ' ';
		}
		if ( $browser_info[13][3] ) {
			// detection is actually for cpu os here, so need to make it show what is expected
			if ( $browser_info[13][3] == 'cpu os' ) {
				$browser_info[13][3] = 'ipad os';
			}
			$handheld .= 'OS: ' . ucwords( $browser_info[13][3] ) . ' ' .  $browser_info[13][4] . ' ';
			// don't write out the OS part for regular detection if it's null
			if ( !$browser_info[5] ) {
				$os_starter = '';
			}
		}
		// let people know OS couldn't be figured out
		if ( !$browser_info[5] && $os_starter ) {
			$os_starter .= 'OS: N/A';
		}
		if ( $browser_info[13][1] ) {
			$handheld .= 'Browser: ' . ucwords( $browser_info[13][1] ) . ' ' .  $browser_info[13][2] . ' ';
		}
		if ( $browser_info[13][5] ) {
			$handheld .= 'Server: ' . ucwords( $browser_info[13][5] . ' ' .  $browser_info[13][6] ) . ' ';
		}
	}

	switch ($browser_info[5]) {
		case 'win':
			$os .= 'Windows ';
			break;
		case 'nt':
			$os .= 'Windows NT ';
			break;
		case 'lin':
			$os .= 'Linux ';
			break;
		case 'mac':
			$os .= 'Mac ';
			break;
		case 'iphone':
			$os .= 'Mac ';
			break;
		case 'unix':
			$os .= 'Unix Version: ';
			break;
		default:
			$os .= $browser_info[5];
	}

	if ( $browser_info[5] == 'nt' ) {
		if ( $browser_info[5] == 'nt' ) {
			switch ( $browser_info[6] ) {
				case '5.0':
					$os = 'Windows 2000';
					break;
				case '5.1':
					$os = 'Windows XP';
					break;
				case '5.2':
					$os = 'Windows XP x64 Edition or Windows Server 2003';
					break;
				case '6.0':
					$os = 'Windows Vista';
					break;
				case '6.1':
					$os = 'Windows 7';
					break;
				case '6.2':
					$os = 'Windows 8';
					break;
				case '6.3':
					$os = 'Windows 8.1';
					break;
				case 'ce':
					$os .= 'CE';
					break;
				# note: browser detection 5.4.5 and later return always
				# the nt number in <number>.<number> format, so can use it
				# safely.
				default:
					if ( $browser_info[6] != '' ) {
						$os .= $browser_info[6];
					}
					else {
						$os .= '(version unknown)';
					}
					break;
			}
		}
	}
	elseif ( $browser_info[5] == 'iphone' ) {
		$os .=  'OS X (iPhone)';
	}
	// note: browser detection now returns os x version number if available, 10 or 10.4.3 style
	elseif ( ( $browser_info[5] == 'mac' ) && ( strstr( $browser_info[6], '10' ) ) ) {
		$os .=  'OS X v: ' . $browser_info[6];
	}
	elseif ( $browser_info[5] == 'lin' ) {
		$os .= ( $browser_info[6] != '' ) ? 'Distro: ' . ucwords($browser_info[6] ) : '';
	}
	// default case for cases where version number exists
	elseif ( $browser_info[5] && $browser_info[6] ) {
		$os .=  " " . ucwords( $browser_info[6] );
	}
	elseif ( $browser_info[5] && $browser_info[6] == '' ) {
		$os .=  ' (version unknown)';
	}
	elseif ( $browser_info[5] ) {
		$os .=  ucwords( $browser_info[5] );
	}
	$os = $os_starter . $os;
	$full .= $handheld . $os . ' Browser: ';

	switch ( $browser_info[0] ) {
		case 'moz':
			$a_temp = $browser_info[10];// use the moz array
			$full .= ($a_temp[0] != 'mozilla') ? 'Mozilla/ ' . ucwords($a_temp[0]) . ' ' : ucwords($a_temp[0]) . ' ';
			$full .= $a_temp[1] . ' ';
			$full .= 'ProductSub: ';
			$full .= ( $a_temp[4] != '' ) ? $a_temp[4] : 'Not Available';
			break;
		case 'ns':
			$full .= 'Netscape ';
			$full .= 'Full Version Info: ' . $browser_info[1];
			break;
		case 'webkit':
			$a_temp = $browser_info[11];// use the webkit array
			$full .= ucwords($a_temp[0]) . ' ' . $a_temp[1];
			break;
		case 'ie':
			$full .= 'User Agent: ';
			$full .= strtoupper($browser_info[7]);
			// $browser_info[14] will only be set if $browser_info[1] is also set
			if ( $browser_info[14] ) {
				if ( $browser_info[14] != $browser_info[1] ) {
					$full .= ' (compatibility mode)';
					$full .= ' Actual Version: ' . number_format( $browser_info[14], '1', '.', '' );
					$full .= ' Compatibility Version: ' . $browser_info[1];
				}
				else {
					if ( is_numeric($browser_info[1]) && $browser_info[1] < 11 ) {
						$full .= ' (standard mode)';
					}
					$full .= ' Full Version Info: ' . $browser_info[1];
				}
			}
			else {
				$full .= ' Full Version Info: ';
				$full .= ( $browser_info[1] ) ? $browser_info[1] : 'Not Available';
			}
			break;
		default:
			$full .= 'User Agent: ';
			$full .= ucwords($browser_info[7]);
			$full .= ' Full Version Info: ';
			$full .= ( $browser_info[1] ) ? $browser_info[1] : 'Not Available';
			break;
	}

	if ( $browser_info[1] != $browser_info[9] ) {
		$full .= ' Primary Version: ' . $browser_info[9];
	}

	return $full;
}
?>