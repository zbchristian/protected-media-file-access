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

function get_pmf_option($opt="") {
	$opts = get_option( 'protected_media_file_options' );
	if(empty($opt)) return $opts;
	return isset($opts[$opt]) ? $opts[$opt] : false;
}

function get_media_dir($mode="dir") {
	$updir = wp_upload_dir();
	if($mode === "dir") 
		$updir = array_key_exists("basedir",$updir) ? $updir["basedir"] : WP_CONTENT_DIR."/uploads";
	else
		$updir = array_key_exists("baseurl",$updir) ? $updir["baseurl"] : false;
	return $updir;
}

function get_path2protected($mode="url") {
	if(($updir = get_media_dir($mode)) === false) return false;
	if(get_pmf_option('is_enabled') === false || ($protected_folder = get_pmf_option('root_folder')) === false) return false;
	if ( $mode === "url") {
		$path = dirname(parse_url($updir,PHP_URL_PATH));
		$path = $path."/".$protected_folder;
	}
	else {
		$path = dirname($updir)."/".$protected_folder;
	}
	return $path; 
}

function send_file($file,$mime="",$mode="file") {
	if(is_file($file) && is_readable($file)) {
		ob_end_clean(); // delete all buffered data before sending the headers
		if (empty($mime)) $mime = decode_mime($file);
		// write the headers for the file transmission
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false);
		header('Content-type: '.$mime);
		header('Content-Length: '.filesize($file));
		$save_name = basename( $file );
		if($mode == "file") header('Content-Disposition: attachment; filename="'.$save_name.'"');
		header("Content-Transfer-Encoding: binary");
		return readfile_blocked( $file );  // send data
	}
	return false;
}

function readfile_blocked($file) {
	$bsize = 1*1024*1024;
	$buf = '';
	$fh = fopen($file, 'rb');
	if ($fh === false) return false;
	while (!feof($fh)) {
		$buf = fread($fh, $bsize);
		echo $buf;
		flush();
	}
	fclose($fh);
}

function decode_mime($file) {
	$ext = suffix($file);
	$mime_types = wp_get_mime_types();	// get mime types as associated array (index is a regexp (e.g. jpeg|jpg ) 
	$mime_type = "application/octet-stream";	// default mime type
    foreach ($mime_types as $regex_ext => $type) {
        if (preg_match("/{$regex_ext}/i", $ext)) {
            $mime_type = $type;
            break;
        }
    }
	return $mime_type;
}

function suffix($name) {
	if(strrpos($name, '.') !== false) return trim(strtolower(substr($name, strrpos($name, '.')+1)));
	return "";
}
?>
