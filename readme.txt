=== UtilitySign ===
Requires at least: 5.9
Tested up to: 6.6
Stable tag: 1.0.5-rc1
Requires PHP: 7.2
License: GPLv2 or later

== Description ==

WordPress plugin for document signing workflows with BankID integration. Provides public-facing order forms and embeddable components for UtilitySign customers.

== Changelog ==

= 1.0.4 =
* Fixed billing address (Fakturaaddresse) feature - billing fields now correctly flow from form to PDF generation
* Added billingAddress, billingCity, billingZip to API client TypeScript interface
* Fixed request body construction to include billing fields when provided

= 1.0.3 =
* Initial production release with BankID integration
* Form submission and signing workflow support
* Product and supplier ID forwarding
