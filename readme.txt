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

How does it work?
=================
- Go to the settings page of the plugin
- Select the root folder for the protected media files
- enable the protection
- select the action in case of unauthorized access (not logged in)
- save the settings

The plugin will place a .htaccess file into the root folder, which blocks the direct access to all files
in the folder and sub-folders. 
The plugin scans the content of each delivered page for the path to the protected folder and rewrites the link to 
be captured by the plugin again. Since permalinks are rewritten at a very late stage by WordPress, the .htaccess file 
contains rewrite rules to capture these links as well. (Apache) Rewrite is required for this to work under all conditions.

The plugin uses two different access modes:
1) An activated WordPress action "pre_get_posts" checks for corresponding links, checks the authorization and delivers the file,
   if everything is ok. This requires WordPress to load and is slow

2) getimg.php in the plugins directory is called directly without the overhead of WordPress. Authorization has been done during the
   rewrite process and an encrypted time stamp is attached to the link. This is validated before the file is deliverd. This fast method
   is necessary for galleries to load reasonably fast. It is NOT used for links in <a href="..."> tags. 