=== Network Attachment Consolidator ===
Contributors:      10up, Thaicloud
Donate link:       http://10up.com
Tags: 
Requires at least: 3.5.1
Tested up to:      3.8.1
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

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

== Installation ==

= Manual Installation =

1. Upload the entire `/network-attachment-consolidator` directory to the `/wp-content/plugins/` directory.
2. Activate Network Attachment Consolidator through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==


== Screenshots ==


== Changelog ==

= 0.1.0 =
* First release

== Upgrade Notice ==

= 0.1.0 =
First Release