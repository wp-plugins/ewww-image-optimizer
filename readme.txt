=== EWWW Image Optimizer ===
Contributors: nosilver4u
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=QFXCW38HE24NY
Tags: images, image, attachments, attachment, optimize
Requires at least: 2.8
Tested up to: 3.5.1
Stable tag: 1.4.4
License: GPLv3

Reduce file sizes for images within WordPress including NextGEN Gallery and GRAND FlAGallery. Uses jpegtran, optipng/pngout, and gifsicle.

== Description ==

The EWWW Image Optimizer is a WordPress plugin that will automatically and losslessly optimize your images as you upload them to your blog. It can also optimize the images that you have already uploaded in the past. It is also now possible to convert your images automatically to the file format that will produce the smallest image (make sure you read the WARNINGS).

By default, EWWW Image Optimizer uses lossless optimization techniques, so your image quality will be exactly the same before and after the optimization. The only thing that will change is your file size. The one small exception to this is GIF animations. While the optimization is technically lossless, you will not be able to properly edit the animation again without performing an --unoptimize operation with gifsicle. The gif2png and jpg2png conversions are also lossless but the png2jpg process is not lossless.

Images are optimized using the [jpegtran](http://jpegclub.org/jpegtran/), [optipng](http://optipng.sourceforge.net/), [pngout](advsys.net/ken/utils.htm), and [gifsicle](http://www.lcdf.org/gifsicle/) image tools (available for free). For PNG files, either optipng or pngout can be used. If you want the best optimization, install both, set optipng to level 3 (beyond that is just crazy and rarely yields significant gains) and pngout to level 0. Images are converted using the above tools and GD or 'convert' (ImageMagick).

EWWW Image Optimizer calls optimization utilities directly which is well suited to shared hosting situations where these utilities may already be installed. Pre-compiled binaries/executables are provided for optipng, gifsicle, and jpegtran. Pngout can be installed with one-click from the settings page.

**Why use EWWW Image Optimizer?**

1. **Your pages will load faster.** Smaller image sizes means faster page loads. This will make your visitors happy, and can increase ad revenue.
1. **Faster backups.** Smaller image sizes also means faster backups.
1. **Less bandwidth usage.** Optimizing your images can save you hundreds of KB per image, which means significantly less bandwidth usage.
1. **Super fast.** Because it runs on your own server, you don’t have to wait for a third party service to receive, process, and return your images. You can optimize hundreds of images in just a few minutes. PNG files take the longest, but you can adjust the settings for your situation.
1. **Better PNG optimization.** YOu can use pngout and optipng in conjunction.
1. **Root access not needed** Pre-compiled binaries are made available to install directly within the Wordpress folder. 

= NextGEN Integration =

Features re-optimization capability, and bulk optimizing. The NextGEN Bulk Optimize function is located near the bottom of the NextGEN menu, and will optimize all images in all galleries.
NOTE: Does not optimize thumbnails on initial upload, 1.4.1+ will provide the option to optimize thumbnails after uploading images.

= GRAND Flash Album Gallery Integration =

Features optimization on upload capability, re-optimization, and bulk optimizing. The Bulk Optimize function is located near the bottom of the FlAGallery menu, and will optimize all images in all galleries. It is also possible to optimize groups of images in a gallery, or multiple galleries at once.

== Installation ==

1. Upload the 'ewww-image-optimizer' plugin to your '/wp-content/plugins/' directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Ensure jpegtran, optipng, pngout and gifsicle are installed on your Linux server (basic installation instructions are below if they are not). You will receive a warning when you activate the plugin if they are not present. This message will go away once you have them installed.
1. The plugin will attempt to install jpegtran, optipng, and gifsicle automatically for you. This requires that the wp-content folder is writable by the user running the web server.
1. If the automatic install did not work, find the appropriate binaries for your system in the ewww-image-optimizer plugin folder, copy them to wp-content/ewww/ and remove the OS 'tag' (like -linux or -fbsd). No renaming is necessary on Windows, just copy the .exe files to the wp-content/ewww folder. IMPORTANT: Do not symlink or modify the binaries in any way, or they will not pass the security checks.
1. *Optional* Visit the settings page to enable/disable specific tools and turn on advanced optimization features.
1. Done!

= Installing pngout =

Pngout is new in version 1.1.0 and is not enabled by default because it is resource intensive. Optipng is the preferred PNG optimizer if you have resource (CPU) constraints. Pngout is also not open-source for those who care about such things, but the command-line version is free.

1. Go to the settings page.
1. Uncheck the option to disable pngout and Save your settings.
1. Click the link in the Plugin Status area to install pngout for your server, and the plugin will download the pngout archive, unpack it, and install the appropriate version for your server.
1. Adjust the pngout level according to your needs. Level 0 gives the best results, but can take up to a minute or more on a single image.

= Installing optipng =

1. Optipng is now bundled with the plugin. If it isn't working for some reason, keep going...
1. If you have root access to your server, you can install optipng from the standard repositories (yum/rpm or apt/deb). If you are on shared hosting, read on... These steps can/should generally all be done via the command line
1. Download the latest stable version of [optipng](http://optipng.sourceforge.net/) to your home directory
1. Ensure libpng and zlib are installed. If they are not, you're on your own there (but maybe you need a new web host...)
1. Uncompress optipng: *tar xvzf optipng-0.7.4.tar.gz && cd optipng-0.7.4*
1. Configure and compile optipng: *./configure && make*
1. If you have root access, install it with *make install*
1. If not, copy the binary from */optipng-0.7.4/src/optipng/optipng* to the ewww tool folder (wordpress/wp-content/ewww/optipng-custom).

= Installing jpegtran =

1. Jpegtran is now bundled with the plugin. If it isn't working for some reason, keep going...
1. If you have root access to your server, jpegtran is part of the libjpeg-turbo-progs on Debian/Ubuntu, and likely something similar on rpm distros (Fedora, CentOS, RHEL, SuSE). If you are on shared hosting, read on... These steps can/should generally all be done via the command line
1. Download the latest stable version of [jpegtran](http://www.ijg.org/) to your home directory
1. Uncompress jpegtran: *tar xvzf jpegsrc.v9.tar.gz && cd jpeg-9*
1. Configure and compile jpegtran: *./configure --disable-shared && make*
1. If you have root access, install it with *make install*
1. If not, copy the binary from */jpeg-9/jpegtran* to the ewww tool folder (wordpress/wp-content/ewww/jpegtran-custom).

= Installing gifsicle =

1. Gifsicle is now bundled with the plugin. If it isn't working for you, keep going...
1. If you have root access to your server, you can install gifsicle from the standard repositories (yum/rpm or apt/deb). If you are on shared hosting, read on... These steps can/should generally all be done via the command line
1. Download the latest version of [gifsicle](http://www.lcdf.org/gifsicle/) to your home directory
1. Uncompress gifsicle: *tar xvzf gifsicle-1.70.tar.gz && cd gifsicle-1.70*
1. Configure and compile gifsicle (we disable gifview and gifdiff as they are not needed): *./configure --disable-gifdiff --disable-gifview && make*
1. If you have root access, install it with *make install*
1. If not, copy the binary from */gifsicle-1.70/src/gifsicle* to the ewww tool folder (wordpress/wp-content/ewww/gifsicle-custom).

== Frequently Asked Questions ==

= Does the plugin replace existing images? =

Yes, but only if the optimized version is smaller. The plugin should NEVER create a larger image.

= The bulk optimizer doesn't seem to be working, what can I do? =

First, upgrade to the latest version. Since version 1.0.8, each image is given 50 seconds to complete (which actually doesn't include time used by the optimization utilities). You can also increase the setting max_execution_time in your php.ini file. That said, there are other timeouts with Apache, and possibly other limitations of your webhost. If you've tried everything else, the last thing to look for is large PNG files. In my tests on a shared hosting setup, "large" is anything over 300 KB. You can first try decreasing the PNG optimization level in the settings. If that doesn't work, perhaps you ought to convert that PNG to JPG. Screenshots are often done as PNG files, but that is a poor choice for anything with photographic elements.

= What are the supported operating systems? =

I've tested it on Windows (with Apache), Linux, Mac OSX, and FreeBSD.

= How are JPGs optimized? =

Using the command *jpegtran -copy all -optimize -progressive -outfile optimized-file original-file*. Optionally, the -copy switch gets the 'none' parameter if you choose to strip metadata from your JPGs on the options page.

= How are PNGs optimized? =

There are two parts (and both are optional - you can run either or both). First, using the command *pngout-static -s2 original-file*. Second, by using the command *optipng -o2 original-file*. You can adjust the optimization levels for both tools on the settings page. Optipng is a derivative of pngcrush, which is another widely used png optimization utility.

= How are GIFs optimized? =

Using the command *gifsicle -b -O3 --careful original file*. This is particularly useful for animated GIFs, and can also streamline your color palette. That said, if your GIF is not animated, you should strongly consider converting it to a PNG. PNG files are almost always smaller, they just don't do animations. The following command would do this for you on a Linux system with imagemagick: *convert somefile.gif somefile.png*

= Why not just convert GIFs to PNGs then? =

Go for it, version 1.2+ makes this possible so long as you have either one of the PNG optimizers available.

= I want to know more about image optimization, and why you chose these options/tools. =

That's not a question, but since I made it up, I'll answer it. See the Image Optimization sections for [Yslow - Yahoo](http://developer.yahoo.com/performance/rules.html#opt_images) and [Google PageSpeed](https://developers.google.com/speed/docs/best-practices/payload#CompressImages). Pngout was suggested by a user and in tests optimizes better than Optipng, and best (usually) when they are used together.

== Screenshots ==

1. Plugin settings page.
2. Additional optimize column added to media listing. You can see your savings, manually optimize individual images, and restore originals (converted only).
3. Bulk optimization page. You can optimize all your images at once and resume a previous bulk optimization. This is very useful for existing blogs that have lots of images.

== Changelog ==

= future =
* these are possible future bugfixes and/or feature requests, if you see a feature you like here, go vote for it in the support forum
* BuddyPress integration
* SunOS (Solaris/OpenIndiana) support

= 1.4.4 =
* fixed bulk optimization functions for non-English users in NextGEN
* fixed bulk action conflict in NextGEN

= 1.4.3 =
* global configuration for multi-site/network installs
* prevent loading of bundled jquery on WP versions that don't need it to avoid conflicts with other plugins not doing the 'right thing'
* removed enqueueing of common.js to make things run quicker
* fixed hardcoded link for optimizing nextgen thumbs after upload
* added links in media library for one time conversion of images
* better error reporting for pngout auto-install
* no longer alert users of jpegtran update if they are using version 8

= 1.4.2 =
* fixed fatal errors when posix_getpwuid() is missing from server
* removed path restrictions, and fixed path detection for old blogs where upload path was modified

= 1.4.1 =
* FlaGallery and NextGEN Bulk functions are now using ajax functions with nicer progress bars and such
* NextGEN now has ability to optimize selected galleries, or selected images in bulk (FlaGallery already had it)
* NextGEN users can now click a button to optimize thumbnails after uploading new images
* use built-in php mimetype functions to check binaries, saving 'file' command for fallback
* added donation links, since several folks have expressed interest in contributing financially
* bundled jquery and jquery-ui for using bulk functions on older WP versions
* use 32-bit jpegtran binary on 'odd' 64-bit linux servers
* rewrote debugging functionality, available on bulk operations and settings page
* increased compatibility back to 2.8 - hope no one is actually using that, but just in case...

= 1.4.0 =
* fixed bug with missing 'nice' not detected properly
* added: Windows support, includes gifsicle, optipng, and jpegtran executables
* added: FreeBSD support, includes gifsicle, optipng, and jpegtran executables
* rewrote calls to jpegtran to avoid shell-redirection and work in Windows
* jpegtran is now bundled for all platforms
* updated gifsicle to 1.70
* pngout installer and version updated to February 20-21 2013
* removed use of shell_exec()
* fixed warning on ImageMagick version check
* revamped binary checking, should work on more hosts
* check permissions on jpegtran
* rewrote bulk optimizer to use ajax for better progress indication and error handling
* added: 64-bit jpegtran binary for linux servers missing compatibility libraries

= 1.3.8 =
* fixed: finfo library doesn't work on PHP versions below 5.3.0 due to missing constant 
* fixed: resume button doesn't resume when the running the bulk action on groups of images 
* shell_exec() and exec() detection is more robust 
* added architecture information and warning if 'file' command is missing on settings page 
* added finfo functionality to nextgen and flagallery

= 1.3.7 =
* re-compiled bundled optipng and gifsicle on CentOS 5 for wider compatibility

= 1.3.6 =
* fixed: servers with gzip still failed on bulk operations, forgot to delete a line I was testing for alternatives
* fixed: some servers with shell_exec() disabled were not detected due to whitespace issues
* fixed: shell_exec() was not used in PNGtoJPG conversion
* fixed: JPGs not optimized during PNGtoJPG conversion
* allow debug info to be shown via javascript link on settings page
* code cleanup

= 1.3.5 =
* fixed: resuming a bulk optimize on FlAGallery was broken
* added resume button when running the bulk optimize operation to make it easier to resume a bulk optimize

= 1.3.4 =
* fixed optipng check for older versions (0.6.x)
* look in system paths for pngout and pngout-static
* added option for ignoring bundled binaries and using binaries located in system paths instead
* added notices on options page for out-of-date binaries

= 1.3.3 =
* use finfo functions in PHP 5.3+ instead of deprecated mime_content_type
* use shell_exec() to make calls to jpegtran more secure and avoid output redirection
* added bulk action to optimize multiple galleries on the manage galleries page - FlAGallery
* added bulk action to optimize multiple images on the manage images page - FlAGallery

= 1.3.2 =
* fixed: forgot to apply gzip fix to NextGEN and FlAGallery

= 1.3.1 =
* fixed: turning off gzip for Apache broke bulk operations

= 1.3.0 =
* support for GRAND FlAGallery (flash album gallery)
* added ability to restore originals after a conversion (we were already storing the original paths in the database)
* fixed: resized converted images had the wrong original path stored
* fixed: tools get deleted after every upgrade (moved to wp-content/ewww)
* fixed: using activation hook incorrectly to fix permissions on upgrades (now we check when you visit the wordpress admin)
* removed deprecated path settings, custom-built binaries will be copied automatically to the wp-content/ewww folder
* better validation of tools, no longer using 'which'
* removed redundant path checks to avoid extra processing time
* moved NextGEN bulk optimize into NextGEN menu
* NextGEN and FlAGallery functions only run when the associated gallery plugin is active
* turn off page compression for bulk operations to avoid output buffering
* added status messages when attempting automatic installation of jpegtran or pngout
* NEW version of bundled gifsicle can produce better-optimized GIFs
* revamped settings page to combine version info, optimizer status, and installation options
* binaries for Mac OS X available: gifsicle, optipng, and pngout
* images are re-optimized when you use the WP Image Editor (but never converted)
* fixed: unsupported files have empty path stored in meta
* fixed: files with empty paths throw PHP notices in Media Library (DEBUG mode only)
* when a converted attachment is deleted from wordpress, original images are also cleaned up

= 1.2.2 =
* fixed: uninitialized variables
* update links in posts for converted images
* fixed: png2jpg sometimes fills with black instead of chosen color
* fixed: thumbnails for animated gifs were not allowed to convert to png
* added pngout version to debug

= 1.2.1 =
* fixed: wordpress plugin installer removes executable bit from bundled tools

= 1.2.0 =
* SECURITY: bundled optipng updated to 0.7.4
* deprecated manual path settings, please put binaries in the plugin folder instead
* new one-click install option for jpegtran
* one-click for pngout is more efficient (doesn't redownload tarball) if it exists
* optipng and gifsicle now bundled with the plugin
* new *optional* conversion routines check for smallest file format
* added gif2png
* added jpg2png
* added png2jpg
* reorganized settings page (it was getting ugly) and cleaned up debug area
* added poll for feedback
* thumbnails are now optimized in NextGEN during a manual optimize (but not on initial upload)
* utilities have a 'niceness' value of 10 added to give them lower priority

= 1.1.1 =
* fixed not returning results of resized version of image

= 1.1.0 =
* added pngout functionality for even better PNG optimization (disabled by default)
* added options to disable/bypass each tool
* pre-compiled binaries are now available via links on the settings page - try them out and let me know if there are problems

= 1.0.11 =
* path validation was broken for nextgen in previous version, now fixed

= 1.0.10 =
* added the ability to resume a bulk optimization that doesn't complete
* changed path validation for images from wordpress folder to wordpress uploads folder to accomodate users who have located this elsewhere
* minor code cleanup

= 1.0.9 =
* fixed parse error due to php short tags (old habits die hard)

= 1.0.8 =
* added extra progress and time indicators on Bulk Optimize
* allow each image in Bulk Optimize 50 seconds to help prevent timeouts (doesn't work if PHP's Safe Mode is turned on)
* added check for safe mode (because we can't function that way)
* changed default PNG optimization to level 2 (8 trials) to improve performance
* restored calls to flush output buffers for php 5.3

= 1.0.7 =
* added bulk optimize to Tools menu and re-optimize for individual images with NextGEN
* fixed optimizer function to skip images where the utilities are missing
* added check to ensure user doesn't pass arguments in utility paths
* added check to prevent utilities from being located in web root
* changed optipng level setting from text entry to drop-down to prevent arbitrary script execution
* more code cleanup

= 1.0.6 = 
* ported basic NextGEN integration from WP Smush.it (no bulk or re-optimize... yet)
* added extra output for bulk operations
* if the jpeg optimization produces an empty file, it will be discarded (instead of overwriting your originals)
* output filesize in custom column for Media Library
* fixed various PHP notices/warnings

= 1.0.5 =
* missed documentation updates in 1.0.4 - sorry

= 1.0.4 =
* Added trial with -progressive switch for JPGs (jpegtran), thanks to Alex Vojacek for noticing something was missing. We still check to make sure the progressive option is better, just in case.
* tested against 3.4-RC3

= 1.0.3 =
* Allow user to specify PNG optimization level
* Code and screenshot cleanup
* Settings page beautification (if you can think of further improvements, feel free to use the support link)
* Bulk Optimize action drop-down on Media Library - ported from Regenerate Thumbnails plugin

= 1.0.2 =
* Forgot to add Settings link to warning message when tools are missing

= 1.0.1 =
* Fixed optimization level for optipng (-o3)
* Added Installation and Support links to Settings page, and a link to Settings from the Plugin page.

= 1.0.0 =
* First release (forked from CW Image Optimizer)

== Upgrade Notice ==

= 1.4.4 =

bugfix release for nextgen users only, everyone else can ignore this release

= 1.4.0 =
sorry about the accidental release of 1.3.9, use this one instead

= 1.3.7 =
If you are using 1.3.6 without problems, you can safely ignore 1.3.7. It is a compatibility fix only.

= 1.3.0 =
Removed path options, moved optimizers to wp-content/ewww. Requires write permissions on the wp-content folder. Custom compiled binaries should automatically be moved to the wp-content/ewww folder also.

= 1.2.1 =
SECURITY: bundled optipng is 0.7.4 to address a vulnerability. Fixed invalid missing tools warning. Added conversion operations gif2png, png2jpg, and jpg2png. Setting paths manually will be disabled in a future release, as the plugin now automatically looks in the plugin folder.

= 1.1.0 =
Added pngout functionality for even better PNG optimization (disabled by default). Settings page now has links to stand-alone binaries of gifsicle and optipng. Please try them out and report any problems.

= 1.0.11 =
Added resume function if Bulk Optimization fails

= 1.0.7 =
Enhanced NextGEN integration, and security enhancements for user data provided to exec() command

= 1.0.6 =
Made jpeg optimization safer (so an empty file doesn't overwrite the originals), and added NextGEN Gallery integration

= 1.0.5 =
Improved optimization for JPGs significantly, by adding -progressive flag. May want to run the bulk operation on all your JPGs (or your whole library)

= 1.0.1 =
Improved performance for PNGs by specifying proper optimization level

== Contact and Credits ==

Written by [Shane Bishop](http://www.shanebishop.net). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it.
