=== Send PDF for Contact Form 7 ===
Contributors: Florent73
Donate link: https://www.paypal.me/RestezConnectes/
Tags: WordPress, plugin, contact form, pdf, send, attachment, form, cf7
Requires at least: 3.0
Tested up to: 5.3
Stable tag: 0.7.8
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

== Frequently Asked Questions ==

= Is there a tutorial? =

Read here <a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/">Tutorial available (in french).</a>

= This plugin is free? =

Yes. If you want, you can support this project here: <a href="https://restezconnectes.fr/a-propos/">https://restezconnectes.fr/a-propos/</a>

= Can I change the plugin code? =

Yes. Thank you for submitting your changes to update the plugin.


== Changelog ==

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

= 0.7.4 =
* Add list of PDF for admin download

= 0.7.3 =
* Add send ZIP option
* Add Header page option

= 0.7.2 =
* Fixed bug PDF protection

= 0.7.1 =
* Fixed bug uninstall plugin

= 0.7 =
* Add open PDF in a popup windows option
* Fixed bug picture view in PDF file if not picture uploaded
* Fixed bug for open PDF after send form
* Fixed bug open PDF in a new window
* Ready for PHP 7.3

= 0.6.9 =
* Add message for download button if "Insert subscribtion in database" option is disabled!
* Fixed bugs Warning PHP
* Fixed bug picture view in PDF file

= 0.6.8 =
* Fixed error notice PHP

= 0.6.7 =
* The PDF file is now in fillable mode
* The PDF file recognize now the checkbox and radio fields

= 0.6.6 =
* Bug on ption "Delete all files into this uploads folder" fixed

= 0.6.5 =
* Set event listener instead of using soon to be removed onSentOk
* Compatible with signature add-on plugin
* Compatible with multi-step module plugin


= 0.6.4 =

* Add URL for PDF files in CSV file
* Secure PDF folder
* Add PDF Footer option

= 0.6.3 =

* Fixed Bug password to protect your PDF file
* Add a new option: open directly your PDF after sending form

= 0.6.2 =

* Change for a hard password to protect your PDF file
* Fixed Bug button insert data table

= 0.6.1 =

* Update mPDF lib to 6.1 version
* Add password to protect your PDF file
* Fixed some minor bugs

= 0.6.0 =

* Bug fixed on desactivate/uninstall plugin
* Added export/import settings
* Added [time] shorcode to display the time
* Added substitution image for better PDF rendering
* Added option to desactivate line break auto

= 0.5.9 =
* Adding Page size & Orientation
* Changing admin page
* Resolved bug with form without PDF

= 0.5.8 =
* Adding [addpage] tag to force a page break anywhere in your PDF
* Adding [reference] and [date] tags for name of your PDF

= 0.5.7 =
* Adding CodeMirror scripts for editing PDF

= 0.5.6 =
* Adding a option : convert [file] tags to image for your PDF

= 0.5.5 =
* Adding a option, delete all files after sent email
* Ready for PHP7

= 0.5.4 =
* Fix bug for use mail-tags

= 0.5.3 =
* Fix bug for other attachements

= 0.5.2 =
* Adding a new option, generate PDF in a another distinct folder

= 0.5.1 =
* Adding a new option, send mail without attachements but return a link for downlod PDF

= 0.5 =
* Reprogramming, avaible for WordPress 4.6 version

= 0.4.4 =
* Adding a option, using form tags for complete the name of PDF

= 0.4.3 =
* Bug fixed upload picture and send pdf

= 0.4.2 =
* Bug fixed include class mpdf

= 0.4.1 =
* Add custom date/time

= 0.4 =
* Multi-languages bug fixed

= 0.3 =
* Media upload bug fixed

= 0.2 =
* Bug fixed after save

= 0.1 =
* First version