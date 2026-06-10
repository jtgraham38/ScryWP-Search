# Scry Search for Meilisearch (Developer Guide)

This README is aimed at contributors. It explains the plugin’s structure, naming conventions, public hooks, and the front-end `window.scrySearch` runtime used by features like autosuggest.

> **Extending the plugin?** See **[`DOCS.md`](DOCS.md)** for the complete, public extension reference: every PHP action/filter hook (type, arguments, return value, when it fires) and the full `window.scrySearch` JavaScript API. This README covers internal architecture and conventions; `DOCS.md` is the contract third‑party developers build against.

## Repo layout

- `scry_search.php`: plugin bootstrap (loads features, shared config, vendor/autoload, etc.).
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
| Document/index shaping | `features/indexes/feature.php` | `scry_ms_index_prepare_document`, `scry_ms_index_fields`, `scry_ms_index_meta_keys`, `scry_ms_index_searchable_attributes_before_update` |
| Index settings flow | `features/indexes/feature.php` | `scry_ms_index_settings_ajax`, `scry_ms_index_settings_backup`, `scry_ms_index_*_before_update`, `scry_ms_index_update_settings` (action) |
| Federated search | `features/search/feature.php` | `scry_ms_multi_search_index_names`, `scry_ms_multi_search_query_params`, `scry_ms_multi_search_query`, `scry_ms_multi_search_raw_results`, `scry_ms_multi_search_final_results` |
| Autosuggest | `features/autosuggest/feature.php` | `scry_ms_autosuggest_query` |
| Analytics | `features/analytics/feature.php` | `scry_ms_analytics_event_to_insert` |
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

Index settings are configured per-post-type (per Meilisearch index). The “Configure Index” dialog uses:

- **AJAX** `get_index_settings` to fetch current settings from Meilisearch (ranking rules, searchable attributes, synonyms, stop words, and available fields).
- **AJAX** `update_index_settings` to persist settings back to Meilisearch and save a local backup option.

Security is enforced with:

- a per-action **nonce**
- **`manage_options`** capability checks
- server-side sanitization and allowlists/validators for sensitive arrays (e.g. ranking rules)

## Front-end runtime: `window.scrySearch`

The front-end “window layer” provides a small runtime other features can build on:

- `window.scrySearch.init()` discovers search forms on the page and constructs `ScrySearch_SearchForm` instances.
- Once ready, it emits **`document.dispatchEvent(new CustomEvent('scrySearchReady', ...))`** so features can attach behavior without worrying about load order.
- Features can also call:
  - `window.scrySearch.getSearchForms()`
  - `window.scrySearch.getSearchFormsByClass(className)`

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

Autosuggest attaches to search inputs after `scrySearchReady`:

- For each detected search form, it registers post-AJAX actions to:
  - persist results to `searchForm.data.core.autosuggestResults`
  - render a dropdown UI under the form
- On each input event (after a small minimum length), it calls `await searchForm.submitAjax()`

## Local development notes

- This plugin expects a reachable Meilisearch instance and valid keys configured in wp-admin.
- The admin UI is designed to function even when Meilisearch settings fetch fails (defaults are shown where possible).

## License

GPL v3: see [GPL v3](https://www.gnu.org/licenses/gpl-3.0.html).
