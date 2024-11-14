=== Send PDF for Contact Form 7 ===
Contributors: Florent73
Donate link: https://www.paypal.me/RestezConnectes/
Tags: contact form 7, pdf, send, attachment, form
Requires at least: 5.2
Tested up to: 6.7
Stable tag: 1.0.2.8
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create, customize and send PDF attachments with Contact Form 7 form

== Description ==

This plugin adds conditional logic to <a href="https://wordpress.org/plugins/contact-form-7/">[Contact Form 7]</a>.

Send the PDF for Contact Form plugin will allow you to recover the data yourself via your form to insert them into a PDF built and prepared by you.

This plugin requires the installation and activation of the plugin Contact Form 7.

<a href="https://demo.restezconnectes.fr/send-pdf-for-contact-form-7/">DEMO HERE</a>
<a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/">[Tutorial Here]</a>

Translations: <a href="https://translate.wordpress.org/projects/wp-plugins/send-pdf-for-contact-form-7/">Here</a>

== Installation ==

1. Upload the full directory into your '/wp-content/plugins' directory
2. Activate the plugin at the plugin administration page
3. Open the plugin configuration page, which is located under 'Contact->Send PDF with CF7'

== Screenshots ==

1. Choice of form Contact Form 7
2. View general settings
3. Preparation view of your PDF

== Contribute ==
You can contribute with code, issues and ideas at the [GitHub repository](https://github.com/Florent73/send-pdf-for-contact-form-7).

If you like it, a review is appreciated :)

== Frequently Asked Questions ==

= Is there a tutorial? =

Read here <a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/">Tutorial available (in french).</a>

= This plugin is free? =

Yes. If you want, you can support this project here: <a href="https://restezconnectes.fr/a-propos/">https://restezconnectes.fr/a-propos/</a>


== Changelog ==

= 1.0.2.8 = 
* Adding option for multi PDF
* Adding conditional possibility

= 1.0.2.7 = 
* Adding option for displaying textarea fields
* Change cols value at 100% for textarea fields
* Adding Fields Matching for CSV
* Fix bug CSV encoding
* Personalize Fields for CSV file

= 1.0.2.6 = 
* Fix bug form filled
* Fix bug preview images in PDF

= 1.0.2.5 = 
* Fix bug open PDF after sending email

= 1.0.2.4 = 
* Fix bug with line break on PDF
* Secure plugin

= 1.0.2.3 = 
* Fix bug format textarea CSV
* Secure plugin

= 1.0.2.2 = 
* Fix bug format text

= 1.0.2.1 = 
* Fix bug for shortcode prices
* Delete HTML for tags 'select'

= 1.0.2.0 = 
* Modify generate ID length for reference

= 1.0.1.9 = 
* Fix bug redirect PDF
* Fix bug footer PDF
* Adding Custom Fonts is now ready
* Adding shortcode for prices

= 1.0.1.8 = 
* Fix bug with line break on PDF

= 1.0.1.7 = 
* Fix bug and defined constants WPCF7_ADMIN_READ_CAPABILITY / WPCF7_ADMIN_READ_WRITE_CAPABILITY
* Fix bug redirection page after sending form
* Fix bug "Desactivate line break auto" option reversed
* Adding a option for desactivated line break for mail content

= 1.0.1.6 =
* Fix bug headers with or without [addpage] shortcode

= 1.0.1.5 =
* Adding tag compatible [_format_{field name} "{date format}"]

= 1.0.1.4 =
* Bug fixed generate CSV

= 1.0.1.3 =
* Bug fixed tags name
* Remove TRANSIENTS for reference of PDF
* Adding form fields for reference and name of PDF
* Adding checking for fields settings in admin page

= 1.0.1.2 =
* Bug fixed if option "Delete all PDF" checked
* Bug fixed for download button shortcode [wpcf7pdf_download]

= 1.0.1.1 =
* Bug Fixed generate PDF
* Bug Fixed redirect page or PDF

= 1.0.1.0 = 
* Major version : reprogramming plugin
* Bug Fixed form image upload

= 1.0.0.3 =
* Bug Fixed form image upload

= 1.0.0.2 =
* Bug Fixed form tags in name PDF

= 1.0.0.1 =
* Bug Fixed upload image in mail

= 1.0.0.0 =
* Bug Fixed tags for name PDF
* Cleaning code
* Bug fixed supports channels pipes
* Compatibility with Shortcoder plugin
* Bug with images fixed
* Adding Directionality option

