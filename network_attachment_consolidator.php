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
define( 'naconsolidator_URL',     plugin_dir_url('') . 'network-attachment-consolidator/' );
define( 'naconsolidator_PATH',    dirname( __FILE__ ) . '/' );

class Network_Attachment_Consolidator {
	private static $nil_id;
	private static $step;

	/**
	 * Default initialization for the plugin:
	 * - Registers the default textdomain.
	 */
	public static function init() {
		// If the network library hasn't been set up, don't do anything, otherwise cache the site ID.
		if ( ! Network_Image_Library::is_active() ) {
			return;
		} else {
			self::$nil_id = Network_Image_Library::site_id();
		}
		// Internationalization.
		self::i11n();
		// Set up the current status.
		self::$step = get_site_option( 'nac_status', 'Find Duplicates' );
		// Enqueue scripts and add menu page
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_admin_page' ) );
		// Add ajax actions only if we are doing ajax and this request is for us.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! empty( $_REQUEST['action'] ) ) {
			$method = array( __CLASS__, str_replace( '-', '_', $_REQUEST['action'] ) );
			if ( is_callable( $method ) ) {
				add_action( 'wp_ajax_' . $_REQUEST['action'], $method );
			}
		}
	}
	public static function i11n() {
		// Load Text Domain
		$locale = apply_filters( 'plugin_locale', get_locale(), 'naconsolidator' );
		load_textdomain( 'naconsolidator', WP_LANG_DIR . '/naconsolidator/naconsolidator-' . $locale . '.mo' );
		load_plugin_textdomain( 'naconsolidator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	/**
	* Enqueue
	*
	*/
	public static function admin_enqueue_scripts( $hook ) {
		if ( $hook == 'settings_page_network_attachment_consolidator'){
			$min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			wp_enqueue_script(
				'naconsolidation-ajax',
				naconsolidator_URL ."assets/js/network_attachment_consolidator$min.js",
				array( 'wp-util' ),
				naconsolidator_VERSION,
				true
			);
			wp_localize_script( 'naconsolidation-ajax', 'nacNonce', wp_create_nonce( 'nac-fire' ) );
		}
	}


	/**
	 * Adds the options subpanel
	 *
	 */
	public static function network_admin_page( ) {
		add_submenu_page( 'settings.php', 'Attachment Consolidator', 'Attachment Consolidator', 'administrator', basename(__FILE__), array( __CLASS__, 'admin_options_page' ) );
	}

	/**
	 * Adds settings/options page
	 *
	 */
	public static function admin_options_page() {
		$ignore_galleries = ( !! get_site_option( '_nac_ignore_galleries' ) ); //woot for php-izing a JS trick.
		echo '<div class="wrap">';
		echo '<h2>Network Attachment Consolidator</h2>';
		echo '<p>This plugin is still in development. <strong> BACKUP </strong> your files and database before running!</p>';
		echo '<noscript>';
		echo '<strong>Warning:</strong> This plugin requires javascript and it is not currently available.';
		echo 'Please turn on javascript or use a javascript enabled browser and reload this page.';
		echo '</noscript>';
		echo '<p><input type="checkbox" id="nac-ignore-galleries" ' . checked( true, $ignore_galleries, false ) . ' /> ';
		echo '<label for="nac-ignore-galleries">Ignore Galleries</label></p>';
		echo '<p class="description">Ignoring galleries can help remove a significantly larger amount of images shared by the newtork. However, this comes at the cost of possibly removing some images from existing galleries as the attachment to which they belong will no longer exist.</p><br /><br />';
		self::submit_button( 'Start' );
		self::submit_button( 'Stop', array( 'disabled' => 'disabled' ) );
		echo '<h3>Status:</h3>';
		echo '<p id="nac-status">Paused</p>';
		echo '<h3>Next Step:</h3>';
		echo '<p id="nac-next-step">' . esc_html( self::$step ) . '</p>';
	}
	private static function submit_button( $text, $attrs = array() ) {
		if ( ! is_string( $text ) ) {
			submit_button();
		} else {
			$attrs = wp_parse_args( $attrs, array(
				'id' => 'nac-' . sanitize_key( $text ),
				'style' => 'margin-right: 1em;',
			) );
			submit_button( esc_attr( $text ), 'primary', null, false, $attrs );
		}
	}

	/**
	* Process & save array of duplicate image attachments
	*
	*/
	public static function find_duplicates() {
		$working_data = get_site_option( '_nac_find_data' );
		if ( ! is_array( $working_data ) ) {
			// Step one, set up, get blog list and set up structured working data option.
			global $wpdb;
			$working_data = array(
				'sites'      => $wpdb->get_results( "SELECT * FROM wp_blogs ORDER BY blog_id" ),
				'sites_done' => array(),
				'images'     => array(),
				'duplicates' => array(),
			);
		} else {
			// Step two, work through each blog, one at a time, one blog per request.
			$blog = $working_data['next_site'];
			$images = $working_data['images'];
			$duplicate_images = $working_data['duplicates'];

			switch_to_blog($blog->blog_id);

			$attachments = get_posts( array(
				'post_type' => 'attachment',
				'post_mime_type' =>'image',
				'posts_per_page' => -1,
			) );

			foreach ( $attachments as $attachment ) {
				if ( $attachment->post_parent != 0 ) {
					$image_info = wp_get_attachment_image_src( $attachment->ID, 'full' );
					$url_full = $image_info[0];
					$filename = basename( $url_full );

					if( array_key_exists( $filename, $images ) ) {
						if( $images[$filename]['blog_id'] == $blog->blog_id ) {
							//fb::log( $filename, 'Duplicate file on same blog.' );
							if( is_array( $images[$filename]['post_parent'] ) ){
								array_push( $images[$filename]['post_parent'], $attachment->post_parent );
							}else{
								$images[ $filename ]['post_parent'] = array( $images[$filename]['post_parent'], $attachment->post_parent );
							}
							continue; // Same image on same blog = skip
						} else {
							if( $images[ $filename ]['img_width'] == $image_info[1] && $images[ $filename ]['img_height'] == $image_info[2] ){
							// Bingo. Same image on different blogs!
								if ( ! isset( $duplicate_images[ $filename ] ) || ! is_array( $duplicate_images[ $filename ] ) ){
									$duplicate_images[ $filename ] = array();
									 array_push( $duplicate_images[$filename], array(
										'img_id' => $images[$filename]['attachment_id'],
										'url_full' => $images[$filename]['url_full'],
										'blog_id' => $images[$filename]['blog_id'],
										'post_parent' => $images[$filename]['post_parent']
									));
								}
								array_push( $duplicate_images[$filename], array(
									'img_id' => $attachment->ID,
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
			array_push( $working_data['sites_done'], $blog );
			$working_data['images'] = $images;
			$working_data['duplicates'] = $duplicate_images;
		}
		// Decide next step
		if ( 0 < count( $working_data['sites'] ) ) {
			$working_data['next_site'] = array_shift( $working_data['sites'] );
			update_site_option( '_nac_find_data', $working_data );
			wp_send_json_success( 'Process Site ' . $working_data['next_site']->blog_id );
		} else {
			update_site_option( '_nac_duplicates', $working_data['duplicates'] );
			update_site_option( 'nac_status', 'Consolidate Images' );
			delete_site_option( '_nac_find_data' );
			wp_send_json_success( 'Consolidate Images' );
		}
	}

	public static function consolidate_images() {
		$images = get_site_option( '_nac_duplicates' );
		// If we don't have an array, reset and error.
		if ( ! is_array( $images ) ) {
			delete_site_option( '_nac_duplicates' );
			update_site_option( 'nac_status', 'Find Duplicates' );
			wp_send_json_error( 'There was a problem processing the duplicate images. Try again?' );
		}
		// Make sure we're not done
		if ( 1 > count( $images ) ) {
			delete_site_option( '_nac_duplicates' );
			update_site_option( 'nac_status', 'Find Duplicates' );
			wp_send_json_success( 'Finished' );
		}
		// Set up the first image for processing and then remove it
		reset ( $images );
		$first_key = key( $images );
		$first_image = array( 'images' => array_shift( $images ), 'name' => $first_key );
		update_site_option( '_nac_duplicate', $first_image );
		update_site_option( '_nac_duplicates', $images );
		update_site_option( 'nac_status', 'Process Image' );
		wp_send_json_success( 'Process ' . $first_key );
	}

	public static function process_image() {
		$image = get_site_option( '_nac_duplicate' );
		// If we didn't get an image array, try to go back to 
		// the consolidate step and see if we can get a valid one.
		if ( ! is_array( $image ) ) {
			update_site_option( 'nac_status', 'Consolidate Images' );
			wp_send_json_success( 'Warning, image failed. Attempting to recover.' );
		}
		// See if we have this image in the network library
		if ( ! array_key_exists( 'network', $image ) ) {
			$exists = false;
			// See if it already exists.
			foreach( $image['images'] as $i => $site ) {
				// purposful fuzzy match as ids switch between strings and integers for some reason.
				if ( $site['blog_id'] == self::$nil_id ) {
					$image['network'] = $site;
					unset( $image['images'][ $i ] );
					$exists = true;
					break;
				}
			}
			// If it doesn't exist, create it.
			if ( ! $exists ) {
				switch_to_blog( self::$nil_id );
				$img_url = $image['images'][ $i ]['url_full'];
				$image['network'] = self::_sideload_image( $img_url );
			}
		} else {
			// Replace blog images with network entry
			$entry = array_shift( $image['images'] );
			switch_to_blog( $entry['blog_id'] );
			$post = get_post( $entry['post_parent'] );
			if ( $post ) {
				//Make sure this isn't this entries featured image, since there is no
				//way to support that currently.
				$ignore_galleries = ( !! get_site_option( '_nac_ignore_galleries' ) );
				$feat_id = get_post_thumbnail_id( $post->ID );
				$reg = self::_prepare_regex( $image['name'] );
				preg_match_all( $reg, $post->post_content, $matches );
				if ( $entry['img_id'] !== $feat_id && ! empty( $matches[0] ) ) {
					$new_content = $post->post_content;
					$new_excerpt = $post->post_excerpt;
					foreach ( $matches[0] as $match ) {
						$new_content = str_replace( $match, $image['network']['url_full'], $new_content );
						$new_excerpt = str_replace( $match, $image['network']['url_full'], $new_excerpt );
					}
					wp_update_post( array( 'ID' => $post->ID, 'post_content' => $new_content, 'post_exerpt' => $new_excerpt ) );
					/**
					 * @todo  Come up with some way to deal with galleries since they are ID based, not URL based.
					 */
				}
				if ( $ignore_galleries || ! empty( $matches[0] ) ) {
					wp_delete_attachment( $entry['img_id'], true );
				}
			}
		}
		// Continue processing each blog one at a time.	
		if ( 0 < count( $image['images'] ) ) {
			update_site_option( '_nac_duplicate', $image );
			wp_send_json_success( 'Process ' . $image['name'] );
		} else {
			// When done, drop back to consolidating images.
			delete_site_option( '_nac_duplicate' );
			update_site_option( 'nac_status', 'Consolidate Images' );
			wp_send_json_success( 'Consolidate Images' );
		}
	}

	private static function _sideload_image( $file ) {
		if ( ! empty($file) ) {
			// Download file to temp location
			$tmp = download_url( $file );

			// Set variables for storage
			// fix file filename for query strings
			preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
			$file_array['name'] = basename($matches[0]);
			$file_array['tmp_name'] = $tmp;

			// If error storing temporarily, unlink
			if ( is_wp_error( $tmp ) ) {
				@unlink($file_array['tmp_name']);
				$file_array['tmp_name'] = '';
			}

			// do the validation and storage stuff
			$id = media_handle_sideload( $file_array, 0 );
			// If error storing permanently, unlink and send error.
			if ( is_wp_error($id) ) {
				@unlink($file_array['tmp_name']);
				wp_send_json_error( 'There was a problem downloading an image.' );
			}

			$src = wp_get_attachment_url( $id );
			return array(
				'img_id'      => $id,
				'url_full'    => $src,
				'blog_id'     => self::$nil_id,
				'post_parent' => 0,
			);
		}
	}

	private static function _prepare_regex( $name ) {
		$extension = str_replace( '/', '\/', substr( strrchr( $name, '.' ), 1 ) );
		$name = str_replace( '/', '\/', rtrim( $name, '.' . $extension ) );
		return '/https?:\/\/[^"]*?(?:' . $name . ').*?(?:' . $extension . ')/';
	}

	public static function naconsolidator_ajax(){
		if( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nac-fire' ) ) {
			wp_send_json_error( 'This request appears to be invalid.' );
		}
		switch ( self::$step ) {
			case 'Find Duplicates':
				self::find_duplicates();
				break;
			case 'Consolidate Images':
				self::consolidate_images();
				break;
			case 'Process Image':
				self::process_image();
				break;
		}
		// If we get here, processing failed, send an error.
		wp_send_json_error( 'no valid step found.' );
	}

	public static function nac_ignoregalleries_ajax() {
		if( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nac-fire' ) ) {
			wp_send_json_error( 'This request appears to be invalid.' );
		}
		$current = ( !! get_site_option( '_nac_ignore_galleries' ) );
		if ( isset( $_POST['ignore'] ) ) {
			$current = ( '1' === $_POST['ignore'] );
			update_site_option( '_nac_ignore_galleries', $current );
		}
		wp_send_json_success( $current );
	}
}
// Fire immediately following the Network Image Library init.
add_action( 'nil_init', array( 'Network_Attachment_Consolidator', 'init' ) );
