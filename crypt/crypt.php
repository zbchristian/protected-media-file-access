<?php

// AES256 text encryption 
// preshared keys are stored in file psk.php in the plugins sub-directory ./conf 

define("_KEYFILE_", dirname( __FILE__ )."/../conf/psk.php");
include _KEYFILE_;

function txt_encrypt($text) {
	if(!defined('_PSKEY_') || !defined('_PSKEY_HMAC_')) generate_pskey(); 
	$key = base64_decode(_PSKEY_);
	$key_hmac = base64_decode(_PSKEY_HMAC_);
	if($key === "" || strlen($key) != 32 ) $key = generate_pskey();
	$ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
	$iv = openssl_random_pseudo_bytes($ivlen);
	$ciphertext_raw = openssl_encrypt($text, $cipher, $key, $options=OPENSSL_RAW_DATA, $iv);
	$hmac = hash_hmac('sha256', $ciphertext_raw, $key_hmac, $as_binary=true);
	return base64_encode( $iv.$hmac.$ciphertext_raw );
}

function txt_decrypt($enctext) {
	if(!defined('_PSKEY_') || !defined('_PSKEY_HMAC_')) return false; 
	$key = base64_decode(_PSKEY_);
	$key_hmac = base64_decode(_PSKEY_HMAC_);
	if($key === "" || strlen($key) != 32) return false;
	$c = base64_decode($enctext);
	$ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
	$iv = substr($c, 0, $ivlen);
	$hmac = substr($c, $ivlen, $sha2len=32);
	$ciphertext_raw = substr($c, $ivlen+$sha2len);
	$original_text = openssl_decrypt($ciphertext_raw, $cipher, $key, $options=OPENSSL_RAW_DATA, $iv);
	$calcmac = hash_hmac('sha256', $ciphertext_raw, $key_hmac, $as_binary=true);
	if (!hash_equals($hmac, $calcmac)) return false;		
	return $original_text;
}

function generate_pskey() {
	$key1 = openssl_random_pseudo_bytes(32);
	$key2 = openssl_random_pseudo_bytes(32);
	$txt  ="<?php\n";
	$txt .='define("_PSKEY_","'.base64_encode($key1).'");'."\n";
	$txt .='define("_PSKEY_HMAC_","'.base64_encode($key2).'");'."\n";
	$txt .= '?>';
	@file_put_contents(_KEYFILE_, $txt);
	@chmod(_KEYFILE_,0600);	// no group and world access - does not always work
	return $key;
}
?>
