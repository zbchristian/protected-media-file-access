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

define( "_SAVE_PROTECTED_ROOT_", plugin_dir_path( __FILE__ ) . '../conf/protected_root_dir.php');
include( plugin_dir_path( __FILE__ ) . '../conf/defaults.php');

class ProtectMediaFileSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Settings Protected Media Files', 
            'Protected Media Files', 
            'manage_options', 
            'protected_media_file_admin', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'protected_media_file_options' );
        echo '<div class="wrap">';
		echo '<h1>Protected Media Files</h1>';
//		var_dump($this->options);
        echo '<div class="pmf_main">';
		echo '<form method="post" action="options.php">';
                // This prints out all hidden setting fields
                settings_fields( 'protected_media_file_group' );
                do_settings_sections( 'protected_media_file_admin' );
                submit_button();
		echo '</form>';
		$this->print_info();
		echo '</div>';
		echo '</div>';
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'protected_media_file_group', // Option group
            'protected_media_file_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // section ID
            'Select the root folder and enable the protection', // Title
			null, // array( $this, 'print_section_info' ), // Callback
            'protected_media_file_admin' // Page
        );  

        add_settings_field(
            'root_folder', 
            'Root Folder', 
            array( $this, 'root_folder_callback' ), 
            'protected_media_file_admin', 
            'setting_section_id'
        );

        add_settings_field(
            'is_enabled', // enable flag
            'Enable Protection', // text  
            array( $this, 'is_enabled_callback' ), // Callback
            'protected_media_file_admin', // Page
            'setting_section_id' // Section           
        );      

        add_settings_section(
            'setting_section_id_2', // section ID
            'Define procedure in case of unauthorized access', // Title
            null, // Callback
            'protected_media_file_admin' // Page
        );  
		
        add_settings_field(
            'login_action', // action to perform, when not authenticated
            'Action', // text
            array( $this, 'login_action_callback' ), // Callback
            'protected_media_file_admin', // Page
            'setting_section_id_2' // Section           
        ); 

        add_settings_field(
            'custom_url', // url of custom login field
            'Custom URL/page', // text
            array( $this, 'custom_url_callback' ), // Callback
            'protected_media_file_admin', // Page
            'setting_section_id_2' // Section           
        ); 
		
		// parameters for call to wp_login_form 
        add_settings_field(
            'wp_login_form_uname', // label username field
            'Labels and text', // text
            array( $this, 'string_callback' ), // Callback
            'protected_media_file_admin', // Page
            'setting_section_id_2', // Section
			array('wp_login_form_uname',"Username field",_LOGIN_FORM_DEFS_["uname"])
        ); 
        add_settings_field(
            'wp_login_form_pw', // label PW field
            '', // text
            array( $this, 'string_callback' ), // Callback
            'protected_media_file_admin', // Page
            'setting_section_id_2', // Section
			array('wp_login_form_pw',"Password field",_LOGIN_FORM_DEFS_["pwd"])
        ); 
        add_settings_field(
            'wp_login_form_send', // text on submit button
            '', // text
            array( $this, 'string_callback' ), // Callback
            'protected_media_file_admin', // Page
            'setting_section_id_2', // Section
			array('wp_login_form_send',"Submit button",_LOGIN_FORM_DEFS_["submit"])
        ); 
        add_settings_field(
            'wp_login_form_failed_msg', // url of custom login field
            '', // text
            array( $this, 'string_callback' ), // Callback
            'protected_media_file_admin', // Page
            'setting_section_id_2', // Section
			array('wp_login_form_failed_msg',"Failed login message",_LOGIN_FORM_DEFS_["err"],70)
        ); 
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {

		$new_input = array();
		$updir = wp_upload_dir();
		$updir = isset($updir["basedir"]) ? dirname($updir["basedir"]) : WP_CONTENT_DIR;

		$txt_fields = array('root_folder','login_action','custom_url','wp_login_form_uname','wp_login_form_pw','wp_login_form_send','wp_login_form_failed_msg');
		foreach($txt_fields as $field) {
			if( isset( $input[$field] ) ) $new_input[$field] = sanitize_text_field( $input[$field] );
		}
		
		$enabled = false;
        if( isset( $input['is_enabled'] ) ) {
			$enabled = esc_attr($input['is_enabled']) == "enabled";
			$new_input['is_enabled'] = $enabled;
		}
		
		$last_root_folder = isset( $input['last_root_folder'] ) ? sanitize_text_field( $input['last_root_folder'] ) : '';

		if( isset( $new_nput['root_folder'] )) {
			if ($last_root_folder !== $new_input['root_folder']) $this->check_htaccess($updir."/".$last_root_folder,"delete");			
			$this->check_htaccess($updir."/".$new_input['root_folder'],$enabled ? "create" : "delete");
		}

		generate_pskey();
		// save root path to file
		if(isset($new_input["root_folder"])) {
			$mediadir = get_media_dir();
			$dir = dirname($mediadir)."/".$new_input['root_folder'];
			$opts  = '<?php'."\n";
			$opts .= 'define("_PROTECTION_ENABLED_",';
			$opts .= $enabled ? "true":"false";
			$opts .= ');'."\n";
			$opts .= 'define("_PROTECTED_ROOT_DIR_","'.$dir.'");'."\n";
			$opts .= '?>';
			@file_put_contents(_SAVE_PROTECTED_ROOT_, $opts);
		}
		
        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_info() 
    {
		print '<div class="pmf_info">';
     	print '<h4>How to use the media file protection?</h4>';
		print '<ul>';
		print '<li>Select the <strong>Root Folder</strong> (e.g. uploads/protected ) and enable the protection.';
		print   '<ul>';
		print 	'<li>A .htaccess file is places into the root folder, which blocks the direct access to the folder and all sub-folders from the web</li>';
		print   '<li>All direct links like https://example.com/wp_content/uploads/protected/my.jpg will be redirected.</li>';
		print   '</ul></li>';
		print '<li><strong>RECOMMENDATION: create a special Root Folder (e.g. uploads/protected). A Plugin like "WordPress Media Library Folders" can be used for this</stronG></li>';		
		print '<li>The plugin rewrites all links to the root folder (and subfolders) in order to be able to check the access rights</li>';
		print '<li>Currently access is granted for all logged in users';
		print '<li>If the access is granted, the plugin delivers the file</li>';
		print '<li>In case of an unauthorized access the following options can be configured above';
		print   '<dl>';
		print 	'<dt>WP standard login form</dt><dd>Displays the orgonal Wordpress login form. The requested media is passed as the <em>redirect_to</em> parameter</dd>';
		print 	'<dt>Custom URL</dt><dd>You prepare a page, which deals with the authentification or shows an error message. The requested media is passed as <em>redirect_to</em> parameter</dd>';
		print 	'<dt>Page with shortcode</dt><dd>Shows the login form on a page. Just create a page with a header line and include the shortcode [pmf_login_form]. The form is created via wp_login_form() and the requested media is passed as <em>redirect_to</em> parameter.<br>';
		print	'Your page should contain: <code><h2>My Login Message</h2>[pmf_login_form]</code></dd>';
		print '</dl>';
		print '</li>';
		print '</ul>';
		print '<p><strong>BE AWARE: when the protection is disabled, the root folder and all subfolders are UNPROTECTED</strong></p>';
		print '<hr>';
		print 'Base path to the media folder: '.dirname(get_media_dir());
		print '<hr>';
		print '<pre>
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
Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301  USA';
		print '</pre>';
		print '</div>';
	}

    /** 
     * Get the settings option array and print one of its values
     */
    public function root_folder_callback()
    {
		$html = '<select id="root_folder" name="protected_media_file_options[root_folder]">';
		$used = isset( $this->options['root_folder'] ) && !empty($this->options['root_folder']) ? esc_attr( $this->options['root_folder']) : '';
		foreach($this->get_media_folders() as $dir) {
			$html .= '<option value="'.$dir.'" '.(($used===$dir)?"selected":"").'>'.$dir.'</option>';
		}
		$html .= '</select>';
		// create hidden input for current root folder
		$html .='<input type="hidden" name="protected_media_file_options[last_root_folder]" value="'.$used.'">';
		echo $html;
    }

    public function is_enabled_callback()
    {
        printf(
            '<input type="checkbox" id="is_enabled" name="protected_media_file_options[is_enabled]" value="enabled" %s />',
            isset( $this->options['is_enabled'] ) && is_bool($this->options['is_enabled'] ) && $this->options['is_enabled'] ? 'checked="checked"' : ''
        );
    }

    public function login_action_callback()
    {
		$html = '<select id="login_action" name="protected_media_file_options[login_action]">';
		$act = isset( $this->options['login_action'] ) && !empty($this->options['login_action']) ? esc_attr( $this->options['login_action']) : '';
		foreach(_LOGIN_OPTS_ as $opt => $opttxt) {
			$opt = esc_attr($opt);
			$html .= '<option value="'.$opt.'" '.(($act===$opt)?"selected":"").'>'.$opttxt.'</option>';
		}
		$html .= '</select>';
		echo $html;
    }
	
    public function custom_url_callback()
    {
		$url = isset( $this->options['custom_url'] ) ? $this->options['custom_url'] : "";
		$html = '<input type="text" id="custom_url" name="protected_media_file_options[custom_url]" size="40"';
		$html .= ' value="'.$url.'">';

		$html .= '<br><select id="all_pages">'; 
		$html .= '<option value="">'.esc_attr( __( 'Select page' ) ).'</option>'; 
		$pages = get_pages(); 
		foreach ( $pages as $page ) {
			$html .= '<option value="' . wp_make_link_relative(get_page_link( $page->ID )) . '">';
			$html .= $page->post_title;
			$html .= '</option>';
		}
		$html .= '</select>';
		echo $html;
    }

    public function string_callback($arg)
    {
		$def = isset($arg[2]) ? $arg[2] : '';
		$opt =esc_attr(isset($this->options[$arg[0]]) ? $this->options[$arg[0]] : $def);
		$html = isset($arg[1]) && !empty($arg[1]) ? '<p><label for='.$arg[0].' >'.$arg[1].'</label></p>' : '';
		$size = isset($arg[3]) ? $arg[3] : "30";
		$html .= '<input type="text" id="'.$arg[0].'" name="protected_media_file_options['.$arg[0].']" size="'.$size.'" value="'.$opt.'">';
		echo $html;
    }

	
	function glob_recursive($pattern, $flags = 0) {
		$files = glob($pattern, $flags);
		foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
			$files = array_merge($files, $this->glob_recursive($dir.'/'.basename($pattern), $flags));
		}       
		return $files;
    }
		
	function get_media_folders() {
		$updir = wp_upload_dir();
		$updir = array_key_exists("basedir",$updir) ? $updir["basedir"] : WP_CONTENT_DIR."/uploads";
	//	$dirs = $this->glob_recursive($updir."/*",GLOB_ONLYDIR);
		$dirs = glob($updir."/*",GLOB_ONLYDIR);	// only top dirs of uploads as root folder
		$subdirs=array("uploads");
		$pat = preg_quote(dirname($updir)."/","/");
		foreach($dirs as $dir) {
			$subdirs[] = preg_replace("/".$pat."/","",$dir);
		}
		asort($subdirs);
		return $subdirs;
	}

	function check_htaccess($dir, $mode="create") {
		if(!@file_exists($dir) || !is_dir($dir)) return false;
		$fname = $dir."/.htaccess";
		switch($mode) {
			default:
			case "create":
				$fh = fopen($fname,"w");	// overwrite content
//				$txt = "Order deny,allow\n"."Deny from all\n";
// THE FOLLOWINH htaccess REQUIRES REWRITE TO WORK IN THE WEB SERVER
				$txt = "Options -Indexes\n";
				$txt .= "RewriteEngine on\n";
				$txt .= "RewriteBase /\n";
				$txt .= 'RewriteRule "^(.*)" "/'._GETFUNC_.'?'._GETPARM_.'=$1" [R,L]';
				fputs($fh,$txt);
				fclose($fh);
				break;
			case "delete":
				@unlink($fname);
				break;
		}
		return true;
	}
}


// load own CSS
function load_custom_wp_admin_style($hook) {
        if($hook != 'settings_page_protected_media_file_admin') return;
        wp_enqueue_style( 'pmf_admin_css', plugins_url('style.css', __FILE__) );
}


// load own javascript
function load_custom_wp_admin_js($hook) {
        if($hook != 'settings_page_protected_media_file_admin') return;
        wp_enqueue_script( 'pmf_admin_js', plugins_url('admin.js', __FILE__) );
}

if( is_admin() ) {
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui'); 
	$wp_scripts = wp_scripts();
	wp_enqueue_style('protected_media_file_admin-ui-css',
		'https://ajax.googleapis.com/ajax/libs/jqueryui/' . $wp_scripts->registered['jquery-ui-core']->ver . '/themes/ui-lightness/jquery-ui.css',
		false,0.0, false);
	add_action( 'admin_enqueue_scripts', 'load_custom_wp_admin_style' );
	add_action( 'admin_enqueue_scripts', 'load_custom_wp_admin_js' );
    $settings_page = new ProtectMediaFileSettingsPage();
}
?>
