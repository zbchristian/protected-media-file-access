<?php
/**
* Plugin Name: Protected Media File Access
* Plugin URI: https://www.zeitnitz.eu/protected-file-access
* Description: Deliver media files via http(s) from web server protected folders (e.g. uploads/protected/*)
* Version: 1.0
* Author: Christian Zeitnitz
* Author URI: http://zeitnitz.eu/
**/

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

/* 
Currently only a check for logged_in is done. No matching of users etc. implemented
*/ 

require_once( plugin_dir_path( __FILE__ ) . '/conf/conf.php');
require_once( plugin_dir_path( __FILE__ ) . '/conf/psk.php');
require_once( plugin_dir_path( __FILE__ ) . '/conf/protected_root_dir.php');
require_once( plugin_dir_path( __FILE__ ) . '/conf/defaults.php');
require_once( plugin_dir_path( __FILE__ ) . '/crypt/crypt.php');
require_once( plugin_dir_path( __FILE__ ) . '/admin/options.php');
require_once( plugin_dir_path( __FILE__ ) . '/include/utils.php');
require_once( plugin_dir_path( __FILE__ ) . '/include/mime_types_image.php');

define("_GETIMGURL_",plugin_dir_url( __FILE__ )._GETIMG_.".php");		// script to access protected media files more quickly (no WordPress overhead)

// Scan the the content of a page before its display and rewrite in href="" and src="" tags the path to the protected media folder by a function _GETFUNC_ 
// This is captured by the action "getfile_from_protected_folder" and the file is send.
// After sending the file this script EXITs the execution!

add_filter('the_content', 'rewrite_links_to_protected_folder',PHP_INT_MAX);	// run last 

// run after initializations of WP are done. Uses DB calls for options, $query object ...
add_action('pre_get_posts','getfile_from_protected_folder',~PHP_INT_MAX);	// run first

// shortcode to display login form on own page
add_shortcode( 'pmf_login_form', 'show_pmf_login_form' );

// in case the login failed, go back to login page and NOT the standard WP login
add_action( 'wp_login_failed', 'pmf_login_failed' );  
// check for empty user/password
add_filter( 'authenticate','pmf_capture_empty_credentials',~PHP_INT_MAX,3);


// a href/src link (e.g. https://example.com/wordpress/wp-conten/upload/protected/test.pdf) is rewritten to https://example.com/wordpress/getmedia?name=test.pdf
// replaces <img src="..."> tags as well, if the protected folder is referenced 
function rewrite_links_to_protected_folder($content){
	if(($protected_dir = get_path2protected("url")) === false) return $content;
	$protected_dir = preg_quote($protected_dir,"/");
	$pat = "/((http|https):\/\/[a-z0-9\.\-\_\/]*)\/*".$protected_dir."\/*([a-z0-9\.\/\-_]+)/i";
	$use_getimg = _ENABLE_GETIMG_ &&  extension_loaded('openssl');
	// check for image in protected folder
	if( $use_getimg && wp_validate_auth_cookie('','logged_in') !== false) {
		$cmd = _GETIMGURL_."?"._GETPARM_."=";
		foreach(_MIME_IMG_ as $mime) {
			// do NOT replace href links (causes problems with the gallery lighbox) 
			$patimg = '/(?<! href=")((http|https):\/\/[a-z0-9\.\-\_\/]*)\/*'.$protected_dir."\/*([a-z0-9\.\/\-_]+\.".$mime["ext"].')/i';
			$cryptotag = urlencode(txt_encrypt(strval(time())."_".$mime["mime"]));
			$content = preg_replace($patimg,$cmd."$3".'&'._ENCTAG_.'='.$cryptotag,$content);
		}
	}
	$cmd = "/"._GETFUNC_."?"._GETPARM_."=";
	$content = preg_replace($pat,"$1".$cmd."$3",$content);
	return $content;
}

