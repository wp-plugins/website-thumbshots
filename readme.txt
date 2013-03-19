=== Thumbshots plugin ===
Contributors: Sonorth Corp.
Tags: thumbshot, website screenshot, website thumbnail, website preview, snapshot, pagepix, webshot
Requires at least: 2.0
Tested up to: 3.5
Stable tag: 1.4.6

This plugin uses the Thumbshots.RU API to replace special tags in posts with website screenshots.


== Description ==

This plugin allows any user to add previews of websites right in the content of their posts using a simple [thumb]http://domain.com[/thumb] format. Users may also "optionally" turn on mouseover previews. No purchase or registration is required to use the plugin. Optional upgrade enables PRO features such as "Full-length Captures", "Free Width Captures" and "Refresh on Demand".

1. Easily embed website previews in posts
2. Flexible caching system allows you to store retrieved screenshots on your server
3. [opt] Display custom images for queued or error thumbshots
4. [opt] Display thumbshot URLs in a safe manner without linking them directly to the target website
4. [opt] Display mouseover header preview over thumbshots
5. [opt] Display mouseover pop-up preview over external links in your posts
6. [opt] Utilize several Thumbshots.RU PRO features

Take a look at Thumbshots.RU for more information (http://www.thumbshots.ru/en/ "Website screenshots provider").


== Installation ==

1. Upload `thumbshots_plugin` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Edit plugin settings on Dashboard > Settings > Thumbshots Plugin page


== Frequently Asked Questions ==

= Do I have to register on Thumbshots.RU to use this plugin? =

No, registration is optional. Only register if you want to use PRO features or if you have a high volume of traffic on the website.


== Screenshots ==

1. The plugin interface
2. Post editor button and inserted shortcode
3. An example thumbshot with reload button, and an optional mouseover header preview


== Changelog ==
= 1.4.6 =
* Clear thumbshot cache when API key changes
* Fix: Internationalized Domain Names
* Updated thumbshots class to v1.7.5

= 1.4.5 =
* "noindex" and/or "nofollow" parameters can be added to individual thumbshots and override global settings

= 1.4.4 =
* Options to add rel="noindex" and/or rel="nofollow" to image links
* Updated thumbshots class to v1.7.5

= 1.4.3 =
* Added a link to plugin settings page
* Minor bugfixes and code clean-up

= 1.4.2 =
* New: restyled plugin menu button
* Fix: use HTTP/1.0 requests to prevent chunking
* Fix: errors in response headers parsing algorithm

= 1.4.1 =
* Released under GPL version 3 or any later version

= 1.4.0 =
* First public release