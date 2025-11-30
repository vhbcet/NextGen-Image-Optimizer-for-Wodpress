=== Hedef Image Optimizer — WebP & AVIF ===
Contributors: hedefhosting
Donate link: https://hedefhosting.com.tr/
Tags: image optimizer, webp, avif, performance, media
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert JPEG and PNG images to modern WebP and AVIF formats on upload or in bulk, and optionally serve them using <picture> tags – with everything processed locally on your own server.

== Description ==

Hedef Image Optimizer — WebP & AVIF is a lightweight but powerful image optimization plugin that converts your JPEG and PNG uploads into modern WebP and AVIF formats.

All conversions happen locally on your server using GD and/or Imagick – there is no external API, no remote storage and no extra monthly bills.

The plugin is developed and maintained by [Hedef Hosting](https://hedefhosting.com.tr/) and is provided completely free of charge.  
If you have ideas, bug reports or feature requests, feel free to email us at support@hedefhosting.com.tr.

It focuses on three main goals:

* **Better performance** – Serve lighter images without visibly losing quality.
* **Modern formats** – Use WebP and AVIF when supported by your server and your visitors’ browsers.
* **Simple control** – Configure everything from a single, polished settings page.

=== Key Features ===

* Automatically convert JPEG/JPG and PNG images to WebP and/or AVIF on upload.
* Bulk optimization tool for existing Media Library images, with:
  * Progress bar and live log of processed items.
  * “Run again” support to refresh images after changing quality or resize settings.
* Media Library “Optimizer” column:
  * See per-image status, new filesize, and percentage of space saved.
  * One-click Re-optimize and Restore original actions (if you keep originals).
* Per-image controls in the attachment edit screen:
  * Optimize/Re-optimize just that image.
  * Override the global “Optimize on upload” setting.
* Server capabilities checker for GD / Imagick WebP and AVIF support.
* Optional frontend integration using <picture> tags with WebP/AVIF <source> elements:
  * Modern browsers get AVIF/WebP.
  * Older browsers fall back to the original image.
* Adjustable compression quality (0–100) with recommended ranges.
* Optional resize of next-gen copies above a configurable max width (e.g. 2048px).
* Optional EXIF/IPTC metadata stripping from next-gen copies to further reduce filesize.
* Exclusion rules:
  * Skip specific images based on filename or path (e.g. logo, /icons/, avatar-).
* Works with:
  * WordPress Media Library
  * wp_get_attachment_image()
  * Featured images and most themes/page builders.
* Localization:
  * English (en_US)
  * Turkish (tr_TR) – full translation of the admin UI and bulk screens.

Everything is designed to blend nicely into the WordPress admin with a clean, modern UI.

== How It Works ==

1. When you upload a JPEG or PNG image, the plugin can automatically generate .webp and/or .avif versions (depending on your settings and server capabilities).
2. For existing images, use the **Media → Bulk Optimize (NGIO)** screen to scan the Media Library and generate missing next-gen versions in small batches.
3. On the frontend, you can enable the <picture> integration so that supported browsers will load AVIF / WebP, while older browsers still get the original image.
4. The plugin stores lightweight stats in attachment metadata (ngio) so you can see how much space you’ve saved on each image and across the library.

== Requirements ==

* PHP 8.1 or higher (for AVIF support through GD or Imagick).
* WordPress 6.5 or higher.
* One of:
  * PHP GD extension compiled with WebP/AVIF support.
  * PHP Imagick extension with WEBP/AVIF codecs enabled.

If your server is missing some of these, the **Server support** box on the settings page will clearly show what’s available and what is not.

== Installation ==

1. Upload the `hedef-image-optimizer` folder to the `/wp-content/plugins/` directory,  
   or install it via the WordPress.org plugin repository (when available).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → Image Optimizer** to configure:
   * Which formats to generate (WebP, AVIF, or both).
   * Quality level and optional resizing.
   * Whether to optimize images automatically on upload.
   * Whether to serve images via <picture> on the frontend.
   * Optional exclusion patterns.
4. (Optional) Go to **Media → Bulk Optimize (NGIO)** to convert existing images.

== Usage ==

=== 1. Choose formats and automation ===

On the **Settings → Image Optimizer** page:

* Enable WebP, AVIF, or both, depending on your server capabilities.
* Turn on **Optimize on upload** if you want new images to be processed automatically.
* Enable **Serve via <picture>** to send WebP/AVIF to compatible browsers.

You can also:

* Pick a quality level (e.g. 80–85 for most sites).
* Enable **Resize next-gen copies** and set a max width (e.g. 2048px).
* Enable **Strip metadata** to remove EXIF/IPTC from generated copies.
* Add **Exclusion patterns** (one per line) to skip specific images.

=== 2. Bulk optimization ===

Go to **Media → Bulk Optimize (NGIO)**:

* Click **Optimize all images** to start the process.
* The progress bar will move as images are processed.
* The log will list each optimized attachment.
* If you change quality or resize settings later, you can run bulk optimization again – existing next-gen copies will be refreshed.

=== 3. Per-image controls ===

In **Media → Library** (list view):

* Use the **Optimizer** column to see:
  * Status (optimized/not optimized).
  * New filesize and percentage of space saved.
  * Links to Re-optimize and Restore original.

In the **attachment edit screen**:

* Use the **NextGen optimization** metabox to:
  * Optimize/re-optimize just that image.
  * Override the global “Optimize on upload” behaviour.

== Frequently Asked Questions ==

= Does this plugin modify my original image files? =

By default the plugin keeps your original JPEG/PNG files untouched and creates additional .webp and .avif versions in the same upload folder.  
If you enable “backup originals” in future versions, restoring will become even easier.

= Will it work if my server does not support WebP or AVIF? =

If your server cannot generate WebP and/or AVIF, the plugin will show this in the **Server support** section on the settings page.  
In that case:

* Only the supported formats will be generated, or  
* If neither WebP nor AVIF is available, conversion is skipped while your originals remain untouched.

= How does the <picture> option affect my theme? =

When enabled, the plugin wraps images output by `wp_get_attachment_image()` and featured images in a <picture> tag, adding <source> elements for WebP and AVIF.  
The original <img> tag remains inside, so themes usually continue to work as expected. If a browser doesn’t support WebP/AVIF, it simply loads the original image.

= Can I disable optimization for specific images? =

Yes. You can:

* Use **exclusion patterns** (e.g. `logo`, `/icons/`, `avatar-`) so matching files are never converted, or
* Override the global auto-optimize setting for individual images from their attachment edit screen.

= Can I remove the generated files if I uninstall the plugin? =

By default, uninstalling the plugin removes only its settings. The generated image files remain in the uploads directory.  
This is intentional to avoid breaking existing content. You can remove them manually (or via a dedicated cleanup tool) if needed.

= Does the plugin send any data to external services? =

No. All conversions happen locally on your server. The plugin does not send your images to any external service or CDN.

== Screenshots ==

1. Settings page – main configuration, conversion formats, automation, quality and server support.  
2. Bulk optimization screen – progress bar, global overview donut and per-image log.  
3. Media Library Optimizer column – per-image status, savings, and quick actions.

== Changelog ==

= 0.1.0 =

* Initial public release.
* Automatic WebP/AVIF conversion on upload (when supported).
* Bulk optimization screen under **Media → Bulk Optimize (NGIO)**.
* Server support checker for GD / Imagick WebP and AVIF capabilities.
* Optional <picture> / srcset integration on the frontend.
* Quality, resize and metadata stripping controls.
* Exclusion patterns for filenames/paths.
* Media Library “Optimizer” column with per-image stats and actions.
* Per-attachment metabox for manual optimization.
* English and Turkish translations.
* Modern, polished admin UI with donut chart and progress bar.

== License ==

Hedef Image Optimizer — WebP & AVIF is free software released under the **GNU General Public License v2.0 or later**.  
You can redistribute it and/or modify it under the terms of the GPL as published by the Free Software Foundation.  
See https://www.gnu.org/licenses/gpl-2.0.html for the full license text.
