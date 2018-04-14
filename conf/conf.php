<?php

define("_GETFUNC_","getmedia");		// pseudo function captured to deliver protected files
define("_GETPARM_","file");			// query string  to define the protected file
define("_ENCTAG_","tag");			// url query string for the encrypted tag
define("_LOGIN_PAGE_","anmelden");	// define page in case the authentification failed

define("_ENABLE_GETIMG_",true);	// enable the use of the direct image delivery via the script _GETIMG_.php
define("_GETIMG_","getimg");		// name of php script to deliver images w/o Wordpress
define("_TAGLIFE_",1000); 			// number seconds the generated link via getimg.php is valid 

?>