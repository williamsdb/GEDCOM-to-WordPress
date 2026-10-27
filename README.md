<a name="readme-top"></a>

<!-- PROJECT TITLE -->
<br />
<div align="center">

<h3 align="center">GEDCOM Family History</h3>

  <p align="center">
    Turn a GEDCOM export into a searchable family history on WordPress.
    <br />
    Linked profiles, family trees, life timelines, maps and statistics.
  </p>
</div>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
      <ul>
        <li><a href="#built-with">Built With</a></li>
      </ul>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#privacy">Privacy</a></li>
    <li><a href="#known-issues">Known Issues</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#license">License</a></li>
    <li><a href="#contact">Contact</a></li>
    <li><a href="#acknowledgments">Acknowledgments</a></li>
  </ol>
</details>

<!-- ABOUT THE PROJECT -->

## About The Project

GEDCOM Family History is a WordPress plugin for publishing a family history from a GEDCOM file. Upload an export from your genealogy software, select a starting person, and explore the family through linked profiles and trees.

The plugin includes:

- A three-generation ancestor tree, with partners and children beneath it.
- Labelled parents, siblings and half-siblings, including explicitly recorded adoptive and foster relationships.
- Searchable name and surname indexes.
- Chronological life timelines, occupation descriptions and expandable source citations.
- Addresses and venue names displayed above cleaned-up place names.
- Individual profile maps with grouped event markers and reusable place coordinates.
- Family statistics, including common surnames, places, recorded events and lifespan summaries.
- A separate navigation and search box, with responsive archive-style presentation.
- Privacy filtering for living people and people whose status is unknown.
- Replacement GEDCOM imports to refresh the family history.

