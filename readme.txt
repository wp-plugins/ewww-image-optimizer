=== EWWW Image Optimizer ===
Contributors: nosilver4u
Tags: images, image, attachments, attachment
Requires at least: 2.9
Tested up to: 3.4
Stable tag: 1.0.6
License: GPLv3

Reduce image file sizes and improve performance using Linux image optimizers within WordPress Media Library and NextGEN Gallery. Uses jpegtran, optipng, and gifsicle.

== Description ==

The EWWW Image Optimizer is a WordPress plugin that will automatically and losslessly optimize your images as you upload them to your blog. It can also optimize the images that you have already uploaded in the past.

Because EWWW Image Optimizer uses lossless optimization techniques, your image quality will be exactly the same before and after the optimization. The only thing that will change is your file size. The one small exception to this is GIF animations. While the optimization is technically lossless, you will not be able to properly edit the animation again without performing an --unoptimize operation with gifsicle.

The EWWW Image Optimizer plugin is based heavily on the CW Image Optimizer plugin, which in turn is based upon the WP Smush.it plugin. Unlike the WP Smush.it plugin, your files won’t be uploaded to a third party. Your files are optimized using the Linux [jpegtran](http://jpegclub.org/jpegtran/), [optipng](http://optipng.sourceforge.net/), and [gifsicle](http://www.lcdf.org/gifsicle/) image tools (available for free). 

The primary reason for creating this plugin was that CW Image Optimizer uses littleutils. While littleutils is a fine piece of software, it does not do any of the optimization work itself, but simply calls upon other utilities with pre-configured options. The result is that you need to have these other utilities already installed to build/compile the binaries for littleutils. In contrast, EWWW Image Optimizer calls the optimization utilities directly (which may also allow us to offer more flexibility in the future). This is better suited to shared hosting situations where these utilities may already be installed. The programs we use (jpegtran, optipng, and gifsicle) generally have very minimal dependencies, so all you will need is a hosting account with shell access, and build utilities installed. You can then tell EWWW Image Optimizer where you compiled these utilities. I use Bluehost, which meets these requirements, and Dreamhost is another suitable alternative. There are likely others out there that I am not aware of.

**Why use EWWW Image Optimizer (some of the same reasons for using CW Image Optimizer)?**

1. **Your pages will load faster.** Smaller image sizes means faster page loads. This will make your visitors happy, and can increase ad revenue.
1. **Faster backups.** Smaller image sizes also means faster backups.
1. **Less bandwidth usage.** Optimizing your images can save you hundreds of KB per image, which means significantly less bandwidth usage.
1. **Super fast.** Because it runs on your own server, you don’t have to wait for a third party service to receive, process, and return your images. You can optimize hundreds of images in just a few minutes. Png files do take a little longer than jpegs, as the plugin is currently configured to perform 16 optimization trials before selecting the best algorithm.
1. **Root access not needed** Because the paths are configurable via the settings page, and the programs we use have minimal dependencies, you can compile the utilities (if they aren't already installed) and tell the plugin where they are located. 

== Installation ==

1. Upload the 'ewww-image-optimizer' plugin to your '/wp-content/plugins/' directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Ensure jpegtran and optipng are installed on your Linux server (basic installation instructions are below if they are not). You will receive a warning when you activate the plugin if they are not present. This message will go away once you have them installed.
1. *Optional* Visit the settings page to configure paths, and turn on metadata stripping for jpegs (to reduce file size even more).
1. Done!

= Installing optipng =

If you have root access to your server, you can install optipng from the standard repositories. If you are on shared hosting, read on... These steps can/should generally all be done via the command line

1. Download the latest stable version of [optipng](http://optipng.sourceforge.net/) to your home directory
1. Ensure libpng and zlib are installed. If they are not, you're on your own there (but maybe you need a new web host...)
1. Uncompress optipng: *tar xvzf optipng-0.7.1.tar.gz && cd optipng-0.7.1*
1. Configure and compile optipng: *./configure && make*
1. If you have root access, install it with *make install*
1. If necessary, provide the path to the optipng binary on the plugin settings page. It should be something similar to */home/your-username-here/optipng-0.7.1/src/optipng/optipng* if you didn't have root access to run *make install*. You can always move the binary wherever you would like, it doesn't need to stay where it is.

= Installing jpegtran =

If you are on a standard webhost, you should already have this, if you own your own server, or have root access, it is part of the libjpeg-turbo-progs on Debian/Ubuntu, and likely something similar on rpm distros (Fedora, CentOS, RHEL, SuSE).

= Installing gifsicle =

If you have root access to your server, you can install gifsicle from the standard repositories. If you are on shared hosting, read on... These steps can/should generally all be done via the command line

1. Download the latest version of [gifsicle](http://www.lcdf.org/gifsicle/) to your home directory
1. Uncompress gifsicle: *tar xvzf gifsicle-1.67.tar.gz && cd gifsicle-1.67*
1. Configure and compile gifsicle (we disable gifview and gifdiff as they are not needed): *./configure --disable-gifdiff --disable-gifview && make*
1. If you have root access, install it with *make install*
1. If necessary, provide the path to the gifsicle binary on the plugin settings page. It should be something similar to */home/your-username-here/gifsicle-1.67/src/gifsicle* if you didn't have root access to run *make install*. You can always move the binary wherever you would like, it doesn't need to stay where it is.

= Troubleshooting =

Have some problems, and I'll give some pointers here.

== Frequently Asked Questions ==

= Can I use EWWW Image Optimizer with a Windows server? =

No, it doesn't work on Windows. If you do manage to get the tools working on Windows and hack the plugin to remove the OS check and it all works, please let me know, and I'll consider removing the block on Windows.

= How are JPGs optimized? =

Using the command *jpegtran -copy all -optimize -progressive original-file > optimized-file*. Optionally, the -copy switch gets the 'none' parameter if you choose to strip metadata from your JPGs on the options page.

= How are PNGs optimized? =

Using the command *optipng -o3 original-file*. The '-o3' switch tells optipng to perform 16 trials, but if people complain about performance, I may look to streamline that a bit. Optipng is a derivative of pngcrush, which is another widely used png optimization utility.

= How are GIFs optimized? =

Using the command *gifsicle -b -O3 --careful original file*. This is particularly useful for animated GIFs, and can also streamline your color palette. That said, if your GIF is not animated, you should strongly consider converting it to a PNG. PNG files are almost always smaller, they just don't do animations. The following command would do this for you on a Linux system with imagemagick: *convert somefile.gif somefile.png*

= Why not just convert GIFs to PNGs then? =

I tried that first, and decided against it. First, because it is hard work and I have no use for it. Second, because I don't want to automatically destroy your original GIFs. If there is enough interest, I will embark upon a quest to implement an optional conversion process that can replace the original GIF with an optimized PNG. Or maybe just give an option to download an optimized PNG that you can then re-upload.

= I want to know more about image optimization, and why you chose these options/tools. =

That's not a question, but since I made it up, I'll answer it. See the Image Optimization sections for [Yslow - Yahoo](http://developer.yahoo.com/performance/rules.html#opt_images) and [Google PageSpeed](https://developers.google.com/speed/docs/best-practices/payload#CompressImages)

== Screenshots ==

1. Plugin settings page.
2. Additional optimize column added to media listing. You can see your savings, or manually optimize individual images.
3. Bulk optimization page. You can optimize all your images at once. This is very useful for existing blogs that have lots of images.

== Changelog ==

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

= 1.0.6 =
Made jpeg optimization safer (so an empty file doesn't overwrite the originals), and added NextGEN Gallery integration

= 1.0.5 =
Improved optimization for JPGs significantly, by adding -progressive flag. May want to run the bulk operation on all your JPGs (or your whole library)

= 1.0.1 =
Improved performance for PNGs by specifying proper optimization level

== Contact and Credits ==

Written by [Shane Bishop](http://www.shanebishop.net). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it.
