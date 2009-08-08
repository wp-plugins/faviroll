=== FAVIROLL - FAVIcons for blogROLL ===
Contributors: grobator
Tags: favicons, links, icons, blogroll, bookmarks
Requires at least: 2.7
Tested up to: 2.8
Stable tag: 0.4.5

Locally caches all favicon.ico in PNG format and use this into the blogroll. Native ICO Images are not supported from all browsers (f.e. IE).

== Description ==

The plugin converts the favicon.ico from the blogroll links in PNG Image format and save the converted image locally. The conversion process works just on the admin-page, visitors don't have to waste time to wait for favicons from remote websites. If a blogroll entry was added or modified this single Link will be updated.

In a configured interval of dates will the favicon cache be refreshed, because the favicons from the remote site can be changed.

The plugin fallback on a default favicon, if on the remote websites cannot be detected a valid favicon. The default icon can be configured.

== Screenshots ==
1. Faviroll's outcome
2. Faviroll configuration panel
3. (re)build the local cache with the remote favicons
4. Remove the double slashes in WP-Render-Blogroll.php, if you want to have the faviroll icons in the blogroll of this plugin.

== Installation ==

1. Upload the folder faviroll to the /wp-content/plugins/ directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Initialize plugin with its settings in general options by clicking the 'Submit' button.
1. The locally favicon cache will be created.
1. Thats all, enjoy.

== Known issues ==
* Quality loss (or wrong color transformation) at the conversion from ICO->PNG, but some browsers (f.e. Safari) has a render problem with some native ICO images, too. I'll keep it in sight.

== Changelog ==
= 0.4.5 =
* FIXED: Parser has not recognized Blogroll-Links with additional attributes like title="...", rel="...", etc.
Many many thanks for patient debugging support of: http://www.heiniger-net.ch/daniel
* prepared for the new version of http://wordpress.org/extend/plugins/wp-render-blogroll-links/
* Unfortunately this plugin needs a little patch. See Screenshot-4.
* If possible, the patch will done by the faviroll plugin automatically.
= 0.4.4 =
* Settings panel now you can find under "Links".
* Image type recognition. Just the ICO image is converted into PNG. All other image formats are bypass direct into the local cache. This will maximize the usage of the foreign favicons.
= 0.4.3 =
* complete (re)design cache file handling, fallback strategies and -finally- the integration into wordpress.
* Added styles.css to easily edit the faviroll css classes.
= 0.4.2 =
* little code correction
= 0.4.1 =
* FIX: in cache directory creation fixed
= 0.4 =
* FIX: unable to create cache directory
Some user feeback reports the plugin is unable to create cache directory which anchors in plugin folder because of missing write permissions..
Now I use wordpress core function `wp_upload_dir()` to create the (persistent) cache into the "uploads" note.
Hopefully now it works all over.

= 0.3.2 =
* same like version 0.3

= 0.3 =
* internal "fight" with subversion. No code changes.

= 0.2 =
* added screenshots
* switching the transparency automatically rebuild the icon cache 
* increase execution time to max. 5 minutes at (re)building all icons to avoid **Fatal error: Maximum execution time of xxx seconds exceeded**

= 0.1 =
* Initial version
