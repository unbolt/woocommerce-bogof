# WooCommerce BOGOF

## Provided Requirements

Wordpress and WooCommerce Add-on that is installed and activated conventionally from
WP’s dashboard.

This plugin needs to create a Buy-One-Get-One-Free coupon, where the cheapest product
is discounted automatically, with an option for adding a percentage discount instead of the
second product being free.

Bonus Points:

This has to add a code reference to our export system (the reference to the coupon needs
to be stored in the database).

Warning:

Ensure that the bundles (refer to our site) are not taken into account when the coupon is
applied at the checkout.

(Note: It has been clarified that bundles are not bundles as such but individual products)


## Basic Plan

Define fields required
Create settings for the BOGOF promotion
Add functions to WC hooks to automatically create and apply a coupon code as required


## Future Expansion Plans

- Split functions in separate classes (this file got borderline too big)
- Change to using a custom post type to allow for adding multiple BOGOF offers
- Use WP-CLI to add some unit tests for the plugin
- Add proper build process for versioning
- Add valid from and to dates to a BOGOF
- Add option to include and exclude products by tag/category instead of specific products
- Add proper multilanguage support
