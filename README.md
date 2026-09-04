# Scry Search for Meilisearch (Developer Guide)

This README is aimed at contributors. It explains the plugin’s structure, naming conventions, public hooks, and the front-end `window.scrySearch` runtime used by features like autosuggest.

> **Extending the plugin?** See **[`DOCS.md`](DOCS.md)** for the complete, public extension reference: every PHP action/filter hook (type, arguments, return value, when it fires) and the full `window.scrySearch` JavaScript API. This README covers internal architecture and conventions; `DOCS.md` is the contract third‑party developers build against.

## Repo layout

- `scry_search.php`: plugin bootstrap (loads features, shared config, vendor/autoload, deactivation hook, etc.).
- `includes/class-lifecycle.php` + `uninstall.php`: deactivation cron cleanup and uninstall data removal.
- `features/<feature>/feature.php`: feature classes (admin pages, REST/AJAX endpoints, asset loading).
- `features/<feature>/assets/`: feature JS/CSS.
- `features/<feature>/elements/`: PHP view fragments used by admin pages.
- `vendor/`: Composer dependencies.

The codebase is organized as “features” that attach WordPress actions/filters, enqueue assets, and expose admin UI / endpoints.

## Feature architecture & conventions

Most feature classes extend `jtgraham38\jgwordpresskit\PluginFeature` and follow this pattern:

- **`add_actions()`**: register WordPress actions (admin menus, enqueue, AJAX, REST routes, cron).
- **`add_filters()`**: register WordPress filters (search interception, query shaping, document shaping).
- **`prefixed($name)`**: feature methods typically call `$this->prefixed('something')` when registering action names, option keys, AJAX actions, etc. This prevents collisions with other plugins.

### Naming conventions you should follow

- **Option keys**: always use `$this->prefixed('option_name')`.
- **AJAX actions**: always use `wp_ajax_{$this->prefixed('action_name')}` and verify the corresponding nonce.
- **Hook names** (plugin-specific actions/filters): use `$this->config('hook_prefix') . '<hook_name>'` so third-party extensions have a stable namespace.

## Registering features

Features are registered in the plugin bootstrap (`scry_search.php`) via the `jtgraham38\jgwordpresskit\Plugin` manager:

- Require the feature file: `require_once plugin_dir_path(__FILE__) . '/features/<feature>/feature.php';`
- Instantiate the feature class: `$feature = new ScrySearch_<FeatureName>Feature();`
- Register it with a **stable key**: `$plugin->register_feature('scry_ms_<feature_key>', $feature);`
- After all features are registered, call `$plugin->init();` once.

Conventions:

- **Feature keys**: keep them prefixed (e.g. `scry_ms_search`, `scry_ms_indexes`) and treat them as part of the public surface area (changing keys can break dependent code).
- **Where to put code**: prefer creating a new `features/<feature>/feature.php` (and `assets/` / `elements/` as needed) rather than expanding unrelated features.
- **WordPress hooks**: inside the feature class, register actions/filters in `add_actions()` / `add_filters()` so initialization is consistent and testable.

## Hooks (actions/filters) you can rely on

This plugin exposes a broad set of extension points spanning indexing, federated search query building, autosuggest, analytics, logging, and front-end localization.

> **The full hook reference lives in [`DOCS.md`](DOCS.md).** It lists every hook with its type, arguments, return value, and when it fires — keep it as the single source of truth for the public contract. The notes below are an internal map for contributors.

All hook names use the runtime prefix `scry_ms_` (i.e. `$this->config('hook_prefix') . '<hook_name>'`). The inline `//@HOOK:` comments at each call site mark every extension point in code.

| Area | Source file | Examples |
|---|---|---|
| Document/index shaping | `features/indexes/feature.php` | `scry_ms_should_index`, `scry_ms_should_delete`, `scry_ms_index_prepare_document`, `scry_ms_index_names`, `scry_ms_index_searchable_attributes`, `scry_ms_index_filterable_attributes`, `scry_ms_index_filterable_fields`, `scry_ms_index_typo_tolerance`, `scry_ms_index_fields`, `scry_ms_index_meta_keys`, `scry_ms_bulk_index_query_args`, `scry_ms_bulk_index_batch_size`, `scry_ms_after_index_document` / `after_delete_document` / `after_bulk_index` (actions) |
| Index settings flow | `features/indexes/feature.php` | `scry_ms_index_settings_ajax`, `scry_ms_index_settings_backup`, `scry_ms_index_*_before_update` (ranking, searchable, synonyms, stop words, filterable attributes, dictionary, typo tolerance), `scry_ms_index_update_settings` (action), `scry_ms_index_settings_restore` / `scry_ms_after_create_index` (on new indexes only), `scry_ms_index_settings_sections_ui` |
| Federated search | `features/search/feature.php` | `scry_ms_should_search`, `scry_ms_multi_search_index_names`, `scry_ms_multi_search_query_params`, `scry_ms_multi_search_query` (hybrid via per-index settings), `scry_ms_multi_search_raw_results`, `scry_ms_multi_search_final_results` |
| Meilisearch client | `features/client/feature.php` | `scry_ms_meilisearch_client` |
| Highlighting | `features/highlighting/feature.php` | Hooks the federated-search filters above (`multi_search_query` / `raw_results` / `final_results`); no dedicated public hooks |
| Autosuggest | `features/autosuggest/feature.php` | `scry_ms_autosuggest_query`, `scry_ms_autosuggest_results`, `scry_ms_autosuggest_results_rendered`, `scry_ms_autosuggest_localized` |
| Admin shell | `features/admin_page/feature.php` | `scry_ms_admin_pages` |
| Analytics | `features/analytics/feature.php` | `scry_ms_analytics_event_to_insert` (non-column keys packed into `search_metadata`; core adds `scry_search_hybrid` when hybrid is used), `scry_ms_analytics_recent_searches_columns`, `scry_ms_analytics_recent_searches_column` |
| Premium upgrades | `features/upgrades/feature.php` | `scry_ms_premium_upgrades_display` |
| Logging | `features/logs/feature.php` | `scry_ms_log_message` |
| Front-end window | `features/window/feature.php` | `scry_ms_window_localized` |