// check for getfile tag and send file, if existing
// call for hook "pre_get_posts". Requires $query object and DB access
function getfile_from_protected_folder($query) {
	if($query->get("pagename") === _GETFUNC_) {
		if(($dir=get_path2protected("dir")) === false) return;
		$fname = $dir."/".esc_attr($_GET[_GETPARM_]);
		if(is_file($fname)) {
			if(wp_validate_auth_cookie('','logged_in') === false) {	// user not logged in -> show login page
				$current_url = "http" . ( ($_SERVER['HTTPS'] === 'on') ? "s://" : "://" ) . $_SERVER['HTTP_HOST'];
				$loginpage = "wp-login.php";
				if(($act = get_pmf_option('login_action')) !== false) {
					switch($act) {
							case "custom":
							case "custom_shortcode":
										if (($custompage=get_pmf_option('custom_url'))!==false) $loginpage = $custompage;
										break;
							default: 
										break;
					}
				}
				$login_url = $current_url.'/'.$loginpage;
				$current_url .= $_SERVER['REQUEST_URI'];
				if(!isset($query->query_vars["dest"])) $login_url = add_query_arg( 'dest', urlencode($current_url), $login_url );
				if(!isset($query->query_vars["type"])) $login_url = add_query_arg( 'type', 'download', $login_url );
				wp_safe_redirect( $login_url );	// show login page 
			}
			send_file($fname);
			exit();
		}
	}
}


function pmf_login_failed( $username ) {
	if(($act = get_pmf_option('login_action')) !== false) {
		switch($act) {
			case "custom":
			case "custom_shortcode":
				$referrer = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : $_SERVER['HTTP_REFERER'];  // where did the post submission come from?
				// if there's a valid referrer, and it's not the default log-in screen
				if ( !empty($referrer) && !strstr($referrer,'wp-login') && !strstr($referrer,'wp-admin') ) {
					$referr = add_query_arg( 'login', 'failed', $referrer );
					wp_safe_redirect( $referr );
				}
				break;
			default: break;
		}
   }
}

function pmf_capture_empty_credentials( $user, $username, $password ) {
    if ( empty( $username ) || empty( $password ) ) { do_action( 'wp_login_failed', $user ); }
    return $user;
}


function show_pmf_login_form ( $atts, $content = "" ) { return show_login_form(); }


function show_login_form() {
// output a login form via wp_login_form()
// the redirect_to parameter is filled with either the passed "redirect_to"/"dest", the HTTP_REFERRER or the domain url (in this order)
// The form is enclosed in a <div class="pmf_login_form">
	if(($txt_user 	= get_pmf_option('wp_login_form_uname')) === false) 	 $txt_user = _LOGIN_FORM_DEFS_["uname"]; 
	if(($txt_pwd  	= get_pmf_option('wp_login_form_pwd')) === false) 		 $txt_pwd  = _LOGIN_FORM_DEFS_["pwd"]; 
	if(($txt_send 	= get_pmf_option('wp_login_form_send')) === false) 		 $txt_send = _LOGIN_FORM_DEFS_["submit"]; 
	if(($txt_failed	= get_pmf_option('wp_login_form_failed_msg')) === false) $txt_failed = _LOGIN_FORM_DEFS_["err"]; 
	$dest = get_site_url();
	if(isset($_SERVER['HTTP_REFERER'])) $dest = $_SERVER['HTTP_REFERER'];
	if(isset($_GET['redirect_to'])) $dest = $_GET['redirect_to']; // if "dest" not set, use the redirect_to tag
	if(isset($_GET['dest'])) $dest = $_GET['dest'];

	$dest = esc_url($dest,array('http', 'https'),' ');
	echo '<div class="pmf_login_form">';
	if(isset($_GET['login']) && $_GET['login']==="failed") echo "<strong>$txt_failed</strong>";
	$args = array(
	'echo' => true,
	'redirect' => $dest,
	'form_id' => 'loginform',
	'label_username' => __( $txt_user ),
	'label_password' => __( $txt_pwd ),
	'label_remember' => __( ' ' ),
	'label_log_in' => __( $txt_send ),
	'id_username' => 'user_login',
	'id_password' => 'user_pass',
	'id_remember' => 'rememberme',
	'id_submit' => 'wp-submit',
	'remember' => false,
	'value_username' => NULL,
	'value_remember' => false );
	wp_login_form($args);
	echo '</div>';
}

?>