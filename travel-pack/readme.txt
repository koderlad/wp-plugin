=== Travel Pack ===
Contributors: travelpack
Tags: travel, tour, booking, itinerary, destinations
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage travel packages (destinations) with categories, tags, departures, itineraries and a booking form. Designed for non-technical travel agents.

== Description ==

Travel Pack gives a travel agent everything they need to publish a destination and take bookings against it:

* Packages (Everest Base Camp, Annapurna Circuit, etc.) as a custom post type.
* Categories and Tags for organising packages.
* Fixed fields: Duration, Group Size, Price, Terrain, Season, Difficulty.
* Managed dropdown values for Group Size, Season and Difficulty.
* Repeatable Included / Not Included / Important Notes blocks.
* Itinerary items with a day-counter (increase the counter to have one item span multiple days — the frontend then reads "Day 4-6: ...").
* Departure dates with per-departure seat capacity. Seats decrement automatically when a customer submits the built-in booking form.
* A polished frontend template for the single package page with a sticky booking form.

== Installation ==

1. Upload the `travel-pack` folder to `/wp-content/plugins/`.
2. Activate through the "Plugins" menu in WordPress.
3. Go to *Travel Pack → Dropdown Settings* to set your Group Size / Season / Difficulty options.
4. Go to *Travel Pack → Add New* to create your first package.

== Frequently Asked Questions ==

= Can I use my own theme for the single package page? =

Yes. Copy `templates/single-travel_package.php` into your theme's root as `single-travel_package.php` and customise as you wish. If your theme has one, it wins over the plugin default.

= Where do bookings go? =

They're stored in the `wp_travel_pack_bookings` table and listed under *Travel Pack → Bookings*.

== Changelog ==

= 1.0.0 =
* Initial release.