> When you add or change a hook, update **`DOCS.md`** (public contract) in the same change, and keep the `//@HOOK:` comment in sync with the real `scry_ms_` name.

### How to add a new hook safely

- **Pick the right type**:
  - Use a **filter** when callers should be able to change a value (`apply_filters`).
  - Use an **action** for notification / side effects (`do_action`).
- **Keep the payload stable**: pass structured arrays/objects with explicit keys rather than positional arguments that are easy to break.
- **Document it** in [`DOCS.md`](DOCS.md) (name, type, when it fires, args, return value), and mark the call site with a `//@HOOK: scry_ms_<name>` comment.

## Logging (debug & error)

The logs feature (`features/logs/feature.php`, key `scry_ms_logs`) provides a database-backed log used throughout the plugin and surfaced under **Scry Search → Logs**.

- **Writing logs**: from any feature, call `$this->get_feature('scry_ms_logs')->log($level, $message)`.
  - `$level` must be one of the configured levels (`debug`, `error`); unknown levels are ignored.
  - `$message` is a string. Build descriptive, single-line messages (use `sprintf()` for context such as the function name or post ID). Avoid logging secrets — messages are sanitized and common key/token formats are redacted, but don't rely on it as a catch-all.
  - The call is exception-safe and never throws (so logging can't break the calling code path).
- **Levels** are defined in the shared config in `scry_search.php` under `logs.levels`.
- **Reading/retention**: the Logs screen reads paginated entries; a daily WP-Cron event prunes entries older than the configured retention period, with a manual cleanup action available.
- **Filter**: `scry_ms_log_message` lets other code rewrite a message before it is stored (see [`DOCS.md`](DOCS.md)).

When adding logging to a feature, prefer `error` for genuine failures and `debug` for routine/expected bail-outs, and include the originating function name in the message so entries are easy to triage.

## Admin-side settings flow (Indexes)

Index settings are configured per-post-type (per Meilisearch index). The “Configure Index” dialog is **tabbed**; each `.scrywp-index-settings-section` is a tab (core sections live under `features/indexes/elements/dialog_sections/`). Premium/custom UI can append tabs via `scry_ms_index_settings_sections_ui`.

Core tabs:

- Ranking rules (built-in + custom `attribute:asc` / `attribute:desc`)
- Searchable fields (drag to set `searchableAttributes` order)
- Synonyms / stop words / dictionary
- Typo tolerance
- Filterable fields (including taxonomy IDs and `post_date_unix`)
- Hybrid search (embedder CRUD, enable/ratio per index; semantic + keyword blended in federated search)

Each section can expand **View Raw JSON** for the live Meilisearch value.

The dialog uses:

- **AJAX** `get_index_settings` to fetch current settings from Meilisearch (ranking rules, searchable/filterable attributes, synonyms, stop words, dictionary, typo tolerance, hybrid prefs, and available field trees).
- **AJAX** `update_index_settings` to persist settings back to Meilisearch and save a local backup option.
- **AJAX** `list_embedders` / `save_embedder` / `delete_embedder` for per-index embedder definitions (admin key stays server-side).

Security is enforced with:

- a per-action **nonce**
- **`manage_options`** capability checks
- server-side sanitization and allowlists/validators for sensitive arrays (e.g. ranking rules)

Local settings backups include: `ranking_rules`, `searchable_attributes`, `synonyms`, `stop_words`, `filterable_attributes`, `dictionary`, `typo_tolerance`, `hybrid` (enabled/embedder/ratio plus stored embedder defs for restore).

### Index create vs restore (embedders)

On admin load, the indexes feature ensures each configured post-type index exists. **WordPress-backed settings (and `scry_ms_index_settings_restore` / `scry_ms_after_create_index`) run only when an index was just created** — not on every page load.

Embedder definitions are stored in the per-index backup and **restored onto newly created indexes**. Re-PATCHing all settings on every admin load kept Meilisearch busy and left the Indexes UI stuck on “Indexing…”.

## Matched-term highlighting (`scry_ms_highlighting`)

The highlighting feature (`features/highlighting/feature.php`) is optional and **disabled by default**. Settings live under **Scry Search → Search Settings**:

- **Enable Highlighting**: toggle (`enable_highlighting`)
- **Highlight CSS**: optional custom CSS for `.scry-ms-highlight` (HTML stripped on save)

When enabled it:

1. Filters `scry_ms_multi_search_query` to request Meilisearch highlights on `post_title` and `post_excerpt` (`<mark class="scry-ms-highlight">…</mark>`).
2. Captures `_formatted` fields from `scry_ms_multi_search_raw_results` into a request-scoped map keyed by post ID.
3. Applies sanitized highlighted title/excerpt onto cloned posts in `scry_ms_multi_search_final_results`.

`sanitize_highlighted_text()` is **public** so other features (autosuggest) can reuse the same allowlist (`<mark>` only). Front-end styles enqueue on search results pages, and also when autosuggest is enabled.

## Analytics: `search_metadata`

Analytics schema version **1.2** stores a `search_metadata` JSON column (renamed from the short-lived `extras` column).

**Core hybrid tracking:** when a federated search uses hybrid on any index, analytics adds a `scry_search_hybrid` key (embedder + semantic ratio per index) via `scry_ms_analytics_event_to_insert`.

**Premium / custom fields:** add a top-level key named for your plugin (e.g. `scry_search_filters`):
- After the filter, any keys that are not table columns are packed into `search_metadata` and stored with the row.
- You may also set `search_metadata` as an array (or JSON string); it is merged with packed plugin keys.
- CSV export includes the `search_metadata` column.

See [`DOCS.md`](DOCS.md) for the full contract on that filter.

## Front-end runtime: `window.scrySearch`

The front-end “window layer” provides a small runtime other features can build on:

- `window.scrySearch.init()` discovers search forms from `windowLocalized.searchFormSelectors` (CSS selectors that must match `<form>` tags) and constructs `ScrySearch_SearchForm` instances.
- Defaults include `#adminbarsearch` and `form[role="search"]`. Add more via the `scry_ms_window_localized` filter (`searchFormSelectors` array) — e.g. `form.my-site-search` or `form.wp-block-search`.
- Once ready, it emits **`document.dispatchEvent(new CustomEvent('scrySearchReady', ...))`** so features can attach behavior without worrying about load order.
- Features can also call:
  - `window.scrySearch.getSearchForms()`
  - `window.scrySearch.getSearchFormsByClass(className)` — used by autosuggest when a class is set under Search Settings

See **Form discovery** in [`DOCS.md`](DOCS.md) for PHP/JS examples.

### `ScrySearch_SearchForm` action pipeline

Each `ScrySearch_SearchForm` instance maintains ordered action lists:

- `preSubmitActions` / `postSubmitActions` (traditional form submit; page navigation likely occurs)
- `preSubmitAjaxActions` / `postSubmitAjaxActions` (AJAX submit; used by autosuggest and similar features)

Actions are instances of `ScrySearch_SubmitAction` and receive:

- `(searchForm)` for pre-actions
- `(searchForm, data)` for post-AJAX actions (where `data` is the parsed JSON response)

### AJAX submit mechanics (autosuggest, etc.)

`searchForm.submitAjax()`:

- is **debounced** to avoid overwhelming the server during rapid typing
- serializes native `<form>` inputs via `FormData` into a JSON-able object (supports bracket syntax like `filters[facets][]`)
- POSTs JSON to the autosuggest REST endpoint (see `features/autosuggest/feature.php`)
- dispatches post-AJAX actions with the returned JSON response

## Autosuggest feature (high level)

Autosuggest attaches after `scrySearchReady`:

- If Search Settings has a class selector, it uses `getSearchFormsByClass(className)`; otherwise it uses every discovered form
- For each form, it registers post-AJAX actions to:
  - persist results to `searchForm.data.core.autosuggestResults`
  - render a dropdown UI under the form
- On each input event (after a small minimum length), it calls `await searchForm.submitAjax()`

To target a custom form: add a CSS class on the `<form>`, include a matching selector in `searchFormSelectors` (via `scry_ms_window_localized` if needed), then set that same class name in the autosuggest class selector setting.

REST payload per hit includes `title`, `excerpt`, `url`, and `featured_image` (thumbnail URL when the post has one). Titles/excerpts are sanitized through the highlighting feature’s allowlist so matched-term `<mark>` tags survive when highlighting is enabled; the dropdown renders a thumbnail when `featured_image` is present.

## Premium Upgrades page

**Scry Search → Premium Upgrades** lists companion add-ons (e.g. **Scry Search Filters**). Hybrid/semantic search is included in the base plugin as of 1.5.0. The catalog is filterable via `scry_ms_premium_upgrades_display` so installed premium plugins can mark themselves active and expose settings UI.

## Local development notes

- This plugin expects a reachable Meilisearch instance and valid keys configured in wp-admin.
- The admin UI is designed to function even when Meilisearch settings fetch fails (defaults are shown where possible).

## License

GPL v3: see [GPL v3](https://www.gnu.org/licenses/gpl-3.0.html).
