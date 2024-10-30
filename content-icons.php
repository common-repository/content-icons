<?php

/*
Plugin Name: Content Icons 
Plugin URI: http://www.codingrush.com/project-release/content-icons-plugin-release
Description: This plugin shows 'Content Icons' within each post. It is Compatible with WordPress 2.7. I have not tested this plugin with any other version of WordPress.
Version: 0.1
Author: RushiKumar
Author URI: http://www.codingrush.com
Credits: WP-Post-Icon Plugin Author for inspiration; Category Icons and WP-Sticky Plugin Authors for code help.
*/

/*  
Copyright 2009 RushiKumar Bhatt (email : RushiKumar dot Bhatt at no spam dot gmail dot com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; GNU GPL version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//Define table name to use throughout our Plugin
global $wpdb;
$wpdb->cicons = $wpdb->prefix.'ci_post_ref';

//Define a term that points to the uploads folder
define('CICONS', ABSPATH."/wp-content/uploads/cicons");

/**
 ** Function: Adding "Content Icons" Meta box
 **/
add_action('admin_menu', 'cicons_add_meta_box');
function cicons_add_meta_box() {
	add_meta_box('ciconsdiv', __('Content Icons', 'cicons'), 'cicons_metabox_admin', 'post', 'side');
}

/**
 ** Function: Content Icons Activate & Install
 **/
//First, we add the action, which tells WordPress to set the activation hook for a plugin
//this particular call requires to parameters: the file location and name and the function name
//add_action('activate_content-icons/content-icons.php', 'conticons_init');
add_action('init', 'cicons_init');
function cicons_init(){
	global $wpdb;

	//let's start by defining our table name
	$table_name = $wpdb->prefix . "ci_post_ref";
	
	//now, we make sure that such a table doesn't already exist.
	//if our desired table name already exist, we will not Create it again!
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		//we will be creating a table that holds two key pieces of information: icon id, and the post it belongs to.
		$create_cicons_info = "CREATE TABLE " . $table_name . " (
						`post_id` INT NOT NULL ,
						`icon_name` TEXT NOT NULL ,
						PRIMARY KEY ( `post_id` )
						);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($create_cicons_info);			
	}

	//create the upload directory
	wp_mkdir_p(ABSPATH."/wp-content/uploads/cicons");
}

/**
** Function: Meta Box Function
**/
function cicons_metabox_admin() {
	global $wpdb, $post;
	$currentPID = $post->ID;
	echo '
	<h3 class="dbx-handle"> Selection </h3>
	<h4 class="dbx-handle">Select Icon to Associate With This Post -- ID '.$currentPID.'</h4>
	<div class="dbx-content">
		<p>
			<select id="select_icon" name="select_icon" onChange="icon_preview.src=(\''.cicons_url().''.get_upload_path().'/\'+this.options[this.selectedIndex].value);">';
			
	//let's proceed only if the post id exist -- this ensures that we don't make unneccessary queries to our database
	if($currentPID != null){
		//echo 'not null';
		$chkIfExist = $wpdb->get_results("SELECT icon_name FROM `$wpdb->cicons` WHERE `post_id` = $currentPID AND `icon_name` != ''");
	}
	
	//if we have already associated a content icon to the post, let's display
	//that image's name first (instead of the non-sensical "Select Icon..." text)
	if($chkIfExist){
		$imagename = $chkIfExist[0]->icon_name;
		//we are going to padd the select box with some blank spaces... just so it doesn't look all crammed up!
		echo '<option value="'.$imagename.'">'.$imagename.'   </option>';
	//if have yet to associate a content icon to the post, we will
	//let the author select know to select one...
	}else{
		echo '<option value="">Select Icon ... </option>';
	}
	//let's display the rest of the icons that we find in our upload directory
	if ($dir = opendir(CICONS)) {
		while(false !== ($file = readdir($dir))) {
			if ($file != '.' && $file != '..') {
				$extension = strtolower(substr($file, strrpos($file, '.')+1));
				if ($extension === 'jpg' || $extension === 'jpeg' || $extension === 'gif' || $extension === 'png') {
					echo '<option value="' . $file . '">' . $file . '   </option>' . "\n";
				}
			}
		}
	closedir($dir);
	}

	echo '
			</select>
		</p>
	</div>
	
	<h3 class="dbx-handle"> Preview </h3>
	<div class="dbx-content">
		<br />
		<center><img id="icon_preview" src="'. cicons_url().''.$icon.'" /></center>
		<br />
	</div>';
	
	//Adding functionality: Displaying current selected content icon for the post at hand (i.e., for the current post)
	if ($chkIfExist) {
		//echo 'found a match...<br />';
		echo '<h3 class="dbx-handle"> Current Selection</h3>
		<div class="dbx-content">
			<br />
			<center><img id="curr_icon_preview" src="'. cicons_url().''.get_upload_path().'/'.$chkIfExist[0]->icon_name.'" /></center>
			<br />
		</div>';
	}
}

/**
** Function: Get the site's url
**/
function cicons_url() {
	$url = trailingslashit(get_option('siteurl')) . $def_path; // idem
	return $url;
}


/**
** Function: Get the Upload Path
**/
function get_upload_path(){
	$path = str_replace(ABSPATH, '', get_option('upload_path')."/cicons");
	return $path;
}

/**
** Function: Save our information whenever we save or publish the post
**/
add_action('save_post', 'add_cicons_admin_process');
function add_cicons_admin_process($post_ID) {
	global $wpdb;
	
	$cPID = wp_is_post_revision($post_ID);
	if($cPID != null){
		$select_icon = $_POST['select_icon'];
		// Ensure No Duplicate Field
		$check = intval($wpdb->get_var("SELECT * FROM `$wpdb->cicons` WHERE `post_id` = $cPID"));
		if($check == 0) {
			$wpdb->query( "INSERT INTO $wpdb->cicons (post_id, icon_name) VALUES ($cPID, '".$select_icon."')" );
		} else {
			if($select_icon != null){
				$wpdb->query( "UPDATE $wpdb->cicons SET icon_name = '".$select_icon."' WHERE post_id = $cPID" );
			}
		}
	}
}


/**
** Function: Delete the content icon associated with the post when the post itself gets deleted
**/
add_action('delete_post', 'delete_cicons_process');
function delete_cicons_process($post_ID) {
	global $wpdb;
	
	$cPID = wp_is_post_revision($post_ID);
	if($cPID != null){
	
		// Only proceed further if the content icon exist
		$check = intval($wpdb->get_var("SELECT * FROM `$wpdb->cicons` WHERE `post_id` = $cPID"));
		if($check > 0) {
			$wpdb->query( "DELETE FROM $wpdb->cicons WHERE post_id = $cPID" );
		}
	}
}

/**
** Function: Display our content icon within the post/the content itself
**/
function display_content_icon($post_content){
	global $wpdb, $post;
	if($post->ID != null){
		// Only proceed further if the content icon exist for the post
		$chkIfExist = $wpdb->get_results("SELECT icon_name FROM `$wpdb->cicons` WHERE `post_id` = $post->ID");
		if($chkIfExist) {
			$imageStr = '<img id="content_icon" src="'. cicons_url().''.get_upload_path().'/'.$chkIfExist[0]->icon_name.'" align="right" style="padding: 5px" />';
			$post_content = $imageStr . $post_content;
		}
	}
	return $post_content;
}
//we need to tell word press to pass the content THROUGH our function... so we can append content icon to the post
add_filter('the_content', 'display_content_icon');

?>