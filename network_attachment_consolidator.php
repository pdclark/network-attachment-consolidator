<?php
/**
 * Plugin Name: Network Attachment Consolidator
 * Plugin URI:  http://knowmike.com
 * Description: Parses all Multisite installs, any duplicate image attachments get moved to shared upload folder
 * Version:     0.1.0
 * Author:      10up, Thaicloud
 * Author URI:  http://10up.com
 * License:     GPLv2+
 * Text Domain: naconsolidator
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2014 Michael Jordan (email : michael.jordan@10up.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Built using grunt-wp-plugin
 * Copyright (c) 2013 10up, LLC
 * https://github.com/10up/grunt-wp-plugin
 */

// Useful global constants
define( 'naconsolidator_VERSION', '0.1.0' );
define( 'naconsolidator_URL',     plugin_dir_url( __FILE__ ) );
define( 'naconsolidator_PATH',    dirname( __FILE__ ) . '/' );

class Network_Attachment_Consolidator {
	
	private static $nac_duplicate_images;
	
	/**
  	 * Default initialization for the plugin:
  	 * - Registers the default textdomain.
  	 */
  	public static function init() {
  		// Load Text Domain
  		$locale = apply_filters( 'plugin_locale', get_locale(), 'naconsolidator' );
		load_textdomain( 'naconsolidator', WP_LANG_DIR . '/naconsolidator/naconsolidator-' . $locale . '.mo' );
		load_plugin_textdomain( 'naconsolidator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
		// Add the super admin menu item
		add_action( 'network_admin_menu', array( __CLASS__, 'network_admin_page' ) );

		// Setup edit form
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );

		// Workaround for WP Settings API bug with multisite
		add_action( 'network_admin_edit_nac-settings', array( __CLASS__, 'save_nac_settings') );
	}
	
   /**
    * Adds the options subpanel
    *
    */
    public static function network_admin_page() {
		add_submenu_page( 'settings.php', 'Attachment Consolidator', 'Attachment Consolidator', 'administrator', basename(__FILE__), array( __CLASS__, 'admin_options_page' ) );
    }

	/**
    * Adds settings/options page
    *
    */
    public static function admin_options_page() {
    	echo '<div class="wrap">';
		echo '<h2>Network Attachment Consolidator</h2>';
		echo '<p>This plugin is still in development. <strong> BACKUP </strong> your files and database before running!</p>';
		echo '<form name="exclude_cpt_form" method="post" action="edit.php?action=nac-settings">';
		wp_nonce_field('nac-settings','nac-nonce');
		submit_button( 'Step 1: Detect all duplicate image attachments' );
		echo '</form>';
		echo '</div>';

    	self::$nac_duplicate_images	= get_option( 'nac_duplicate_images');
    	if( !empty(  self::$nac_duplicate_images ) ){
    		echo '<p>All Duplicate Image Attachments on this Network:</p>';
    		echo '<pre>';
    		print_r(  self::$nac_duplicate_images );
    		echo '</pre>';
    	}
    }

	/**
	 * The WordPress API behaving wierd on Multisite :(
	 *
	 */
	public static function save_nac_settings( ){
		if ( !isset($_POST['nac-nonce']) || !wp_verify_nonce($_POST['nac-nonce'],'nac-settings') ){
		   print 'Sorry, your nonce did not verify.';
		   exit;
		}else{
			 // loop through all Sites
			global $wpdb, $blog_id, $post;
    		$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM wp_blogs ORDER BY blog_id" ) );
    		
    		$images = array();
    		$duplicate_images = array();
    		foreach ( $blogs as $blog ){
	
        		switch_to_blog($blog->blog_id);
	
        		$attachments = get_posts( array(
				    'post_type' => 'attachment',
				    'post_mime_type' =>'image',
				    'posts_per_page' => -1,
				    'exclude'     => get_post_thumbnail_id()
				) );
				
				foreach ( $attachments as $attachment ) {
					if ( $attachment->post_parent != 0 ){
				    	$image_info = wp_get_attachment_image_src( $attachment->ID, 'full' );
						$url_full = $image_info[0];;
						$filename = basename( $url_full );

						if( array_key_exists( $filename, $images ) ){
							if( $images[$filename]['blog_id'] == $blog->blog_id ){
								//fb::log( $filename, 'Duplicate file on same blog.' );

								if( is_array( $images[$filename]['post_parent'] ) ){
									array_push( $images[$filename]['post_parent'], $attachment->post_parent );
								}else{
									$images[$filename]['post_parent'] = array( $images[$filename]['post_parent'], $attachment->post_parent );
								}

								continue; // Same image on same blog = skip
							}else{
								if( $images[$filename]['img_width'] == $image_info[1] && $images[$filename]['img_height'] == $image_info[2] ){
									// Bingo. Same image on different blogs!

									if ( !is_array( $duplicate_images[$filename] ) ){
										$duplicate_images[$filename] = array();
										array_push( $duplicate_images[$filename], array(
											'url_full' => $images[$filename]['url_full'],
											'blog_id' => $images[$filename]['blog_id'],
											'post_parent' => $images[$filename]['post_parent']
										));
									}

									array_push( $duplicate_images[$filename], array(
											'url_full' => $image_info[0],
											'blog_id' => $blog->blog_id,
											'post_parent' => $attachment->post_parent
									));
								}
							}
						}

				    	$images[$filename]['url_full'] = $image_info[0];
				    	$images[$filename]['img_width'] = $image_info[1];
				    	$images[$filename]['img_height'] = $image_info[2];
						$images[$filename]['post_parent'] = $attachment->post_parent;
						$images[$filename]['blog_id'] = $blog->blog_id;
						$images[$filename]['attachment_id'] = $attachment->ID;
		
						// TODO: here is a good place to check if orphan attachment & trash it

					}else{
						// If we wanted to do something fancy with images that aren't attached
						// to any posts- we can do it here
					}
        		}
        		restore_current_blog();
    		}

			update_option( 'nac_duplicate_images', $duplicate_images );
		
			wp_redirect(
    			add_query_arg(
       			 array( 'page' => 'network_attachment_consolidator.php', 'updated' => 'true' ),
        		(is_multisite() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ))
 				)
			);
			exit;
		}
	}
}

Network_Attachment_Consolidator::init();

/**
 * Activate the plugin
 */
function naconsolidator_activate() {
  // First load the init scripts in case any rewrite functionality is being loaded
  Network_Attachment_Consolidator::init();
  flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'naconsolidator_activate' );
