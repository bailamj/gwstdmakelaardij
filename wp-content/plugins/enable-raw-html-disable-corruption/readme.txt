=== Enable RAW HTML - Disable Corruption ===
Contributors: netAction
Tags: raw html, wp_autop, convert_chars, wptexturize
Requires at least: 2.0
Tested up to: 3.1
Stable tag: trunk

Every post and page will be forwarded directly to the browser without any correction. The plugin is extremely simple and effective.

== Description ==

Line breaks will not become P elements. Errors in HTML will not be fixed, no correction of Unicode Characters. Quotes, dashes, trademark symbols and so on will stay as they are.

This simple plugin switches all of the filters for your content off. Everything you type will be sent to your visitor's browser. The filters are wp_autop, convert_chars, wptexturize. If you need the same functions not only for posts and pages but also for excerpts or something else, have a look in the source and edit what you need.


== Installation ==

1. Upload the content of this archive to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Why do you disable wp_autop? =

The function adds P elements and BR elements where it finds line breaks in the source. If you have Tables, JavaScript or just want to add line breaks where you want, this function will disturb you.

= Why do you disable convert_chars? =

This function modifys TITLE, CATEGORY, BR, HR and other elements. It tries to fix Unicode characters and HTML tags. You can spend a lot of time searching for the reason of strange behaviour in your site. Often it is this function.

= Why do you disable wptexturize? =

With wptexturize you will get some funny things like long dashes, trademark symbols and apostrophes where you wanted to enter usual ASCII characters. Without this function you have to put the right things in the post directly. If you want a dash, use a dash, and you will get a dash.

= When should I enable the filter parse_as_php? =

If you have PHP code in your posts and trust your authors.

= I have a question. =

Or ask the author on [netaction.de](http://netaction.de/enable-raw-html-and-execute-php-in-wordpress/).

== Changelog ==

= 0.1 =
* first version

= 0.2 =
* PHP execution

= 0.3 =
* Minor fixes

