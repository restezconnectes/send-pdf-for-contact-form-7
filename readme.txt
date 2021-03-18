=== Send PDF for Contact Form 7 ===
Contributors: Florent73
Donate link: https://www.paypal.me/RestezConnectes/
Tags: WordPress, plugin, contact form, pdf, send, attachment, form, cf7
Requires at least: 3.0
Tested up to: 5.7
Stable tag: 0.8.6
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create, customize and send PDF attachments with Contact Form 7 form

== Description ==

This plugin adds conditional logic to <a href="https://wordpress.org/plugins/contact-form-7/">[Contact Form 7]</a>.

Send the PDF for Contact Form plugin will allow you to recover the data yourself via your form to insert them into a PDF built and prepared by you.

This plugin requires the installation and activation of the plugin Contact Form 7.

File send-pdf-for-contact-form-7.pot available

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

= 0.8.6 =
* Adding change separator for CSV option
* Adding fields for margin auto top & bottom
* Adding reset TMP folder

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