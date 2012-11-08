=== EWWW Image Optimizer ===
Contributors: nosilver4u
Tags: images, image, attachments, attachment
Requires at least: 2.9
Tested up to: 3.4.2
Stable tag: 1.2.1
License: GPLv3

Reduce file sizes and improve performance for images within WordPress including NextGEN Gallery. Uses jpegtran, optipng/pngout, and gifsicle.

== Description ==

The EWWW Image Optimizer is a WordPress plugin that will automatically and losslessly optimize your images as you upload them to your blog. It can also optimize the images that you have already uploaded in the past. It is also now possible to convert your images automatically to the file format that will produce the smallest image (make sure you read the WARNINGS).

By default, EWWW Image Optimizer uses lossless optimization techniques, so your image quality will be exactly the same before and after the optimization. The only thing that will change is your file size. The one small exception to this is GIF animations. While the optimization is technically lossless, you will not be able to properly edit the animation again without performing an --unoptimize operation with gifsicle. The gif2png and jpg2png conversions are also lossless but the png2jpg process is not lossless.

Images are optimized using the [jpegtran](http://jpegclub.org/jpegtran/), [optipng](http://optipng.sourceforge.net/), [pngout](advsys.net/ken/utils.htm), and [gifsicle](http://www.lcdf.org/gifsicle/) image tools (available for free). For PNG files, either optipng or pngout can be used. If you want the best optimization, install both, set optipng to level 3 (beyond that is just crazy and rarely yields significant gains) and pngout to level 0. Images are converted using the above tools and GD or 'convert' (ImageMagick).

EWWW Image Optimizer calls optimization utilities directly which is better suited to shared hosting situations where these utilities may already be installed. In addition, optipng and gifsicle are included with the plugin. Jpegtran and pngout can be installed with one-click from the settings page if your host doesn't already have them.

**Why use EWWW Image Optimizer?**

1. **Your pages will load faster.** Smaller image sizes means faster page loads. This will make your visitors happy, and can increase ad revenue.
1. **Faster backups.** Smaller image sizes also means faster backups.
1. **Less bandwidth usage.** Optimizing your images can save you hundreds of KB per image, which means significantly less bandwidth usage.
1. **Super fast.** Because it runs on your own server, you don’t have to wait for a third party service to receive, process, and return your images. You can optimize hundreds of images in just a few minutes. Png files take the longest, but you can adjust the settings for your situation.
1. **Better PNG optimization using pngout and optipng in conjunction.
1. **Root access not needed** Pre-compiled binaries are made available to install directly within the plugin folder. 

= NextGEN Integration =

Features re-optimization capability, and bulk optimizing. The NextGEN Bulk Optimize function is located under the Wordpress Tools menu, and should optimize all images in all galleries. If anyone has a better idea for where the tool should go, feel free to post in the support area, or on the plugin homepage. Alternatively, if you can figure out a way to hook into the existing NextGEN menu, that would be ideal. I just can't seem to find a way to do that.
NOTE: Does not optimize thumbnails on initial upload, must re-optimize images to optimize thumbnails.

== Installation ==

1. Upload the 'ewww-image-optimizer' plugin to your '/wp-content/plugins/' directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Ensure jpegtran, optipng, pngout and gifsicle are installed on your Linux server (basic installation instructions are below if they are not). You will receive a warning when you activate the plugin if they are not present. This message will go away once you have them installed. 
1. *Optional* Visit the settings page to configure paths, enable/disable specific tools and turn on advanced optimization features.
1. Done!

= Installing pngout =

Pngout is new in version 1.1.0 and is not enabled by default because it is resource intensive. Optipng is the preferred PNG optimizer if you have resource (CPU) constraints. Pngout is also not open-source for those who care about such things, but is free.
1. Go to the settings page.
1. Click one of the links near the middle of the page to install pngout for your server, and the plugin will download the pngout archive, unpack it, and install the version that you chose. If you don't know what architecture your server is, you can stick with the i386 or ask your webhost about it. You can always choose a different version later, and the plugin will simply update the version that is used.
1. Adjust the pngout level according to your needs. Level 0 gives the best results, but can take up to a minute or more on a single image.
1. If the one-click install isn't working for you, download the latest version from http://www.jonof.id.au/kenutils and extract the appropriate pngout-static to the plugins/ewww-image-optimizer/ folder.

= Installing optipng =

1. Optipng is now bundled with the plugin. If it isn't working for some reason, keep going...
1. If you have root access to your server, you can install optipng from the standard repositories (yum/rpm or apt/deb). If you are on shared hosting, read on... These steps can/should generally all be done via the command line
1. Download the latest stable version of [optipng](http://optipng.sourceforge.net/) to your home directory
1. Ensure libpng and zlib are installed. If they are not, you're on your own there (but maybe you need a new web host...)
1. Uncompress optipng: *tar xvzf optipng-0.7.4.tar.gz && cd optipng-0.7.4*
1. Configure and compile optipng: *./configure && make*
1. If you have root access, install it with *make install*
1. If not, copy the binary from */optipng-0.7.4/src/optipng/optipng* to the plugin folder (wordpress/wp-content/plugins/ewww-image-optimizer).

= Installing jpegtran =

1. Try the one-click install on the settings page, or download it manually and place it in the plugin folder (wordpress/wp-content/plugins/ewww-image-optimizer).
1. If you own your own server, or have root access, it is part of the libjpeg-turbo-progs on Debian/Ubuntu, and likely something similar on rpm distros (Fedora, CentOS, RHEL, SuSE).

= Installing gifsicle =

1. Gifsicle is now bundled with the plugin. If it isn't working for you, keep going...
1. If you have root access to your server, you can install gifsicle from the standard repositories (yum/rpm or apt/deb). If you are on shared hosting, read on... These steps can/should generally all be done via the command line
1. Download the latest version of [gifsicle](http://www.lcdf.org/gifsicle/) to your home directory
1. Uncompress gifsicle: *tar xvzf gifsicle-1.67.tar.gz && cd gifsicle-1.67*
1. Configure and compile gifsicle (we disable gifview and gifdiff as they are not needed): *./configure --disable-gifdiff --disable-gifview && make*
1. If you have root access, install it with *make install*
1. If not, copy the binary from */gifsicle-1.67/src/gifsicle* to the plugin folder (wordpress/wp-content/plugins/ewww-image-optimizer).

== Frequently Asked Questions ==

= The bulk optimizer doesn't seem to be working, what can I do? =

First, upgrade to the latest version. Since version 1.0.8, each image is given 50 seconds to complete (which actually doesn't include time used by the optimization utilities). You can also increase the setting max_execution_time in your php.ini file. That said, there are other timeouts with Apache, and possibly other limitations of your webhost. If you've tried everything else, the last thing to look for is large PNG files. In my tests on a shared hosting setup, "large" is anything over 300 KB. You can first try decreasing the PNG optimization level in the settings. If that doesn't work, perhaps you ought to convert that PNG to JPG. Screenshots are often done as PNG files, but that is a poor choice for anything with photographic elements.

= Can I use EWWW Image Optimizer with a Windows server? =

Not right now, but maybe if I run out of features to implement for Linux. All of the utilities have windows builds, but it would require a significant rewrite to make it platform agnostic.

= How are JPGs optimized? =

Using the command *jpegtran -copy all -optimize -progressive original-file > optimized-file*. Optionally, the -copy switch gets the 'none' parameter if you choose to strip metadata from your JPGs on the options page.

= How are PNGs optimized? =

There are two parts (and both are optional - you can run either or both). First, using the command *pngout-static -s2 original-file*. Second, by using the command *optipng -o2 original-file*. You can adjust the optimization levels for both tools on the settings page. Optipng is a derivative of pngcrush, which is another widely used png optimization utility.

= How are GIFs optimized? =

Using the command *gifsicle -b -O3 --careful original file*. This is particularly useful for animated GIFs, and can also streamline your color palette. That said, if your GIF is not animated, you should strongly consider converting it to a PNG. PNG files are almost always smaller, they just don't do animations. The following command would do this for you on a Linux system with imagemagick: *convert somefile.gif somefile.png*

= Why not just convert GIFs to PNGs then? =

Go for it, version 1.2 makes this possible so long as you have either one of the PNG optimizers available.

= I want to know more about image optimization, and why you chose these options/tools. =

That's not a question, but since I made it up, I'll answer it. See the Image Optimization sections for [Yslow - Yahoo](http://developer.yahoo.com/performance/rules.html#opt_images) and [Google PageSpeed](https://developers.google.com/speed/docs/best-practices/payload#CompressImages). Pngout was suggested by a user and in tests optimizes better than Optipng, and best (usually) when they are used together.

== Screenshots ==

1. Plugin settings page.
2. Additional optimize column added to media listing. You can see your savings, or manually optimize individual images.
3. Bulk optimization page. You can optimize all your images at once. This is very useful for existing blogs that have lots of images.

== Changelog ==

= 1.2.2 =
* fixed: warnings on uninitialized variable $processed
* update links in posts for converted images
* fixed: png2jpg sometimes fills with black instead of chosen color
* fixed: uninitialized constants

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
