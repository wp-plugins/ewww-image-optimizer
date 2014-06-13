=== EWWW Image Optimizer ===
Contributors: nosilver4u
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MKMQKCBFFG3WW
Tags: images, image, attachments, attachment, optimize, optimization, nextgen, buddypress, flagallery, flash-gallery, lossless, lossy, photos, photo, picture, pictures, seo, compression, image-store, imstore, slider, image editor, gmagick, wp-symposium, meta-slider, metaslider, jpegtran, gifsicle, optipng, pngout, pngquant, gmedia
Requires at least: 3.5
Tested up to: 3.9.1
Stable tag: 1.9.2
License: GPLv3

Reduce file sizes for images within WordPress including NextGEN, GRAND FlAGallery and more. Uses jpegtran, optipng, pngout, pngquant, and gifsicle.

== Description ==

The EWWW Image Optimizer is a WordPress plugin that will automatically and losslessly optimize your images as you upload them to your blog. It can also optimize the images that you have already uploaded in the past. It is also possible to convert your images automatically to the file format that will produce the smallest image size (make sure you read the WARNINGS). It can also optionally apply lossy reductions for PNG images.

By default, EWWW Image Optimizer uses lossless optimization techniques, so your image quality will be exactly the same before and after the optimization. The only thing that will change is your file size. The one small exception to this is GIF animations. While the optimization is technically lossless, you will not be able to properly edit the animation again without performing an --unoptimize operation with gifsicle. The gif2png and jpg2png conversions are also lossless but the png2jpg process is not lossless. Lossy optimization is available for PNG files. While pngquant tries very hard to maintain visual quality, lossy compression always has the potential for quality loss.

