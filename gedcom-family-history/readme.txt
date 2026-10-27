=== GEDCOM Family History ===
Contributors: neilthompson
Tags: genealogy, gedcom, family-history
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import a GEDCOM, choose a starting person, and explore linked profiles and surname indexes.

== Installation ==
1. Upload gedcom-family-history.zip through Plugins > Add New > Upload Plugin.
2. Activate GEDCOM Family History.
3. Open Family History in the WordPress admin menu.
4. Select a UTF-8 .ged file and click Save family history.
5. Choose the starting person from the imported names and save again.
6. Follow View Family History. Add that page to your site's navigation if desired.

The first successful import creates a published Family History page containing
[gedcom_family_history]. Edit the page title or introduction normally, keeping
the shortcode. Each person has a bookmarkable URL on that page.

== Features ==
* Birth, christening, baptism, death, burial, cremation, occupation, education,
  residence, nationality, custom events/facts and inline notes when recorded.
* Three-generation linked ancestry: selected person, parents, grandparents.
* Linked partners and children, marriage and divorce events.
* Alphabetical names and surname indexes, with surname counts and filtering.
* Semantic headings, list-based trees, keyboard links and responsive styling.
* GEDCOM 5.5.1 / 7 common INDI and FAM records, UTF-8 encoding, CONT and CONC text.

== Privacy ==
Names and relationships are public, including living people. All visitors,
including logged-in administrators, see the same privacy-filtered profiles.
A person is treated as living unless DEAT or BURI has a Y flag, date or place.
An empty DEAT or a DEAT N is insufficient. No age-based death assumptions are made.
All dates, places, notes and personal facts are hidden for living/unknown people.
Marriage/divorce details are hidden when any partner is living/unknown or missing.

Free-text notes and facts on deceased people may mention living relatives: review
your GEDCOM before publishing. The plugin does not understand identities within
prose. It cannot remove information already shared in a previous import or cached
by other software. Purge site/CDN caches after imports or privacy-related changes.

Uploads are read from PHP's temporary upload file, never placed in the Media
Library or public uploads directory. Parsed data is stored as a non-autoloaded
WordPress option, with no public REST endpoint. Database backups contain the
complete imported information and should be protected accordingly.

== Refreshing the tree ==
Upload a new GEDCOM on the same admin screen. Parsing and validation finish before
the existing dataset is replaced. Invalid files leave the current tree intact.
The replacement removes people absent from the new file. Existing root selection
is retained if that identifier still exists; otherwise the first person is used.
Select a new starting person after import if required. Person links stay stable
when your genealogy software preserves GEDCOM identifiers across exports.
If identifiers change or are reused, old bookmarks may stop resolving or refer
to a different person. No matching by name is attempted.

== Limits ==
Maximum 10 MB, 250,000 nonblank GEDCOM lines, 30 nesting levels. Host upload,
POST, memory and execution limits also apply. A single validated dataset is
stored in the database; very large trees need a different storage strategy.
Non-UTF-8 exports should be re-exported as UTF-8 by your genealogy application.
Media files, shared NOTE records, GEDZIP, advanced GEDCOM 7
extensions, calendars and automatic place geocoding are not supported. Dates are displayed
as supplied, preserving qualifiers such as ABT and BEF.

Deactivation retains data. Uninstall removes the imported dataset; the Family
History page remains so you can remove it or reuse its content yourself.

== Timelines and sources ==
Version 1.1.0 adds cream, green and gold archive styling and replaces the flat
personal-details list with a chronological timeline below the family sections.
Events without dates are excluded. Recorded dates that cannot be sorted appear at the end. Original date
text, including qualifiers, is preserved. Qualified dates are sorted by their
first stated date, not treated as exact dates. Ages require exact Gregorian
birth and event dates; qualified, partial and unsupported calendar dates do not
produce ages. Ages over 120 and ages for posthumous event types are omitted.

Expandable source citations appear beneath the facts they support. Shared SOUR
records provide title, author and publication; citation PAGE, DATA/TEXT and QUAY
provide reference, transcript and the original recorded evidence assessment.
This assessment is imported data, not a verification by the plugin. Profile-level
citations appear separately. Missing source records are labelled. Source prose
can mention other relatives; review it before publishing, as with personal notes.

After upgrading from 1.0.x, upload the same GEDCOM again to load source records.
Previously imported facts and relationships continue working without re-import.
Root and page are retained when their identifiers remain valid. Purge any site
or CDN caches after updating and importing. No media upload is needed for these
features. Living profiles have no timeline or sources, and marriages with a
living/unknown partner are excluded from timelines and source disclosures.

== Search and relatives ==
Search given names and surnames from any family page. Multiple words match in
any order, case-insensitively; results contain names and profile links only.
Parent roles come from family HUSB/WIFE links. Explicit FAMC/PEDI birth, adopted
and foster types and ADOP/FAMC adoption qualifiers label parent relationships.
An unqualified parent is not automatically described as biological.
Siblings are derived from shared family/parent links. Half-siblings require two
recorded parents on each side and exactly one shared parent. Incomplete parent
sets are labelled as sharing a recorded parent; adoptive/foster families are
listed separately. Relationships reflect the source export, not verification.
Existing imported data already retains these links: no re-import is needed.

