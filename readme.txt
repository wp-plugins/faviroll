=== FAVIROLL - FAVIcons for blogROLL ===
Contributors: UnderWordPressure
Donate link: http://donate.andurban.de/
Tags: favicons, links, icons, blogroll, bookmarks
Requires at least: 3.x
Tested up to: 3.3
Stable tag: 0.5.1.2

== Description ==

This plugin convert the favicon.ico from the blogroll sites into PNG images and save this in a local cache file. The conversion process works just on the admin-page, visitors don't have to waste time to wait for favicons from remote websites. If a blogroll entry was added or modified this single Link will be updated.

All cached icons can conveniently be assigned to any of the bookmarks, just a mouse click.

The plugin fallback to a default icon, if on the remote websites cannot be detected a valid favicon. The default icon can be configured like any other bookmark.

== Screenshots ==
1. two blogrolls examples with Favicons
2. where you find the control panel in the backend
3. faviroll backend panel with favicon cache
4. manage-links panel have favicons decoration also

== Installation ==
1. install & activate the plugin.
1. initialize plugin with by clicking the 'Click here to start' button.
1. the favicon cache will be created.
1. play.

== Changelog ==
Explanation:

* FEA = Implemented feature
* FIX = Bugfix
* OPT = Optimization
* CLN = Cleanup/Refactoring
* OTH = Other

= 0.5.1.2 =
* OPT: Favicons will now displayed in the manage links admin panel also

= 0.5.1.1 =
* FIX: Resolved an javascript/ajax problem. images were not updated correctly.

= 0.5.1 =
* OPT: The favicon from own site is now the default icon for bookmarks without a favicon.
* OPT: Countdown box while refeshing favicons from foreign sites for better feedback.

= 0.5 =
* FEA: Complete redesign of the backend-panel with many customizing options and usability improvements
* FEA: No submit button any change is saved immediately via ajax technology.
* FEA: Possibility to assign any image for "custom icons", workaround to use an icon with a better quality.
* OPT: Full Code maintenance.

= 0.4.8.2 =
* OPT: Code maintenance for WP 3.x

= 0.4.8.1 =
* FIX: Ooops, I've overlooked a little error in my code, made one test too less, "mea cupla". ;-) 

= 0.4.8 - Codename: "Dixie's fix" =
* CLN: Code Refactoring: Code structure completely rewritten, a lot of bugfixes and runtime optimation.<br />
* FEA: Implementation of Windows BMP processing. Some site using this image format which can't process by PHPs GD-Library.<br />
Until now these proprietary formats was (non optimal) passed by Faviroll.
* FIX: Some Sites deliver Favions larger 16x16 pixels and proprietary formats wasn't shrinked to 16x16.<br />(Issue Request from Dixie. Thanks for feedback, "Dix").<br />
I've found [http://phpthumb.sourceforge.net/](http://phpthumb.sourceforge.net/) to solve the issue.<br />
This superb library is able to convert Windows BMP icons up to 32 Bit to [GD image](http://php.net/manual/en/ref.image.php) which Faviroll needs.<br />
Conclusion: Favicon handling is more complex I thought at the beginning, but I don't give up.

= 0.4.7 =
* FEA: Options has a new checkbox for disabling faviroll/style.css  (Request from Dixie)

= 0.4.6 =
* FIX: Icon Color "irritation" is fixed now.<br />
Many thanks to: http://www.tom-reitz.com/2009/02/17/php-ico-to-png-conversion/
* OPT: Users asked for WordPress MU support....Here it is.
* OTH: Revisit default days changed from 14 to 180 days (1/2 year should be enough)

= 0.4.5.1 =
* CLN: Code "polish"
* OPT: Try to enable/disable the availability with http://wordpress.org/extend/plugins/wp-render-blogroll-links/

= 0.4.5 =
* FIX: Parser has not recognized Blogroll-Links with additional attributes like title="...", rel="...", etc.<br />
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
* OPT: Added style.css to easily edit the faviroll css classes.

= 0.4.2 =
* CLN: little code correction

= 0.4.1 =
* FIX: in cache directory creation fixed

= 0.4 =
* FIX: unable to create cache directory.<br />
Some user feeback reports the plugin is unable to create cache directory which anchors in plugin folder because of missing write permissions.<br />
Now I use wordpress core function `wp_upload_dir()` to create the (persistent) cache into the "uploads" note.<br />

= 0.3.2 =
* OTH: same like version 0.3

= 0.3 =
* OTH: internal "fight" with subversion. No code changes.

= 0.2 =
* CLN: added screenshots
* OPT: switching the transparency automatically rebuild the icon cache 
* FIX: increase execution time to max. 5 minutes at (re)building all icons to avoid **Fatal error: Maximum execution time of xxx seconds exceeded**

= 0.1 =
* Initial version

== Upgrade Notice ==
Nothing to do for you

== Frequently Asked Questions ==
= What's about favicons of https (SSL) Websites? =
 The plugin is 100% written in PHP. In PHP it isn't possible to decode SSL sites, so the plugin try to fallback to "[curl](http://curl.haxx.se/)".
 If this is not available on your webserver, the favicon will not displayed.

= Where does the plugin come from? =
 see: [http://www.andurban.de/tag/faviroll](http://www.andurban.de/tag/faviroll)

= Can I save my custom favicons before install an update? =
  No, sorry.
  The cache directory is currently a subdirectory in the plugin directory, I designed this to hold all together.
  The wordpress update installer remove the whole old plugin directory before install the update, so all cached icons will removed, too.
