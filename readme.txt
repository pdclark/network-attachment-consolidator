
=== Network Attachment Consolidator ===
Contributors: Thaicloud
Tags: multisite, attachments, images
Author URI: http://knowmike.com 
Requires at least: 3.8
Tested up to: 3.8
Stable tag: 0.1

Parses all Multisite installs, any duplicate image attachments get moved to shared upload folder

== Description ==

This plugin is still under development. DO NOT use in a production environment!!

What this plugin aims to do:

- Detect where the same image has been uploaded to different installs on the network
- Delete duplicates and move image into shared Images folder
- Update post meta and content to reflect new location

What is currently working:

- Network admin menu item / admin page
- Run initial consolidation on settings page: this returns an array of all duplicate image attachments ( where the same image has been uploaded to multiple installs )
- Saves array as site option


What is needed next:

- Parse array ( that has been saved as 'nac_duplicate_images' option ); for each piece of image data (1) copy image to new shared directory, (2) update attachment data, (3) update parent posts, (4) remove old duplicate images

---

Side note: For avoiding the problem of duplicate images on a network install for future content, please use the handy 'Network Shared Media' plugin:
( http://wordpress.org/plugins/network-shared-media/ )

== Installation ==

1. Upload the `network-attachment-consolidator` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==

= 0.1 =
* Builds out array of image attachments which are duplicated ( same filename & dimensions ) on different Multisite installs