== Profile maps ==
Version 1.3.0 adds Places in this life beneath deceased-person timelines.
Only public events are mapped. Marriages/divorces involving a living or unknown
partner are excluded. Undated public events may appear on the map even though
undated events remain excluded from the timeline. Events at the same coordinates
share a marker. The accessible event list works without loading the map.

Open Family History > Places to manage locations. Filter by place name and save
latitude/longitude in decimal degrees, marking town/parish approximations or
verified building/site locations. West/south coordinates use a minus sign.
Each place is shared across profiles; addresses are not used as lookup keys, so
different street addresses in one town share that town's saved coordinate.
Use building precision only when all events using that place truly share a site.
Use GEDCOM event-specific coordinates for distinct sites within the same town.

PLAC/MAP/LATI/LONG coordinates are used automatically, including N/S/E/W decimal
values. A manually saved place overrides embedded coordinates. Clear both fields
and save to remove the override. No place is automatically guessed. Unlocated
places are listed for review. Saved coordinates survive GEDCOM replacements;
unused entries remain private and can be reused if a place returns later.
Changing a place's spelling may require saving it again. Existing imports retain
PLAC/MAP data, so no re-import is required for maps. Removing the plugin deletes
saved coordinates as well as the dataset.

Leaflet 1.9.4 is bundled locally under its BSD-2-Clause license (see
assets/leaflet/LICENSE). No API key is required. Map tiles are requested from
https://tile.openstreetmap.org only after a visitor selects Load map. The
provider receives normal browser request information, including IP address,
referrer subject to browser/site settings, and requested tile areas. Names,
event dates, notes and source texts are not sent by the plugin to a geocoder.
The admin Find on OpenStreetMap link submits that place name when followed.

Attribution remains visible on the map. OpenStreetMap tile service is best-effort
and subject to https://operations.osmfoundation.org/policies/tiles/. Do not block
the Referer header, disable browser caching, or bulk-download tiles. If your site
has substantial traffic, arrange suitable tile hosting before using the service
at scale. Mention the external map service in your site's privacy information.
If map tiles are unavailable, the local text list remains available.

== Statistics ==
Follow Statistics in the family navigation for people/family totals, conservative
living/deceased counts, common surnames and places, event totals, birth centuries
and exact-date lifespan summaries. For a separate WordPress page, add
[gedcom_family_statistics] to its content. No new import is required.

Names and relationship totals include everyone, as on the indexes. Birth/death
years, places and events exclude living/unknown people. Marriage/divorce events
require both partners to be known deceased and are counted once per family.
Counts describe records, including undated facts and alternative birth records.
Qualified dates contribute their first recognized year. Exact lifespans require
one birth and one death record, valid full Gregorian dates and ages 0–120;
ambiguous alternatives and approximate dates do not contribute to the average.
Data corrections and replacement imports are reflected automatically.

== Changelog ==
= 1.4.2 =
Balance toolbar top/bottom spacing and match search button and input heights.

= 1.4.1 =
Separate navigation and search into a compact toolbar card above content with
an eight-pixel gap, consistently across profiles, indexes and statistics.

= 1.4.0 =
Add Statistics navigation, standalone statistics shortcode, overview cards,
surname/place/event tables, birth-century counts and privacy-filtered lifespans.

= 1.3.1 =
Include venue/address lines and occupation details in map popups and event lists;
show occupation descriptions consistently in timelines, including TYPE fallback.

= 1.3.0 =
Add privacy-filtered profile maps, grouped event pins, GEDCOM coordinates and a
paginated admin place registry with reusable manual coordinate overrides.

= 1.2.1 =
Style sibling groups as family boxes and rename Recorded family to Parents.

= 1.2.0 =
Add name search, labelled parents, siblings and half-siblings, with adoptive and
foster family labels where explicitly recorded.

= 1.1.3 =
Sort day/month/year dates alongside GEDCOM textual dates; tolerate mixed case
and whitespace, validate full dates, and add spaces after place commas.

= 1.1.2 =
Show event addresses above places and remove empty comma-separated place components.

= 1.1.1 =
Exclude events without dates from the life timeline.

= 1.1.0 =
Heritage styling, chronological life timelines, exact-date ages and expandable
source citations with living-person privacy. Re-import to load shared sources.

= 1.0.4 =
Add Neil Thompson author credit and website links to plugin metadata.

= 1.0.3 =
Show the family tree and partners/children before personal details.

= 1.0.2 =
Bordered pedigree chart with connected grandparents, parents, selected person, partners and children.

= 1.0.1 =
Allow reused identifiers in ignored proprietary records while keeping person/family identifiers unambiguous.

= 1.0.1 =
Initial release.
