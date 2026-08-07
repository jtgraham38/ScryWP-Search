=== Scry Search: Meilisearch for WordPress ===
Contributors: jtgraham38
Tags: meilisearch, search, developer, hooks, extendable
Requires at least: 5.2
Tested up to: 7.0
Stable tag: 1.4.0
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Meilisearch for WordPress — by developers for developers, usable without writing code. Headless, documented hooks, drop-in WP_Query search.

== Get Managed Hosting from ScryWP ==

Don't want to run Meilisearch yourself? [ScryWP Search](https://scrywp.com) is managed hosting aimed at WordPress.

== Description ==

Scry Search is built by developers for developers — but you don't need to be one to run it.

If you can install a plugin and paste API keys, you can connect Meilisearch, pick post types, index content, tune ranking/filterable fields/synonyms/typo tolerance/weights, and turn on autosuggest from wp-admin. No theme edits required. Existing search forms keep working. WooCommerce products are a first-class post type.

If you *are* a developer, the same plugin is meant to be extended: `scry_ms_*` filters/actions, optional `window.scrySearch`, and a full hook reference in DOCS.md ([GitHub](https://github.com/jtgraham38/ScryWP-Search)). We use this kind of surface on client work and our own products, so the priorities are boring on purpose: stable hook names, easy-to-find call sites, a real docs file, and no forced frontend chrome.

= Works from the admin (no code) =

* Connection Settings — URL and API keys, with a connection test
* Index Settings — post types, searchable and filterable fields (including meta and taxonomies), ranking rules (including custom attribute:asc/desc), synonyms, stop words, dictionary, and typo tolerance; bulk index / wipe
* Search Settings — post-type weights for federated search, optional autosuggest and matched-term highlighting
* Task drawer, Logs, and Search Analytics for day-to-day ops

Site owners and agencies can go live without custom development. Developers can still hook in when a project needs it.

= Headless: no frontend UI required =

This plugin does not force a search UI on you. No required shortcode, widget, or results template.

What you get by default is indexing and Meilisearch-backed queries. Autosuggest and highlighting are optional and off unless you turn them on. Keep your theme's search, or build your own UI against `WP_Query` / the autosuggest REST endpoint.

= Drop-in WordPress search =

Scry Search hooks `posts_pre_query`. Any `WP_Query` with a search string can go through Meilisearch (main search, programmatic queries, autosuggest) and still return normal `WP_Post` objects.

* No theme rewrites, no forced shortcodes, no widget lock-in
* `search.php`, `searchform.php`, core search widgets/blocks, and page-builder search elements keep working if you use them
* Opt out per query with `scry_ms_should_search` when you need native WP search

Works with Elementor, Divi, Beaver Builder, and similar tools: whatever search box they output still hits WordPress search, which Scry Search routes to Meilisearch.

= AJAX autosuggest (optional) =

Enable Autosuggest under Scry Search → Search Settings. The plugin attaches debounced AJAX to existing `name="s"` fields. Suggestions use the same Meilisearch indexes as full-site search. Optional CSS class selector so only the forms you choose get typeahead. Thumbnails supported when a featured image exists.

Shape the result data with `scry_ms_autosuggest_results`, or the rendered HTML with `scry_ms_autosuggest_results_rendered`. Leave autosuggest off to stay fully headless.

= Per-post-type indexes and federated search =

Index any registered post type independently — posts, pages, WooCommerce products, CPTs from other plugins. Each type gets its own Meilisearch index:

* Searchable fields — titles, content, excerpts, taxonomies, author, custom meta (ACF, Meta Box, etc.)
* Filterable fields — post type/status/author, post date, taxonomy IDs (`taxonomies.<name>.id`), and more for facets and advanced filters
* Ranking rules — drag-and-drop reorder (words, typo, proximity, attribute, sort, exactness) plus custom `attribute:asc` / `attribute:desc` rules per index
* Search weights — e.g. weight products above blog posts in the merged results

Search uses Meilisearch federated multi-search: indexes queried together, results merged and re-ranked with your per–post-type weights (not a hand-stitched PHP merge).

= Relevancy, filtering, and language settings =

Per index from the tabbed Index Settings dialog (or via filters):

* Reorder built-in ranking rules and add custom ranking rules
* Choose which attributes are searchable and which are filterable
* Synonyms — nicknames, abbreviations, UK/US spelling, brand aliases
* Stopwords — drop noise terms that shouldn't affect ranking
* Dictionary — keep acronyms and multi-word brand names from being split during tokenization
* Typo tolerance — enable/disable, min word sizes, and disable on numbers, words, or attributes
* View Raw JSON for each settings group when debugging

= WooCommerce =

Fully compatible. Select `product` in Index Settings, choose product fields and meta, set federation weights if you also search posts/pages. Catalog search upgrades; theme and checkout stay as they are. Same hooks as any other post type if you need custom product documents or autosuggest rows.

= Search analytics =

Scry Search → Search Analytics:

* Dashboard with summary metrics, charts, and recent searches
* Optional IP anonymization / omit identifying fields
* Retention period with daily WP-Cron cleanup, plus a manual “delete old events” button
* CSV export of the analytics table (admin-only, nonce-protected)
* Extra fields via `scry_ms_analytics_event_to_insert` (non-column keys go into `search_metadata`)

= Task monitor =

Task drawer on plugin admin screens:

* Indexing tasks with status, duration, and errors
* Paginated history
* Scoped to indexes this plugin manages (shared Meilisearch instances won't dump unrelated tasks)

= Debug and error logs =

Scry Search → Logs:

* Debug and Error levels, filterable in the viewer
* Stored in the database, newest first, load-more paging
* API keys / tokens redacted before storage
* Retention + daily cleanup, or clean up on demand

= Automatic and manual indexing =

* Auto-index on create/update; remove on trash/delete; re-index on untrash
* One-click bulk index per post type
* Wipe and rebuild when you need a clean slate
* Live search preview from the indexes UI before you ship ranking changes

= For developers: hooks and JS runtime =

Admin covers setup and tuning. When you need custom behavior, use the public `scry_ms_*` PHP hooks and the optional `window.scrySearch` runtime. Call sites are marked `//@HOOK: scry_ms_…` in source.

Full argument lists, return types, timing, and JS examples: [DOCS.md on GitHub](https://github.com/jtgraham38/ScryWP-Search/blob/main/DOCS.md) (also shipped with the plugin).

Code is split into `features/<name>/` packages. Prefer a search-only API key for front-end paths; the shared client factory supports `admin` vs `search`.

**PHP filters**

Indexing and documents:

* `scry_ms_should_index`
* `scry_ms_should_delete`
* `scry_ms_index_prepare_document`
* `scry_ms_index_names`
* `scry_ms_index_searchable_attributes`
* `scry_ms_index_filterable_attributes`
* `scry_ms_index_filterable_fields`
* `scry_ms_index_typo_tolerance`
* `scry_ms_bulk_index_query_args`
* `scry_ms_bulk_index_batch_size`
* `scry_ms_index_ranking_rules`
* `scry_ms_index_fields`
* `scry_ms_index_meta_keys`

Index settings:

* `scry_ms_index_settings_ajax`
* `scry_ms_index_settings_backup`
* `scry_ms_index_ranking_rules_before_update`
* `scry_ms_index_searchable_attributes_before_update`
* `scry_ms_index_synonyms_before_update`
* `scry_ms_index_stop_words_before_update`
* `scry_ms_index_filterable_attributes_before_update`
* `scry_ms_index_dictionary_before_update`
* `scry_ms_index_typo_tolerance_before_update`

Search and client:

* `scry_ms_should_search`
* `scry_ms_meilisearch_client`
* `scry_ms_multi_search_index_names`
* `scry_ms_multi_search_query_params`
* `scry_ms_multi_search_query`
* `scry_ms_multi_search_queries`
* `scry_ms_multi_search_federation`
* `scry_ms_multi_search_raw_results`
* `scry_ms_multi_search_final_results`

Autosuggest, admin, analytics, logs, window:

* `scry_ms_autosuggest_query`
* `scry_ms_autosuggest_results`
* `scry_ms_autosuggest_results_rendered`
* `scry_ms_admin_pages`
* `scry_ms_analytics_event_to_insert`
* `scry_ms_log_message`
* `scry_ms_window_localized`
* `scry_ms_premium_upgrades_display`

**PHP actions**

* `scry_ms_after_index_document`
* `scry_ms_after_delete_document`
* `scry_ms_after_bulk_index`
* `scry_ms_after_create_index`
* `scry_ms_index_settings_restore`
* `scry_ms_index_update_settings`
* `scry_ms_index_settings_sections_ui`
* `scry_ms_premium_upgrade_settings_ui`

**JavaScript runtime (`window.scrySearch`)**

Optional. Enqueued on the front end (script handle `scry_ms_window-script`). Wait for the `scrySearchReady` event on `document` before using it.

* `window.scrySearch.version`
* `window.scrySearch.getSearchForms()`
* `window.scrySearch.getSearchFormsByClass(className)`
* `window.scrySearch.registerUpgrade(name, version)` — namespace for add-ons under `window.scrySearch.upgrades`

Per form (`ScrySearch_SearchForm`):

* `formElement`, `searchInput`, `data`
* `submit()`, `submitAjax()` (debounced)
* `addPreSubmitAction(fn, order)` / `addPostSubmitAction(fn, order)`
* `addPreSubmitAjaxAction(fn, order)` / `addPostSubmitAjaxAction(fn, order)`

A search form is any `<form>` with `role="search"` or a text/search input named `s`. Autosuggest and similar features attach through the AJAX action lists. Details and examples: [DOCS.md](https://github.com/jtgraham38/ScryWP-Search/blob/main/DOCS.md).

= Hosting =

1. [ScryWP Search](https://scrywp.com) — managed Meilisearch for WordPress
2. Self-hosted — your server, full control
3. Local — for development and testing

Enter URL + keys under Scry Search → Connection Settings, pick post types, index, done.

= Works with your existing setup =

* Theme search templates (`search.php`, `searchform.php`)
* Core search widgets and Gutenberg search blocks
* Page builders (Elementor, Divi, Beaver Builder, etc.)
* WooCommerce products and product meta
* Any registered custom post type
* Optional autosuggest / highlighting when you enable them

== Installation ==

1. Have a Meilisearch instance running ([ScryWP](https://scrywp.com), self-hosted, or local).
2. Install and activate the plugin (upload to `wp-content/plugins/` or via Plugins → Add New).
3. Scry Search → Connection Settings: URL, admin key, optional search key; test connection.
4. Scry Search → Index Settings: choose post types, save, Index Posts for each.
5. WordPress search now uses Meilisearch. No theme changes required.
6. Optional: enable autosuggest or highlighting under Search Settings.
7. Optional: open DOCS.md and add your first `add_filter( 'scry_ms_…' )`.

You can complete the whole flow from wp-admin without custom code. Hooks are there when you need them.

== Frequently Asked Questions ==

= What makes this developer-friendly? =

It's built by people who write WordPress integrations for a living. Hooks are documented and marked in code, there's a shared client filter, and DOCS.md is the reference. Headless by default if you don't want plugin UI on the front.

= Can non-developers use it? =

Yes. Connect Meilisearch, select post types, index, and adjust ranking, synonyms, weights, and autosuggest from wp-admin. You only need a developer when you want custom behavior beyond the settings screens.

= Do I have to use a frontend UI from the plugin? =

No. Search is `WP_Query` (and optional REST). Autosuggest and highlighting are opt-in. Use your own templates or a separate front.

= Do I need to change my theme? =

No. Existing search forms and templates keep working. If you don't have any, the plugin still doesn't force one on you. Page-builder search elements work the same way.

= What is Meilisearch? =

Open-source, typo-tolerant search engine: [meilisearch.com](https://www.meilisearch.com/). This plugin assumes you already chose it (or will via ScryWP); we focus on the WordPress integration.

= ScryWP or self-host? =

[ScryWP](https://scrywp.com) if you don't want to operate Meilisearch. Self-host for control, compliance, or local/dev. Either way you paste URL and keys into the plugin.

= Custom post types and meta? =

Yes. Each selected post type gets its own index. Meta shows up in searchable fields; tweak via admin or `scry_ms_index_fields` / `scry_ms_index_meta_keys` / `scry_ms_index_prepare_document`.

= How does federated search work? =

Meilisearch multi-search with federation across your indexes, using the weights you set in Search Settings. Adjust the request with `scry_ms_multi_search_*`.

= Will this work with WooCommerce? =

Yes. Index `product`, choose fields/meta, set weights if you federate with other types. Theme and checkout stay unchanged.

= Synonyms, stopwords, dictionary, and typo tolerance? =

Yes — per index in the Index Settings dialog (dedicated tabs), no Meilisearch config files required. Also filterable from code when you need that.

= Can I configure filterable fields from WordPress? =

Yes. The Filterable Fields tab lets you pick core fields, taxonomy IDs, and related attributes. Defaults and the field tree are adjustable with `scry_ms_index_filterable_attributes` / `scry_ms_index_filterable_fields`.

= How do I customize with code? =

See **For developers: hooks and JS runtime** above for the full list. Common starters: `scry_ms_should_index`, `scry_ms_index_prepare_document`, `scry_ms_meilisearch_client`, `scry_ms_autosuggest_results` / `scry_ms_autosuggest_results_rendered`, `scry_ms_admin_pages`. Signatures and examples: [DOCS.md](https://github.com/jtgraham38/ScryWP-Search/blob/main/DOCS.md).

= JavaScript API? =

Yes — optional. See **For developers: hooks and JS runtime**. Wait for `scrySearchReady`, then use `window.scrySearch` and per-form pre/post submit (and AJAX) action lists. Skip it if you're not attaching front-end behavior. Details in DOCS.md.

= How do I debug indexing? =

Task drawer for Meilisearch tasks on managed indexes; Scry Search → Logs for plugin debug/error lines (secrets redacted, retention available).

= Can I export analytics? =

Scry Search → Search Analytics: CSV export, retention/cleanup, privacy options, and `scry_ms_analytics_event_to_insert` for extra fields.

= Is it secure? =

AJAX nonces, capability checks, sanitized/escaped I/O. Prefer a search-only API key for front-end paths; the client factory supports `admin` vs `search` keys.

== Screenshots ==

1. Index Settings Dashboard - Manage post type indexes, view document counts, and trigger indexing operations
2. Index Configuration Modal - Tabbed settings for ranking rules (including custom rules), searchable/filterable fields, synonyms, stop words, dictionary, and typo tolerance
3. Connection Settings - Configure Meilisearch URL and API keys with connection testing
4. Search Settings - Configure post type search weights for federated search, enable AJAX autosuggest, and set the class selector for which forms receive predictive search
5. Task Drawer - Monitor Meilisearch tasks with status, timing, and error details
6. Live Search Preview - Test search queries directly from the admin panel
7. Search Analytics - Dashboard, privacy/retention settings, CSV export of analytics data

== Changelog ==

= 1.4.0 =
* Tabbed Index Settings dialog covering ranking rules, searchable fields, filterable fields, synonyms, stop words, dictionary, and typo tolerance
* Filterable attributes managed in core — including post taxonomies (`taxonomies.<name>.id`) and `post_date_unix`
* Custom ranking rules (`attribute:asc` / `attribute:desc`) with drag-and-drop ordering alongside built-in rules
* Dictionary and typo tolerance settings (enable/disable, min word sizes, disable on numbers/words/attributes)
* View Raw JSON on each settings section for debugging
* Settings backup/restore now includes filterable attributes, dictionary, and typo tolerance
* New hooks: `scry_ms_index_filterable_attributes`, `scry_ms_index_filterable_fields`, `scry_ms_index_filterable_attributes_before_update`, `scry_ms_index_dictionary_before_update`, `scry_ms_index_typo_tolerance`, `scry_ms_index_typo_tolerance_before_update`, `scry_ms_bulk_index_batch_size`
* Bulk indexing processes posts in batches (memory-friendly; batch size filterable)
* Setup breadcrumb navigation on admin pages
* DOCS.md / README updated for the new index settings surface

= 1.3.0 =
* New hooks: `should_index`, `should_delete`, `should_search`, `after_index_document`, `after_delete_document`, `after_bulk_index`, `index_names`, `index_searchable_attributes`, `bulk_index_query_args`, `meilisearch_client`, `autosuggest_results`, `autosuggest_results_rendered`, `admin_pages`
* Customize how your site displays autosuggest results with the `autosuggest_results_rendered` hook
* Documents now include public taxonomy terms; `index_prepare_document` gets `WP_Post` as second arg
* Sync on `delete_post` and `untrash_post`
* DOCS.md / README updated
* Optional matched-term highlighting in results and autosuggest (Search Settings; CSS class `.scry-ms-highlight`)
* Autosuggest can show featured-image thumbnails
* Analytics: extra keys on `scry_ms_analytics_event_to_insert` stored in `search_metadata` (CSV too)
* Premium Upgrades lists Scry Search Hybrid alongside Filters
* Index settings restored from WP only when an index is newly created (avoids re-PATCHing Meilisearch every admin load when embedders are set)

= 1.2.1 =
* Task drawer only lists tasks for indexes this plugin manages
* Premium Upgrades page layout/assets fix

= 1.2.0 =
* Logs screen (debug/error, retention, secret redaction)
* More `scry_ms_*` hooks; documented in DOCS.md
* `window.scrySearch` JS API
* Premium Upgrades admin page
* Federated search hit-count fix

= 1.1.2 =
* Analytics CSV export, retention/cleanup, privacy settings on Search Analytics

= 1.0.3 =
* Optional AJAX autosuggest (Search Settings toggle + optional class selector)

= 1.0.2 =
* Synonyms and stopwords from the WordPress admin

= 1.0.1 =
* ScryWP-hosted Meilisearch support

= 1.0.0 =
* Initial release: per-post-type indexes, federated search, ranking/searchable fields, auto + bulk indexing, task drawer, live preview, drop-in WP search

== Upgrade Notice ==

= 1.4.0 =
Tabbed Index Settings: filterable fields, custom ranking rules, dictionary, typo tolerance, raw JSON viewers, plus batched bulk indexing and new `scry_ms_*` hooks. See DOCS.md.

= 1.3.0 =
More developer hooks (indexing/search gates, client factory, autosuggest results, admin tabs), optional highlighting, autosuggest thumbnails, analytics metadata, Hybrid listing, indexing fix for embedder setups. See DOCS.md.

= 1.2.1 =
Upgrades page updates; task pane fix for multi-tenant Meilisearch.

= 1.2.0 =
Logs screen and expanded hooks / `window.scrySearch` (see DOCS.md).

= 1.1.2 =
Analytics CSV export and retention cleanup.

= 1.0.3 =
Optional AJAX autosuggest under Search Settings.

= 1.0.0 =
Initial release.

== Requirements ==

* WordPress 5.2 or higher
* PHP 8.1 or higher
* A Meilisearch instance (ScryWP or self-hosted)

== Support ==

Hooks/docs: DOCS.md or [GitHub](https://github.com/jtgraham38/ScryWP-Search). Issues and questions: same repo or [JG Web Development](https://jacob-t-graham.com).
