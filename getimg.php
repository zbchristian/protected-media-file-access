<?php
/*   
   WordPress Plugin Protected-Media-File-Access
   Copyright (C) 2018  Christian Zeitnitz

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 3 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software Foundation,
   Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301  USA
*/
// ------------------------------------------------------------------------------------------------
//
// BE AWARE: WordPress is NOT running, when this script is called. 
//			 Therefore the script has to be self contained.
// 			 Do not use WordPress methods/settings ...
//
// - Essential settings are stored in protected_root_dir.php 
// - time stamp and mime type file is encrypted with the key in psk.php in the GET variable _ENCTAG_

require_once( dirname( __FILE__ ) . '/conf/conf.php');
require_once( dirname( __FILE__ ) . '/conf/psk.php');
require_once( dirname( __FILE__ ) . '/conf/protected_root_dir.php');
require_once( dirname( __FILE__ ) . '/crypt/crypt.php');
require_once( dirname( __FILE__ ) . '/include/utils.php');

// check if protection is enabled and get path to protected folder (
if(!defined("_PROTECTED_ROOT_DIR_") || !defined("_ENCTAG_") || !defined("_GETPARM_")) exit;
$protect_enabled = defined("_PROTECTION_ENABLED_") ? _PROTECTION_ENABLED_ : false; 

// clean up the file name
$file = mb_ereg_replace("([^\w\s\d\-_\[\]\(\).\/])", '', $_GET[_GETPARM_]);
$fname = _PROTECTED_ROOT_DIR_."/".$file;

// check and sanatize the encrypted data
$tag = isset($_GET[_ENCTAG_]) ? preg_replace('/[^a-zA-Z0-9+=\/]/','',$_GET[_ENCTAG_]) : false;
if($tag !== false) $tag = txt_decrypt($tag);

// check file exists and valid tag found
if(is_file($fname) && $tag !== false) {
//	file_put_contents(dirname( __FILE__ ) ."/access.log", "File $file delivered\n".PHP_EOL , FILE_APPEND | LOCK_EX);
	$tag = explode("_",$tag);

	// check expiration of the tag (lifetime _TAGLIFE_) 
	if($protect_enabled && (count($tag) !== 2 || abs(time()-intval($tag[0])) > _TAGLIFE_)) {
		$newurl = "http" . ( ($_SERVER['HTTPS'] === 'on') ? "s://" : "://" ) . $_SERVER['HTTP_HOST'];
		$newurl .= "/"._GETFUNC_."?"._GETPARM_."=".$file;

		// tag is expired -> redirect to standard plugin hook, which will call the configured page for login/error message
		header("Location: ".$newurl);	
		exit();
	}

	// deliver the file
	send_file($fname, $tag[1]);
}
exit();
?>