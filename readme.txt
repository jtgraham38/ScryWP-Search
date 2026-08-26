=== Scry Search: Meilisearch for WordPress ===
Contributors: jtgraham38
Tags: meilisearch, search, developer, hooks, extendable
Requires at least: 5.2
Tested up to: 7.1
Stable tag: 1.5.1
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Meilisearch for WordPress — keyword + built-in semantic/hybrid search. Drop-in WP_Query, ~45 PHP hooks, no theme changes.

== Get Managed Hosting from ScryWP ==

Don't want to run Meilisearch yourself? [ScryWP Search](https://scrywp.com) is managed hosting aimed at WordPress.

== Description ==

Scry Search wires Meilisearch into WordPress the way you'd want it on a client project: fast keyword search, optional AI/semantic (hybrid) search, and a big extension surface — without ripping out your theme.

**Docs (start here):** [DOCS.md on GitHub](https://github.com/jtgraham38/ScryWP-Search/blob/main/DOCS.md) — every filter/action, args, return values, when it fires, plus the `window.scrySearch` JS API. Same file ships in the plugin zip.

You get over **40 PHP hooks** (`scry_ms_*`) covering indexing, documents, index settings, federated search, autosuggest, analytics, logs, and the front-end runtime. Call sites are marked in source. If you can install a plugin and paste API keys, you can run it without writing code. If you ship WordPress for a living, you can bend it without forking.

= No theme changes. Not glued to a results UI. =

This is the important bit:

* We do **not** replace your search results template. `search.php` (or whatever your theme / builder uses) still renders the results.
* We do **not** require a shortcode, block, or widget. Autosuggest and highlighting are optional and off unless you turn them on.
* Existing search forms keep working. Gutenberg Search block, classic `searchform.php`, Elementor/Divi/Beaver search widgets — they still POST to WordPress; we intercept the query.

Admin-side tuning is a different story: ranking, searchable/filterable fields, synonyms, typo tolerance, hybrid embedders, federated weights, etc. are all in wp-admin (see below). You’re not stuck editing Meilisearch JSON by hand — you’re just not locked into a plugin-owned results page.

Under the hood we hook `posts_pre_query`, run the search in Meilisearch, and hand WordPress normal `WP_Post` objects. Same loop, same template tags, same pagination assumptions your theme already has.

Need native WP search for one query? `scry_ms_should_search`.

= Built-in semantic / hybrid search =

Keyword search is the baseline. Hybrid search is built into the core plugin (not a separate SKU): Meilisearch blends full-text ranking with vector similarity so "fuzzy intent" queries still hit the right posts/products.

Per index, under **Index Settings → Configure Index → Hybrid Search**:

* Add embedders (OpenAI, Hugging Face, Ollama, user-provided vectors, other Meilisearch-supported sources)
* Pick which embedder to use and the semantic vs keyword ratio
* Enable hybrid per post type — products can differ from posts

Same `WP_Query` path as keyword search. No second endpoint, no alternate results page. Reindex after embedder changes so vectors exist. Analytics can record hybrid usage in `search_metadata`.

= What you configure in wp-admin =

Most of Meilisearch’s knobs are in WordPress — you don’t need to curl the instance for day-to-day tuning.

**Connection** — URL, admin key, optional search-only key, connection test.

**Index Settings** (tabbed dialog per post type):

* Searchable fields — titles, content, excerpts, taxonomies, author, custom meta (ACF, Meta Box, etc.); drag to set relevancy order
* Filterable fields — post type/status/author, `post_date_unix`, taxonomy IDs (`taxonomies.<name>.id`), and more for facets / advanced filters
* Ranking rules — reorder words / typo / proximity / attribute / sort / exactness; add custom `attribute:asc` / `attribute:desc`
* Synonyms, stop words, dictionary — nicknames, brand aliases, noise terms, multi-word tokens
* Typo tolerance — enable/disable, min word sizes, disable on numbers / words / attributes
* Hybrid Search — embedders and semantic ratio (see above)
* View Raw JSON on each group when something looks off
* Bulk index / wipe, plus a live search preview before you ship ranking changes

**Search Settings** — federated post-type weights (e.g. products above blog posts), optional AJAX autosuggest (with optional form class targeting), optional matched-term highlighting (`.scry-ms-highlight`).

**Ops** — task pane, Logs, Search Analytics (see **For developers**).

You can go live from these screens alone. Hooks kick in when a project needs something the UI doesn’t cover.

= Autosuggest + highlighting (optional front-end extras) =

These are the only front-end UI pieces the plugin ships, and both are off by default. Your theme’s results page stays yours either way.

**Autosuggest** — Search Settings toggle. The front-end runtime finds forms via CSS selectors (defaults: `#adminbarsearch`, `form[role="search"]`, including the core Search block). Debounced AJAX, same indexes as full search. Optional form class if you only want typeahead on specific forms. Thumbnails when a featured image exists. Extend discovery selectors with `scry_ms_window_localized` — details in DOCS.md. Shape data with `scry_ms_autosuggest_results` or markup with `scry_ms_autosuggest_results_rendered`.

**Highlighting** — optional matched-term markup in results / autosuggest (class `.scry-ms-highlight`). Style it in your theme; we don’t force a look.

= Indexes and federation =

Each registered post type can get its own Meilisearch index (posts, pages, `product`, CPTs). Federated multi-search merges them with your Search Settings weights — not a home-rolled PHP merge.

WooCommerce: select `product`, pick product fields/meta, set weights if you also search posts/pages. Catalog search upgrades; theme and checkout stay as they are.

= Ops: indexing =

* Auto-index on save; remove on trash/delete; re-index on untrash. Bulk index / wipe from Index Settings.
* Task pane, Logs, and Search Analytics are covered under **For developers** below (they’re useful for site owners too, but they’re built as day-to-day ops tooling).

= For developers =

**Full hook reference + JS API:** [DOCS.md on GitHub](https://github.com/jtgraham38/ScryWP-Search/blob/main/DOCS.md)

**45 PHP hooks** documented there (filters + actions). Names below; args/returns/timing in the docs. Call sites tagged `//@HOOK: scry_ms_…` in source.

**Task pane** — drawer on plugin admin screens for Meilisearch tasks on indexes this plugin owns: status, duration, errors, paginated history. Shared Meilisearch instances won’t dump unrelated tasks. Useful when bulk indexing stalls or a settings update fails silently.

**Error / debug logs** — Scry Search → Logs. Debug and Error levels, filterable viewer, DB-backed newest-first with load-more, API keys/tokens redacted before storage, retention + daily cleanup (or clean on demand). Hook `scry_ms_log_message` if you need to divert or annotate lines.

**Search analytics** — Scry Search → Search Analytics. Dashboard with summary metrics, charts, and recent searches; optional IP anonymization / omit identifying fields; retention with WP-Cron cleanup; CSV export (admin-only, nonce-protected). Extend rows with `scry_ms_analytics_event_to_insert` (non-column keys go into `search_metadata`, including hybrid usage when enabled).

Filters — indexing / documents:

* `scry_ms_should_index`, `scry_ms_should_delete`, `scry_ms_index_prepare_document`
* `scry_ms_index_names`, `scry_ms_index_searchable_attributes`, `scry_ms_index_filterable_attributes`, `scry_ms_index_filterable_fields`
* `scry_ms_index_typo_tolerance`, `scry_ms_index_ranking_rules`, `scry_ms_index_fields`, `scry_ms_index_meta_keys`
* `scry_ms_bulk_index_query_args`, `scry_ms_bulk_index_batch_size`

Filters — settings:

* `scry_ms_index_settings_ajax`, `scry_ms_index_settings_backup`
* `scry_ms_index_ranking_rules_before_update`, `scry_ms_index_searchable_attributes_before_update`
* `scry_ms_index_synonyms_before_update`, `scry_ms_index_stop_words_before_update`
* `scry_ms_index_filterable_attributes_before_update`, `scry_ms_index_dictionary_before_update`, `scry_ms_index_typo_tolerance_before_update`

Filters — search / client:

* `scry_ms_should_search`, `scry_ms_meilisearch_client`
* `scry_ms_multi_search_index_names`, `scry_ms_multi_search_query_params`, `scry_ms_multi_search_query`, `scry_ms_multi_search_queries`
* `scry_ms_multi_search_federation`, `scry_ms_multi_search_raw_results`, `scry_ms_multi_search_final_results`

Filters — autosuggest / admin / analytics / logs / window:

* `scry_ms_autosuggest_query`, `scry_ms_autosuggest_results`, `scry_ms_autosuggest_results_rendered`
* `scry_ms_admin_pages`, `scry_ms_analytics_event_to_insert`, `scry_ms_log_message`, `scry_ms_window_localized`

Actions:

* `scry_ms_after_index_document`, `scry_ms_after_delete_document`, `scry_ms_after_bulk_index`, `scry_ms_after_create_index`
* `scry_ms_index_settings_restore`, `scry_ms_index_update_settings`, `scry_ms_index_settings_sections_ui`

**JS:** `window.scrySearch` (handle `scry_ms_window-script`). Wait for `scrySearchReady`. Forms from `windowLocalized.searchFormSelectors`; filter `scry_ms_window_localized` to add selectors (e.g. `form.my-search`). Per-form pre/post submit + AJAX hooks for custom front-end behavior.

Code lives under `features/<name>/`. Prefer a search-only key on the front; the client factory supports admin vs search.

= Hosting =

1. [ScryWP](https://scrywp.com) — managed Meilisearch for WP
2. Self-hosted
3. Local (dev)

Paste URL + keys, pick post types, index. Done.

= Fits what you already have =

* Your theme's `search.php` / results markup — unchanged
* `searchform.php`, core search block/widget, page-builder search boxes
* WooCommerce products
* Custom post types + meta
* Headless / custom front via `WP_Query` or REST if you build one

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

45 documented `scry_ms_*` hooks, call sites marked in source, shared client filter, and a real docs file — not a marketing PDF. Plus the tooling you’d actually use on a site: task pane for Meilisearch jobs, error/debug logs, and search analytics (dashboard + CSV). Start here: [DOCS.md](https://github.com/jtgraham38/ScryWP-Search/blob/main/DOCS.md). Optional `window.scrySearch` if you need front-end glue. No forced search results UI.

= Can non-developers use it? =

Yes. Connect Meilisearch, pick post types, index, tune ranking/synonyms/weights/hybrid/autosuggest from wp-admin. Bring a developer when you need behavior the screens don't cover.

= Do I need semantic / AI search? =

Optional. Keyword search works without it. Hybrid is in core when you want vector similarity alongside full-text — configure embedders per index, reindex, same results templates.

= Do I have to use a frontend UI from the plugin? =

No. We don’t own your results page — your theme (or builder) still displays posts from `WP_Query`. Ranking, fields, synonyms, typo tolerance, hybrid, weights, etc. are configured in wp-admin. Autosuggest and highlighting are the optional front-end extras; leave them off and you’re indexing + query routing only.

= Do I need to change my theme? =

No. Forms and `search.php` (or equivalent) keep working. We swap the query engine, not the markup. Page-builder search boxes that hit WordPress search work the same way.

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

Hook list is under **For developers** above. Common starters: `scry_ms_should_index`, `scry_ms_index_prepare_document`, `scry_ms_meilisearch_client`, `scry_ms_autosuggest_results` / `scry_ms_autosuggest_results_rendered`, `scry_ms_admin_pages`. Full signatures: [DOCS.md](https://github.com/jtgraham38/ScryWP-Search/blob/main/DOCS.md).

= JavaScript API? =

Optional. Wait for `scrySearchReady`, then `window.scrySearch` + per-form pre/post (and AJAX) hooks. Skip it if you're not doing front-end work. Examples in DOCS.md.

= How do I debug indexing? =

Task pane for Meilisearch tasks on managed indexes; Scry Search → Logs for plugin debug/error lines (secrets redacted, retention available). See **For developers**.

= Can I export analytics? =

Yes — Search Analytics includes CSV export, retention/cleanup, privacy options, and `scry_ms_analytics_event_to_insert` for extra fields. Covered under **For developers**.

= Is it secure? =

AJAX nonces, capability checks, sanitized/escaped I/O. Prefer a search-only API key for front-end paths; the client factory supports `admin` vs `search` keys.

== Screenshots ==

1. Index Settings Dashboard - Manage post type indexes, view document counts, and trigger indexing operations
2. Index Configuration Modal - Tabbed settings for ranking rules, searchable/filterable fields, synonyms, stop words, dictionary, typo tolerance, and hybrid search embedders
3. Connection Settings - Configure Meilisearch URL and API keys with connection testing
4. Search Settings - Configure post type search weights for federated search, enable AJAX autosuggest, and set the class selector for which forms receive predictive search
5. Task Drawer - Monitor Meilisearch tasks with status, timing, and error details
6. Live Search Preview - Test search queries directly from the admin panel
7. Search Analytics - Dashboard, privacy/retention settings, CSV export of analytics data

== Changelog ==

= 1.5.1 =
* Front-end form discovery via CSS selectors (`#adminbarsearch`, `form[role="search"]` by default), including Gutenberg Search block forms
* Extend discovery selectors with `scry_ms_window_localized` (`searchFormSelectors`)
* Autosuggest class selector limits typeahead to discovered forms that carry a given CSS class on `<form>`
* Window and autosuggest use separate localized JS objects (avoids config collisions)
* DOCS.md / README updated for form discovery and autosuggest targeting

= 1.5.0 =
* Hybrid / semantic search in core — Hybrid Search tab per index with embedder add/edit/delete, semantic ratio, and federated search integration
* Embedder definitions backed up in WordPress and restored when an index is recreated; preserved when wiping/reindexing
* Search analytics records hybrid usage in `search_metadata` (`scry_search_hybrid`) when hybrid is active
* Searchable fields: drag-and-drop reorder (saved as Meilisearch `searchableAttributes` order)
* Default searchable attribute order prioritizes title, excerpt, then content
* Index settings UI treats Meilisearch `["*"]` searchable attributes as all fields selected
* DOCS.md / README updated

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

= 1.5.1 =
Search forms are discovered by CSS selector; extend the list with `scry_ms_window_localized`, and optionally limit autosuggest by form class. See DOCS.md.

= 1.5.0 =
Hybrid/semantic search and embedder management are now built into Index Settings, plus draggable searchable-field ordering and analytics for hybrid queries. See DOCS.md.

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