= 0.9.9.7 =
* Bug Fixed tags for name PDF
* Cleaning code
* Adding Cyrillic Time New roman Bulgarian font
* Autorized form tags in name of PDF

= 0.9.9.6 =
* Change name of generated PDF's
* Adding Directionality option
* Adding option for hidden the empty value for checkbox and radio  

= 0.9.9.5 =
* Adding IPA (Japanese) fonts

= 0.9.9.4 =
* Autorize text-rotate tags in text editor
* Bug Fixed Image rotate
* Change name of PDF sent

= 0.9.9.3 =
* Adding apply_filters() to use a query for PDF design
* Adding Lato font

= 0.9.9.2 =
* Vulnerability fixed 
* Autorize ID tag in text editor
* Adding font

= 0.9.9.1 =
* Autorize ID tag in text editor
* Bug Fonts Fixed
* Bug checkbox fixed

= 0.9.9 =
* Adding deleted all settings form option
* Some Bug fixed
* Barcode & QrCode are now compatible
* Fixed bug import / export settings

= 0.9.8 =
* Add the u modifier to make UTF-8 work

= 0.9.7 =
* Autorize tags in text editor

= 0.9.6 =
* Bug fixed Checkbox
* Autorize colspans and rowspans in text editor
* Bug fixed date in PDF name

= 0.9.5 =
* Bug fixed CSV
* Remove usage SESSIONS, use TRANSIENT instead

= 0.9.4 =
* Bug fixed Conditional Fields PRO

= 0.9.3 =
* Bug Safari open PDF fixed
* Cleaner code, adding <thead>, <th>, <tbody> tags
* Adding Font Awesome and Dashicons
* Bug HTML footer fixed
* Adding tag [avatar] for user avatar URL

= 0.9.2 =
* Several security patches have been brought

= 0.9.1 =
* Adding Choice of separator for checkboxes or radio buttons
* Compatibility with Conditional Fields PRO

= 0.9 =
* Bug fixed upload image
* Bug fixed footer if is empty

= 0.8.9 =
* Bug fixed PDF password

= 0.8.8 =
* Adding Margin Left & Right

= 0.8.7 =
* Adding fields for customs shortcodes
* Adding ID tag from database
* Bug fixed upload logo and background


= 0.8.6 =
* Adding change separator for CSV option
* Adding fields for margin auto top & bottom
* Adding reset TMP folder
* Upgrade mPDF v8.0.10
* Adding filed for password with a TAG
* Bug with SESSION password

= 0.8.5 =
* Bug fixed with uploading files field

= 0.8.4 =
* Bug fixed with the 'wpcf7pdf_path_temp' option, you can remove this option by disabling plugin
* Adding disabling function for plugin : removed all options (not parameters)
* Remove COOKIE and use SESSION

= 0.8.3 =
* Bug fixed COOKIES

= 0.8.2 =
* Display Error Message if COOKIES are blocked
* Bug fixed margin top & Header :
    - Adding "Margin Bottom Header" setting for header picture
    - Adding "Global Margin PDF" setting for PDF
* Bug fixed Multiple Checkbox
* Bug Fixed Radio checked

= 0.8.1 =
* Use capability of CF7
* Fixed typing error Donwload

= 0.8 =
* Bug fixed COOKIE
* Update mPDF Library

= 0.7.9.6 =
* Fixed bugs Header & Background Image
* Make the text filterable

= 0.7.9.6 =
* Fixed bugs codeEditor

= 0.7.9.5 =
* Adding Custom CSS
* Adding Background Image

= 0.7.9.4 =
* Fixed bug TMP directory
* Adding tags form in PDF name
* Fixed bugs minors

= 0.7.9.3 =
* Remove SESSION and use COOKIE

= 0.7.9.2 =
* Fixed bugs session

= 0.7.9.1 =
* Fixed bugs notice PHP

= 0.7.9 =
* Resolve bug delete record

= 0.7.8 =
* Change CodeMirror
* Add a CSS example for A4 page

= 0.7.7 =
* Fixed bugs notice PHP
* Adding PDF password length
* Tags [reference], [pdf-password], [date] and [time] is possible in the subject email
* Adding URL input for your stylesheet

= 0.7.6 =
* Adding choosing font for your PDF file

= 0.7.5 =
* Fixed bug checkbox
* Fixed bug CSV