![](https://thompsonhistory.co.uk/wp-content/uploads/2026/10/SCR-20261027-kxzc-scaled.png)

<a href='https://ko-fi.com/Y8Y0POEES' target='_blank'><img height='36' style='border:0px;height:36px;' src='https://storage.ko-fi.com/cdn/kofi5.png?v=6' border='0' alt='Buy Me a Coffee at ko-fi.com' /></a>

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Built With

- [WordPress](https://wordpress.org/)
- [PHP](https://www.php.net/)
- JavaScript and CSS
- [Leaflet](https://leafletjs.com/) 1.9.4, bundled with the plugin
- [OpenStreetMap](https://www.openstreetmap.org/copyright) map tiles, loaded when a visitor requests a map

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- GETTING STARTED -->

## Getting Started

The installable plugin is available as [gedcom-family-history.zip](gedcom-family-history.zip). No Composer or JavaScript build step is required.

### Prerequisites

1. WordPress 6.4 or later.
2. PHP 7.4 or later.
3. A UTF-8 GEDCOM `.ged` export using common GEDCOM 5.5.1 or 7 records.
4. A WordPress account with permission to manage plugins and site settings.

The importer accepts files up to **10 MB**, with a maximum of **250,000 nonblank lines** and **30 nesting levels**. Your host's upload, memory and execution limits also apply.

### Installation

1. Download [gedcom-family-history.zip](gedcom-family-history.zip).
2. In WordPress, open **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP, install it and activate **GEDCOM Family History**.
4. Open **Family History** in the admin menu.
5. Select your GEDCOM file and click **Save family history**.
6. Choose the starting person from the imported names and save again.
7. Follow **View Family History** to open the published page.

The first successful import creates a published **Family History** page. Add it to your site's navigation if desired.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- USAGE -->

## Usage

### Family history page

The generated page contains this shortcode:

```text
[gedcom_family_history]
```

You can change the page title and add introductory text while keeping the shortcode. Each person has a bookmarkable profile URL. Use the navigation box to search names or open the surname indexes and statistics.

### Life timelines and sources

Timelines show dated events for deceased people, preserving the original date text and qualifiers such as `ABT` and `BEF`. Both `dd/mm/yyyy` (day/month/year) and `dd mmm yyyy` dates are supported alongside partial GEDCOM dates. Events without dates are excluded from the timeline.

Ages are shown only where exact birth and event dates allow them to be calculated. Source references expand beneath the facts they support, including available titles, authors, publications, reference details and transcripts.

If upgrading from a version before **1.1.0**, upload your GEDCOM again once to load shared source records.

### Maps and places

Maps appear beneath deceased people's timelines. GEDCOM coordinates are used automatically where present.

For unresolved locations, open **Family History → Places**. Find a place, save its latitude and longitude, and mark the position as a town/parish approximation or a verified building/site. Saved coordinates are shared across profiles and retained when the GEDCOM is replaced.

Events at the same coordinates share a marker. Pop-ups and the text list include event descriptions, dates, venue/address details and place names. There is no automatic geocoding: uncertain places need manual review.

Visitors select **Load map** before their browser requests OpenStreetMap tiles. Map use is subject to the [OpenStreetMap tile usage policy](https://operations.osmfoundation.org/policies/tiles/). See the [plugin readme](gedcom-family-history/readme.txt) for external-service and privacy details.

### Statistics

Select **Statistics** in the family navigation, or create a separate WordPress page containing:

```text
[gedcom_family_statistics]
```

Statistics include people and family totals, common surnames and places, event counts, births by century, and exact-date lifespan summaries. Dates, places and life-event statistics exclude living people and people whose status is unknown.

### Refreshing the GEDCOM

Upload a replacement file through **Family History**. The importer validates it before replacing the existing dataset; invalid files leave the current tree intact.

The starting person is retained when their identifier still exists. Otherwise, the first person in the replacement file becomes the starting person. Select a different person after importing if needed.

Profile links remain stable when your genealogy software preserves identifiers. Changed or reused identifiers can affect existing bookmarks. Saved map coordinates remain available. Purge any site or CDN caches after a refresh.

### Updating the plugin

Upload the new plugin ZIP through **Plugins → Add New → Upload Plugin**, then choose **Replace current with uploaded**. Existing imported data remains available. Re-import only when an update specifically requires it or you want to refresh your genealogy data.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- PRIVACY -->

## Privacy

Names and family relationships are public, including those of living people. A person is treated as living unless their GEDCOM record provides death or burial evidence: a `Y` flag, a date or a place. No age-based death assumptions are made.

Dates, places, notes, personal facts, timelines and maps are hidden for living people and people whose status is unknown. Marriage and divorce details require both partners to be known deceased. All visitors, including administrators viewing the public pages, see the same filtered information.

Review free-text facts, source transcripts and notes about deceased people before publishing: these may mention living relatives. Automated privacy filtering cannot identify every reference within prose.

Raw uploads are not copied into the public Media Library. Parsed genealogy data is stored in the WordPress database; protect database backups accordingly. Deactivating the plugin retains data. Uninstalling it removes the imported dataset and saved place coordinates, while leaving the generated WordPress page in place.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- KNOWN ISSUES -->

## Known Issues

- Photographs, document galleries and GEDZIP imports are not currently supported.
- Shared `NOTE` records and advanced GEDCOM extensions are not rendered.
- Historical place names and ambiguous locations require manual coordinate review.
- Very large trees may exceed host limits; the importer stores one complete dataset in the database.
- WordPress themes and caching plugins can affect appearance and refresh behaviour.

Full compatibility notes, privacy rules and release history are in [gedcom-family-history/readme.txt](gedcom-family-history/readme.txt).

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTRIBUTING -->

## Contributing

Suggestions, bug reports and improvements are welcome. When reporting an import problem, include the error and a small anonymised GEDCOM example rather than publishing a complete family export.

To contribute code:

1. Create a branch for your change.
2. Make the change and update relevant documentation.
3. Run the regression checks:

   ```sh
   php tests/run.php
   ```

4. Check PHP syntax for changed files and test the relevant behaviour in a staging WordPress installation.
5. Submit the change for review.

The regression suite uses a lightweight WordPress-function harness. It covers parsing, profile rendering, relationships, dates, privacy, maps and statistics; it does not replace testing uploads and admin workflows in WordPress.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- LICENSE -->

## License

Distributed under the GNU General Public License v3.0. See `LICENSE` for more information.

Leaflet is distributed under its own BSD-2-Clause license; see [assets/leaflet/LICENSE](gedcom-family-history/assets/leaflet/LICENSE).

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTACT -->

## Contact

X - [@spokenlikeageek](https://x.com/spokenlikeageek)

Bluesky - [@spokenlikeageek.com](https://bsky.app/profile/spokenlikeageek.com)

Mastodon - [@spokenlikeageek](https://techhub.social/@spokenlikeageek)

Website - [https://spokenlikeageek.com](https://www.spokenlikeageek.com/tag/gedcom)

Project link - [Github](https://github.com/williamsdb/GEDCOM-to-WordPress)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- ACKNOWLEDGMENTS -->

## Acknowledgments

- [WordPress](https://wordpress.org/)
- [Leaflet](https://leafletjs.com/)
- [OpenStreetMap contributors](https://www.openstreetmap.org/copyright)
- The [php2Bluesky README](https://github.com/williamsdb/php2Bluesky) for the document format.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->

[contributors-shield]: https://img.shields.io/github/contributors/github_username/repo_name.svg?style=for-the-badge
[contributors-url]: https://github.com/github_username/repo_name/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/github_username/repo_name.svg?style=for-the-badge
[forks-url]: https://github.com/github_username/repo_name/network/members
[stars-shield]: https://img.shields.io/github/stars/github_username/repo_name.svg?style=for-the-badge
[stars-url]: https://github.com/github_username/repo_name/stargazers
[issues-shield]: https://img.shields.io/github/issues/github_username/repo_name.svg?style=for-the-badge
[issues-url]: https://github.com/github_username/repo_name/issues
[license-shield]: https://img.shields.io/github/license/github_username/repo_name.svg?style=for-the-badge
[license-url]: https://github.com/github_username/repo_name/blob/master/LICENSE.txt
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-black.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/linkedin_username
[product-screenshot]: images/screenshot.png
[Next.js]: https://img.shields.io/badge/next.js-000000?style=for-the-badge&logo=nextdotjs&logoColor=white
[Next-url]: https://nextjs.org/
[React.js]: https://img.shields.io/badge/React-20232A?style=for-the-badge&logo=react&logoColor=61DAFB
[React-url]: https://reactjs.org/
[Vue.js]: https://img.shields.io/badge/Vue.js-35495E?style=for-the-badge&logo=vuedotjs&logoColor=4FC08D
[Vue-url]: https://vuejs.org/
[Angular.io]: https://img.shields.io/badge/Angular-DD0031?style=for-the-badge&logo=angular&logoColor=white
[Angular-url]: https://angular.io/
[Svelte.dev]: https://img.shields.io/badge/Svelte-4A4A55?style=for-the-badge&logo=svelte&logoColor=FF3E00
[Svelte-url]: https://svelte.dev/
[Laravel.com]: https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white
[Laravel-url]: https://laravel.com
[Bootstrap.com]: https://img.shields.io/badge/Bootstrap-563D7C?style=for-the-badge&logo=bootstrap&logoColor=white
[Bootstrap-url]: https://getbootstrap.com
[JQuery.com]: https://img.shields.io/badge/jQuery-0769AD?style=for-the-badge&logo=jquery&logoColor=white
[JQuery-url]: https://jquery.com
