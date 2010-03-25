=== FAVIROLL - FAVIcons for blogROLL ===
Contributors: grobator
Donate link: http://donate.grobator.de/
Tags: favicons, links, icons, blogroll, bookmarks
Requires at least: 2.7
Tested up to: 2.9.1, (WPMU 2.8.6)
Stable tag: 0.4.7

Locally caches all favicon.ico in PNG format and use this into the blogroll. Native ICO Images are not supported from all browsers (f.e. IE).

== Description ==

The plugin converts the favicon.ico from the blogroll links in PNG Image format and save the converted image locally. The conversion process works just on the admin-page, visitors don't have to waste time to wait for favicons from remote websites. If a blogroll entry was added or modified this single Link will be updated.

In a configured interval of dates will the favicon cache be refreshed, because the favicons from the remote site can be changed.

The plugin fallback on a default favicon, if on the remote websites cannot be detected a valid favicon. The default icon can be configured.

== Screenshots ==
1. Faviroll's outcome
2. Faviroll configuration panel
3. (re)build the local cache with the remote favicons

== Installation ==
1. Upload the folder faviroll to the /wp-content/plugins/ directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Initialize plugin with its settings in general options by clicking the 'Submit' button.
1. The locally favicon cache will be created.
1. Thats all, enjoy.

== Changelog ==
Explanation:

* FEA = Implemented feature
* BUG = Resolved bug
* OPT = Optimization
* CLN = Cleanup/Refactoring
* OTH = Other

= 0.4.7 =
* FEA: Options has a new checkbox for disabling faviroll/style.css  (Request from Dixie)

= 0.4.6 =
* BUG: Icon Color "irritation" is fixed now.<br />
Many thanks to: http://www.tom-reitz.com/2009/02/17/php-ico-to-png-conversion/
* OPT: Users asked for WordPress MU support....Here it is.
* OTH: Revisit default days changed from 14 to 180 days (1/2 year should be enough)

= 0.4.5.1 =
* CLN: Code "polish"
* OPT: Try to enable/disable the availability with http://wordpress.org/extend/plugins/wp-render-blogroll-links/

= 0.4.5 =
* BUG: Parser has not recognized Blogroll-Links with additional attributes like title="...", rel="...", etc.<br />
Many many thanks for patient debugging support of: http://www.heiniger-net.ch/daniel
* prepared for the new version of http://wordpress.org/extend/plugins/wp-render-blogroll-links/
* Unfortunately this plugin needs a little patch. See Screenshot-4.

= 0.4.4 =
* CLN: Settings panel now you can find under "Links".
* OPT: Image type recognition. Just the ICO image is converted into PNG.<br />
All other image formats are bypass direct into the local cache.<br />
This will maximize the usage of the foreign favicons.

= 0.4.3 =
* CLN: complete (re)design cache file handling, fallback strategies and -finally- the integration into wordpress.
* OPT: Added styles.css to easily edit the faviroll css classes.

= 0.4.2 =
* CLN: little code correction

= 0.4.1 =
* BUG: in cache directory creation fixed

= 0.4 =
* BUG: unable to create cache directory.<br />
Some user feeback reports the plugin is unable to create cache directory which anchors in plugin folder because of missing write permissions.<br />
Now I use wordpress core function `wp_upload_dir()` to create the (persistent) cache into the "uploads" note.<br />

= 0.3.2 =
* OTH: same like version 0.3

= 0.3 =
* OTH: internal "fight" with subversion. No code changes.

= 0.2 =
* CLN: added screenshots
* OPT: switching the transparency automatically rebuild the icon cache 
* BUG: increase execution time to max. 5 minutes at (re)building all icons to avoid **Fatal error: Maximum execution time of xxx seconds exceeded**

= 0.1 =
* Initial version

== Upgrade notice ==
There is nothing to do for you

== Frequently Asked Questions ==
= Do the plugin work with WordPress MU? =
 Yes, plugin is WPMU compatible up to version 0.4.6 