Images are optimized using the [jpegtran](http://jpegclub.org/jpegtran/), [optipng](http://optipng.sourceforge.net/), [pngout](http://advsys.net/ken/utils.htm), [pngquant](http://pngquant.org/), and [gifsicle](http://www.lcdf.org/gifsicle/) image tools (available for free). For PNG files, optipng or pngout can be used for lossless compression, and pngquant is available for lossy compression. If you want the best optimization, install all three, set optipng to level 3 (beyond that is just crazy and rarely yields significant gains) and pngout to level 0. Images are converted using the above tools and GD or 'convert' (ImageMagick).

EWWW Image Optimizer calls optimization utilities directly which is well suited to shared hosting situations where these utilities may already be installed. Pre-compiled binaries/executables are provided for optipng, gifsicle, and jpegtran. Pngout can be installed with one-click from the settings page. If none of that works, there is a cloud option that will work for those who cannot run the optimizers on their own server.

**Why use EWWW Image Optimizer?**

1. **Your pages will load faster.** Smaller image sizes means faster page loads. This will make your visitors happy, and can increase ad revenue.
1. **Faster backups.** Smaller image sizes also means faster backups.
1. **Less bandwidth usage.** Optimizing your images can save you hundreds of KB per image, which means significantly less bandwidth usage.
1. **Super fast.** The plugin can run on your own server, so you don’t have to wait for a third party service to receive, process, and return your images. You can optimize hundreds of images in just a few minutes. PNG files take the longest, but you can adjust the settings for your situation.
1. **Better PNG optimization.** You can use pngout, optipng, and pngquant in conjunction.
1. **Root access not needed** Pre-compiled binaries are made available to install directly within the Wordpress folder, and cloud optimization is provided for those who cannot run the binaries locally.
1. **Optimize almost anything** Using the Optimize More tool, and the wp_image_editor class extension, any image in Wordpress can be optimized.

If you need a version of this plugin for cloud use only, see [EWWW Image Optimizer Cloud](http://wordpress.org/plugins/ewww-image-optimizer-cloud/). It is much more compact as it does not contain any binaries or any mention of the exec() function.

= Bulk Optimize =

There are two functions on the Bulk Optimize page. One is to optimize all images in the Media Library. The Scan and Optimize is for everything else. Officially supported galleries (GRAND FlaGallery and NextGEN) have their own Bulk Optimize pages. 

= Skips Previously Optimized Images =

All optimized images are stored in the database so that the plugin does not attempt to re-optimize them unless they are modified. On the Bulk Optimize page you can view a list of already optimized images. You may additionally choose to empty the table, remove individual images from the list, or use the Force optimize option to override the default behavior. Before running the Bulk Optimize the first time, you must run an import operation that scans your Media Library for any previously optimized images (from before version 1.8.0). The re-optimize links on the Media Library page also force the plugin to ignore the previous optimization status of images.

= WP Image Editor = 

All images created by the new WP_Image_Editor class in WP 3.5 will be automatically optimized. Current implementations are GD, Imagick, and Gmagick. Images optimized via this class include Meta Slider, BuddyPress Activity Plus (thumbs), WP Retina 2x, Imsanity, Simple Image Sizes and probably countless others. If you have a plugin that uses WP_Image_Editor and would like EWWW IO to be able to optimize previous uploads, post a thread in the support forums.

= Optimize Everything Else =

As of version 1.7.0, site admins can specify any folder within their wordpress folder to be optimized. The 'Scan and Optimize' option under Media->Bulk Optimize will optimize theme images, BuddyPress avatars, BuddyPress Activity Plus images, Meta Slider slides, WP Symposium images, GD bbPress attachments, Grand Media Galleries, and any user-specified folders. Additionally, this tool can run on an hourly basis via wp_cron to keep newly uploaded images optimized. Scheduled optimization does NOT include Media Library images, because they are already optimized on upload.


= NextGEN Gallery =

Features optimization on upload capability, re-optimization, and bulk optimizing. The NextGEN Bulk Optimize function is located near the bottom of the NextGEN menu, and will optimize all images in all galleries. It is also possible to optimize groups of images in a gallery, or multiple galleries at once.
NOTE: Does not optimize thumbnails on initial upload for legacy (1.9.x) versions of NextGEN, but instead provides a button to optimize thumbnails after uploading images. No further updates will be provided for NextGEN legacy support, use NextCellent instead.

= NextCellent Gallery =

Features all the same capability as NextGEN, and is the continuation of legacy (1.9.x) NextGEN support.

= GRAND Flash Album Gallery =

Features optimization on upload capability, re-optimization, and bulk optimizing. The Bulk Optimize function is located near the bottom of the FlAGallery menu, and will optimize all images in all galleries. It is also possible to optimize groups of images in a gallery, or multiple galleries at once.

= Image Store =

Uploads are automatically optimized. Look for Optimize under the Image Store (Galleries) menu to see status of optimization and for re-optimization and bulk-optimization options. Using the Bulk Optimization tool under Media Library automatically includes all Image Store uploads.

= Translations =

Translators: 
Romanian translation by MediasInfo.ro.
Spanish translation by Andrew Kurtis of WebHostingHub, looking for new maintainer.
Dutch translation by Ludo Rubben.

1. Please post in the support forums announcing your intent to translate the plugin into a particular language. 
1. Download ewww-image-optimizer.pot from the plugin /languages/ folder.
1. Fill in the msgstr for each msgid and complete the header information as best as you can (recommended to use PoEdit).
1. Save it as a .po file.
1. Submit it via the form at http://www.shanebishop.net/contact-me/


== Installation ==

1. Upload the 'ewww-image-optimizer' plugin to your '/wp-content/plugins/' directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Ensure jpegtran, optipng, pngout and gifsicle are installed on your Linux server (basic installation instructions are below if they are not). You will receive a warning when you activate the plugin if they are not present. This message will go away once you have them installed.
1. The plugin will attempt to install jpegtran, optipng, and gifsicle automatically for you. This requires that the wp-content folder is writable by the user running the web server.
1. If the automatic install did not work, find the appropriate binaries for your system in the ewww-image-optimizer plugin folder, copy them to wp-content/ewww/ and remove the OS 'tag' (like -linux or -fbsd). No renaming is necessary on Windows, just copy the .exe files to the wp-content/ewww folder. IMPORTANT: Do not symlink or modify the binaries in any way, or they will not pass the security checks. If you transfer files via FTP, be sure to transfer in binary mode, not ascii or text.
1. If the binaries don't run locally, you can sign up for the EWWW IO cloud service to run them via a third-party server: http://www.exactlywww.com/cloud/
1. *Optional* Visit the settings page to enable/disable specific tools and turn on advanced optimization features.
1. Done!

The videos below are somewhat outdated, but still give you a good idea of the capabilities and functions of the plugin.

EWWW IO Installation and Configuration:
[youtube http://www.youtube.com/watch?v=uEU4DbDm3r0]

Using EWWW IO:
[youtube http://www.youtube.com/watch?v=6NKBfmE00vM]

= Installing pngout =

Pngout is not enabled by default because it is resource intensive. Optipng is the preferred PNG optimizer if you have resource (CPU) constraints. Pngout is also not open-source for those who care about such things, but the command-line version is free.

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
1. Uncompress gifsicle: *tar xvzf gifsicle-1.78.tar.gz && cd gifsicle-1.78*
1. Configure and compile gifsicle (we disable gifview and gifdiff as they are not needed): *./configure --disable-gifdiff --disable-gifview && make*
1. If you have root access, install it with *make install*
1. If not, copy the binary from */gifsicle-1.78/src/gifsicle* to the ewww tool folder (wordpress/wp-content/ewww/gifsicle-custom).

= Installing pngquant =

1. Pngquant is bundled with the plugin. If it isn't working for you, keep going...
1. Download the latest version of [pngquant](http://pngquant.org/) to your server
1. Uncompress pngquant: *tar xvjf pngquant-2.0.2-src.tar.bz2 && cd pngquant-2.0.2*
1. Compile pngquant: *make*
1. If you have root access, install it with *make install*
1. If not, copy the binary from */pngquant-2.0.2/pngquant* to the ewww tool folder (wordpress/wp-content/ewww/pngquant-custom).

== Frequently Asked Questions ==

= Does the plugin replace existing images? =

Yes, but only if the optimized version is smaller. The plugin should NEVER create a larger image.

= Can I resize my images with this plugin? =

No, that would be a lossy operation, and we try to avoid that. Use Imsanity.

= Can I lower the compression setting for JPGs to save more space? =

Again, that would be a lossy operation, and we try to avoid that. Use Imsanity.

= The bulk optimizer doesn't seem to be working, what can I do? =

Each image is given 50 seconds to complete (which actually doesn't include time used by the optimization utilities). If that doesn't seem to do the trick, you can also increase the setting max_execution_time in your php.ini file. That said, there are other timeouts with Apache, and possibly other limitations of your webhost. If you've tried everything else, the last thing to look for is large PNG files. In my tests on a shared hosting setup, "large" is anything over 300 KB. You can first try decreasing the PNG optimization level in the settings. If that doesn't work, perhaps you ought to convert that PNG to JPG. Screenshots are often done as PNG files, but that is a poor choice for anything with photographic elements.

= What are the supported operating systems? =

I've tested it on Windows (with Apache), Linux, Mac OSX, FreeBSD, and Solaris (v10). The cloud service will run on any OS.

= How are JPGs optimized? =

Using the command *jpegtran -copy all -optimize -progressive -outfile optimized-file original-file*. Optionally, the -copy switch gets the 'none' parameter if you choose to strip metadata from your JPGs on the options page.

= How are PNGs optimized? =

There are three parts (and all are optional). First, using the command *pngquant original-file*, then using the commands *pngout-static -s2 original-file* and *optipng -o2 original-file*. You can adjust the optimization levels for both tools on the settings page. Optipng is an automated derivative of pngcrush, which is another widely used png optimization utility.

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
* these are current feature requests, if you see something you like here, go vote for it in the support forum
* full GMedia support
* webp support
* jpegmini server integration for cloud users
* If you would like to help translate this plugin in your language, post a thread on the support forums.
* looking for new maintainer for Spanish translation

= 1.9.2 =
* fixed: memory limit exceeded when counting total savings on settings page
* fixed: application/octet-stream is accepted as valid output for mimetype check on executables
* added: PngOptimizerCL for even better optimization of PNG images on cloud service
* changed: cloud processing nodes upgraded for faster image processing
* changed: made queries for resuming bulk operations more efficient to avoid running into max query length problems
* fixed: images that were not processed (cloud or otherwise) can be optimized later (they are no longer stored in ewwwio_images table)
* changed: more efficient verification of cloud api keys

= 1.9.1 =
* fixed: escapeshellarg command breaks Windows filenames
* fixed: newer versions of pngquant not detected
* fixed: properly check paletted/indexed PNG files for transparency (requires GD)
* fixed: images smaller than imsanity resize limit trigger notice
* changed: exclude full-size from lossy optimization applies to lossy conversions too
* changed: no more caching of cloud key verification results, since verification is 300x faster, and only called when we absolutely need it
* added: status for pngquant on settings page when lossy optimization is enabled
* added: Optimized/webview sizes in FlaGallery are tracked properly, and optimized during bulk operations, and manual one-time optimizations.
* added: use nextgen2 hook for adding action link in gallery management pages

= 1.9.0 =
* changed: verification results for cloud optimization are still cached, but actual optimization requires pre-verification to maintain load-balancing
* added: NextCellent Gallery support - no future development will be done for NextGEN 1.9.13, all future development will be on NextCellent.
* updated translations for Romanian and Dutch
* fixed some warnings and notices
* added GMedia folder to Scan and Optimize function
* show cumulative savings in status section
* added: filter to bypass optimization for developer use
* added: option to bypass optimization for small images

= 1.8.5 =
* fixed: images with empty metadata count as unoptimized images on Bulk Optimize
* changed: Import process split into batches via AJAX to make it less likely to timeout and use less memory
* changed: Bulk Optimize page uses less memory and is quicker to load
* fixed: custom column in NextGEN galleries works again with NextGEN 2.0.50+
* changed: cloud api cache refreshes properly when visiting Settings page
* fixed: license exceeded messages do not stall Bulk Optimize incorrectly
* fixed: warning on Bulk Optimize for sites using UTC
* fixed: user-specified paths to optimize did not work if using multi-site WP with plugin activated per-site
* fixed: gifsicle sometimes generates slightly larger images (not anymore)

= 1.8.4 =
* fixed: Import process is much faster by about 50x

= 1.8.3 =
* fixed: tools cannot be found if there are spaces in the WP paths
* changed: API key validation is now cached to greatly reduce page load time, mostly on the admin side, but also for any sites that generate or allow uploading images on the front-end
* fixed: a few WP Retina @2x images were not being optimized, and none of them were stored in the ewwwio_images table properly
* new: better compression for cloud users via advpng
* new: lossy compression for PNG images via pngquant
* changed: Bulk Optimize loads much quicker (mostly noticable on sites with thousands of images)

= 1.8.2 =
* updated Romanian translation
* removed: potentially long-running query from upgrade
* fixed: cloud queries were using the wrong hostname, all cloud users must apply this update to avoid service degradation

= 1.8.1 =
* fixed: ewww_image_optimizer_aux_images_loop() undefined causes any calls to WP_Image_Editor to fail (breaks lots of stuff)

= 1.8.0 =
* fixed: debug output not working properly on bulk optimize
* changed: when cloud license has been exceeded, the optimizer will not attempt to upload images, and bulk operations will stop immediately
* fixed: unnecessary decimals will not be displayed for file-sizes in bytes
* added: button to stop bulk optimization process
* fixed: rewrote escapeshellarg() to avoid stripping accented characters from filenames
* fixed: problems with apostrophes in filenames
* changed: Optimize More and Bulk Optimize are now on the same page
* changed: After running Optimize More, you can Show Optimized Images and Empty Table without refreshing the page.
* fixed: blank page when resetting bulk status in flagallery
* change: already optimized images in Media Library will not be re-optimized by default via bulk tool
* fixed: FlaGallery version 4.0, optimize on upload now works with plupload
* fixed: proper validation that an image has been removed from the auxilliary images table
* move more code into admin_init to improve page load on front-end
* added: ability to specify number of seconds between images (throttling)
* added: nextgen and grand flagallery thumb optimization is now stored in database
* change: significant speed improvement, optimizer only checks for the tools it needs for the current image
* fixed: urls for converted resizes were not being updated in posts
* fixed: attempt to convert PNGs with empty alpha channels after optimization on first pass, instead of on re-optimization

= 1.7.6 =
* fixed: color of progressbar for 4 more admin themes in WP 3.8
* changed: metadata stripping now applies to PNG images, but only if using optipng 0.7.x
* added: ability to remove individual images from the Optimize More table
* fixed: Optimize More was using case-insensitive queries for matching paths
* fixed: Optimize More was unable to record image sizes over 8388607 bytes
* removed: obsolete jquery 1.9.1 file used for maintaining backwards compatiblity with really old versions of WP
* fixed: weirdness with paths preventing Windows servers from activating, and cleanup of plugin path code

= 1.7.5 =
* new version of gifsicle (1.78), for more detail, see http://www.lcdf.org/gifsicle/changes.html
* proper detection of Cloudinary images instead of error message
* plays nicer with Imsanity, detect when a newly uploaded image has been modified and optimized already (instead of re-optimizing)
* Dutch translation - nl_NL
* Romanian translation - ro_RO
* Spanish translation - es_ES
* Cloudinary integration: auto-upload after optimization when uploading to Media Library, must be enabled in settings
* debugging output for Media Library (let's you see resizes)
* visual tweaking for upcoming WP 3.8
* better checking for safe_mode

= 1.7.4 =
* fixed: some settings were set to incorrect defaults after enabling and disabling cloud features
* fixed: invalid status on some systems for 'tar' command
* new: SunOS support - OpenIndiana and Solaris
* fixed: resizes not properly checking for re-optimization prevention

= 1.7.3 =
* fixed: some security plugins disable Optimize More - use install_themes permission instead of edit_themes
* fixed: table schema changes not firing on upgrade
* changed: bulk_attachment variables are not autoloaded to improve performance

= 1.7.2 =
* added: internationalization - need volunteers to provide translations. If interested, post a support thread with the language you would like to help with.
* fixed: Import button not shown on Optimize More in some cases
* fixed: Bulk Optimize for Nextgen was broken
* changed: file comparison from md5sum to filesize for Optimize More to improve load time
* added: quota information for cloud users on settings page
* fixed: sub-folders of uploads directory were not allowed if /uploads is outside of wp folder
* changed: increased cloud_verify timeout to avoid false results
* added: link to status page for cloud service on settings page
* fixed: debug log created if it does not exist already

= 1.7.1 =
* fixed: syntax error causing white screen of death for Nextgen v2

= 1.7.0 =
* added: ability to optimize specified folders within your wordpress install
* added: option to optimize on a schedule for images that cannot be automatically optimized on upload (buddypress, symposium, metaslider, user-specified folders)
* added: WP Symposium support via 'Optimize More' in Tools menu
* added: BuddyPress Activity Plus support via 'Optimize More'
* fixed: unnecessary check for 'file' field in attachment metadata
* fixed: network-level settings are not reset on deactivation and reactivation
* fixed: blog-level settings not displayed when activated at the blog-level on multi-site
* added: Any plugin that uses wp_image_editor (GD, Imagick, and Gmagick implementations) will be auto-optimized on upload
* fixed: Optimize More will crash if one of the standard folders does not exist (e.g.: buddypress avatar folders)
* fixed: filenames are escaped to prevent potential crashes and security risks
* fixed: temporary jpgs are checked to be sure they exist to avoid warnings
* fixed: prevent warnings on bulk optimize due to empty arrays
* fixed: don't check permissions until after we know file exists
* fixed: WP get_attached_file() doesn't always work, try other methods to get attachment path
* removed: deprecated setting to skip utility verification
* fixed: init not firing for plugins with front-end functionality
* fixed: suppress warnings if corrupt jpg crashes jpegtran
* added: screencasts on plugin Installation page

= 1.6.3 =
* plugin will failover gracefully if one of the cloud optimization servers is offline
* prevent excess database calls when optimizing theme images
* fixed plugin mangles metadata for Image Store plugin
* added optimization support for Image Store plugin
* verify md5 on buddypress optimization, so changed images will get re-optimized by the bulk tool
* cleaned up settings page (mostly) for cloud users

= 1.6.2 =
* added license exceeded status into status message so users know if they've gone over
* prevent tool checks and cloud verification from firing on every page load, yikes...

= 1.6.1 =
*fixed: temporary jpgs were not being deleted (leftovers from testing for last release)
*fixed: jpgs would not be converted to pngs if jpgs had already been optimized
*fixed: cloud service not converting gif to png

= 1.6.0 =
* Cloud Optimization option (BETA: get your free API key at http://www.exactlywww.com/cloud/)
* fixed if exec() is disabled or safe mode is on, don't bother testing local tools
* more tweaks for exec() detection, including suhosin extension

= 1.5.0 =
* BuddyPress integration to optimize avatars
* added function to optimize all images in currently active theme
* full compatibility with NextGEN 2.0.x
* thumbnails are now optimized automatically on upload with NextGEN 2.0.x
* fixed detection of disabled exec() function when exec is the first function in the list
* use internal wordpress functions for retrieving image path, displaying filesize, building redirect urls, and downloading pngout

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

= 1.8.2 = 
* All cloud users must apply this update to avoid service degradation

= 1.8.0 =
* Bulk Optimize page: Import to the custom ewwwio table is mandatory (one time) before running Bulk Optimize, and highly recommended for all users to prevent duplicate optimizations. Optimize More and Bulk Optimize are now on one page.

= 1.7.6 =
* metadata stripping now applies to PNG images, but only if using optipng 0.7.x, you may want to run a bulk optimize on all your PNG images to make sure you have the best possible optimization

= 1.7.5 =
* New version of gifsicle that has quite a few improvements. If you have restricted permissions on the wp-content/ewww/ folder, you may need to temporarily change them to allow the plugin to perform the gifsicle upgrade automatically.

= 1.7.2 =
* Optimize More table format has changed, make sure to visit Optimize More and Convert your table immediately after upgrade if you are running it in scheduled mode.

= 1.7.0 =
* More third-party plugins supported via custom paths, and the Optimize More tool (which can also run via cron now). Also check out new screencasts on the Installation page.

= 1.6.2 =
* All Cloud users should upgrade immediately to avoid extended page load times

= 1.6.1 =
* New Cloud Optimization option for those who can't (or won't) enable exec() on their servers (BETA: get your free API key at http://www.exactlywww.com/cloud/)

= 1.5.0 =
Fixes and enhancements for NextGEN 2, Buddypress support, and theme image optimization

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
[Hammer](http://thenounproject.com/noun/hammer/#icon-No1306) designed by [John Caserta](http://thenounproject.com/johncaserta) from The Noun Project.
[Images](http://thenounproject.com/noun/images/#icon-No22772) designed by [Simon Henrotte](http://thenounproject.com/Gizmodesbois) from The Noun Project.

== Webhosts ==

In general, these lists only apply to shared hosting services. If the providers below have VPS or dedicated server options, those will likely work just fine. If you have any contributions or corrections to these lists, please contact me via the form at http://www.shanebishop.net

Webhosts where things work out of the box.

* [Bluehost](http://www.bluehost.com)
* [Dreamhost](http://www.dreamhost.com)
* [GoDaddy](http://www.godaddy.com) (only with PHP 5.3)
* [gPowerHost](https://gpowerhost.com/)
* [HostGator](http://www.hostgator.com)
* [Hetzner Online](http://www.hetzner.de)
* [Hosterdam](http://www.hosterdam.com) (FreeBSD)
* [iFastNet](https://ifastnet.com/portal/) (with custom php.ini from customer support)
* [Namecheap](http://www.namecheap.com)
* [OVH](http://www.ovh.co.uk)
* [WebFaction](http://www.webfaction.com)

Webhosts where the plugin will only work in cloud mode or only some tools are installed locally.

* ipower
* Gandi
* ipage (JPG only)
* WP Engine - use EWWW Image Optimizer Cloud fork: http://wordpress.org/plugins/ewww-image-optimizer-cloud/

