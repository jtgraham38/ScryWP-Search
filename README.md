# Scry Search for Meilisearch (Developer Guide)

This README is aimed at contributors. It explains the plugin’s structure, naming conventions, public hooks, and the front-end `window.scrySearch` runtime used by features like autosuggest.

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

This plugin intentionally exposes a few extension points. When adding new ones, follow the “Naming conventions” above and document them here.

### Indexing hooks

- **`{$hook_prefix}index_prepare_document`** (filter)  
  Modify a document *right before* it is sent to Meilisearch.  
  Located in `features/indexes/feature.php` inside `format_post_for_meilisearch()`.

- **`{$hook_prefix}index_settings_backup`** (filter)  
  Modify the array persisted to the per-index “settings backup” option before it is saved.  
  Located in `features/indexes/feature.php` inside `ajax_update_index_settings()`.

- **`{$hook_prefix}index_update_settings`** (action)  
  Fires after index settings are successfully applied in Meilisearch.  
  Located in `features/indexes/feature.php` inside `ajax_update_index_settings()`.

### How to add a new hook safely

- **Pick the right type**:
  - Use a **filter** when callers should be able to change a value (`apply_filters`).
  - Use an **action** for notification / side effects (`do_action`).
- **Keep the payload stable**: pass structured arrays/objects with explicit keys rather than positional arguments that are easy to break.
- **Document it** in this README (name, type, when it fires, args).

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
