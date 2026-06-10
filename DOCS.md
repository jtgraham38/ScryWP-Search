# Scry Search for Meilisearch — Developer Hooks & JavaScript API

This document is the public extension reference for **Scry Search for Meilisearch**. It covers:

1. [PHP filters](#php-filters) — change values as they flow through the plugin.
2. [PHP actions](#php-actions) — run side effects at key moments.
3. [JavaScript API](#javascript-api) — the `window.scrySearch` runtime, search‑form action pipeline, and front‑end customization points.

It documents the **contract** of each extension point (purpose, type, arguments, return value, and when it runs). It intentionally does not describe the plugin's internal implementation.

---

## Hook naming

Every PHP hook in this plugin is namespaced with the prefix:

```
scry_ms_
```

So a hook documented below as `index_prepare_document` is registered in WordPress as `scry_ms_index_prepare_document`.

```php
add_filter( 'scry_ms_index_prepare_document', 'my_callback' );
```

> All hook names below are shown **with** their full, literal `scry_ms_` prefix so you can copy them directly.

A quick reminder of the WordPress conventions used throughout:

- **Filters** receive a value and **must return a value** (modified or not).
- **Actions** return nothing; they are for side effects.
- If your callback wants more than the first argument, declare the argument count in the 4th parameter of `add_filter()` / `add_action()`.

---

## PHP Filters

### Indexing & document shaping

#### `scry_ms_index_prepare_document`
Modify a single post's document just before it is sent to Meilisearch.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $document` |
| **Returns** | `array` — the (possibly modified) document |
| **When** | Each time a post is prepared for indexing (single save and bulk indexing). |

```php
add_filter( 'scry_ms_index_prepare_document', function ( $document ) {
    $document['reading_time'] = my_estimate_reading_time( $document['post_content'] ?? '' );
    return $document;
} );
```

A document is an associative array keyed by attribute name (for example `ID`, `post_title`, `post_content`, `post_excerpt`, `post_author`, `permalink`, and `post_meta`). Add, remove, or rewrite keys as needed; whatever you return is what gets indexed.

---

#### `scry_ms_index_ranking_rules`
Change the default Meilisearch ranking rules.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $ranking_rules` (ordered list of rule names) |
| **Returns** | `array` — the ranking rules |
| **When** | When default ranking rules are resolved. |

Order matters: rules are applied top to bottom.

---

#### `scry_ms_index_fields`
Modify the list of fields offered for a post type in the index configuration UI.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $fields`, `string $post_type` |
| **Returns** | `array` — the fields map |
| **When** | When the configuration UI builds the field list for a post type. |

Each entry is keyed by field path and contains at least `label`, `type`, and `path`.

---

#### `scry_ms_index_meta_keys`
Modify the list of post‑meta keys discovered for a post type.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $meta_keys` (list of meta key strings), `string $post_type` |
| **Returns** | `array` — the meta key list |
| **When** | When meta keys are gathered for a post type (used to build the field list). |

Use this to expose meta keys that wouldn't otherwise be discovered, or to hide private ones.

---

### Index settings flow (per‑index configuration)

#### `scry_ms_index_settings_ajax`
Add or adjust the data returned to the index settings screen.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $return_array`, `string $index_name` |
| **Returns** | `array` — the response payload |
| **When** | When current settings for an index are fetched for display. |

The payload includes keys such as `ranking_rules`, `searchable_attributes`, `available_fields`, `synonyms`, and `stop_words`. Add your own keys to surface custom data in companion UI.

---

#### `scry_ms_index_settings_backup`
Modify the settings payload that is persisted locally when an index is saved.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $index_settings_backup`, `string $index_name` |
| **Returns** | `array` — the payload to persist |
| **When** | Right before the settings backup is saved. |

`$index_settings_backup` contains: `ranking_rules`, `searchable_attributes`, `synonyms`, `stop_words`.

---

The following four filters fire **immediately before** each setting group is applied to Meilisearch. Each receives the index name as a second argument and must return the (possibly modified) value.

#### `scry_ms_index_ranking_rules_before_update`
| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $ranking_rules`, `string $index_name` |
| **Returns** | `array` |

#### `scry_ms_index_searchable_attributes_before_update`
| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $searchable_attributes`, `string $index_name` |
| **Returns** | `array` |
| **When** | Before searchable attributes are applied to an index — both when settings are saved and when an index's searchable attributes are configured (e.g. on index creation). |

```php
add_filter( 'scry_ms_index_searchable_attributes_before_update', function ( $attributes, $index_name ) {
    $attributes[] = 'reading_time';
    return $attributes;
}, 10, 2 );
```

#### `scry_ms_index_synonyms_before_update`
| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $synonyms`, `string $index_name` |
| **Returns** | `array` |

`$synonyms` is a map of `base term => array of synonym terms`. An empty array clears synonyms for the index.

#### `scry_ms_index_stop_words_before_update`
| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $stop_words`, `string $index_name` |
| **Returns** | `array` |

`$stop_words` is a flat list of words. An empty array clears stop words for the index.

---

### Search query (front‑end search interception)

These filters let you shape how a front‑end search is translated into a federated Meilisearch query.

#### `scry_ms_multi_search_index_names`
Choose which indexes a search runs against.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $index_names_to_search` (map of `post_type => index name`), `array $post_types_to_search` |
| **Returns** | `array` — the index map |
| **When** | After the target indexes are resolved, before queries are built. |

---

#### `scry_ms_multi_search_query_params`
Adjust high‑level query parameters.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $query_params`, `WP_Query $query` |
| **Returns** | `array` — the query params |
| **When** | After the base params are assembled. |

`$query_params` may include keys like `q` (search term), `limit`, and `offset`.

---

#### `scry_ms_multi_search_query`
Modify the per‑index search query object.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `Meilisearch\Contracts\SearchQuery $search_query`, `string $index_name` |
| **Returns** | `Meilisearch\Contracts\SearchQuery` |
| **When** | Once per index, as each query is constructed. |

---

#### `scry_ms_multi_search_queries`
Modify the full set of per‑index queries before they are executed.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $search_queries` (list of `SearchQuery`) |
| **Returns** | `array` |

---

#### `scry_ms_multi_search_federation`
Modify the federation options for the multi‑search request.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `Meilisearch\Contracts\MultiSearchFederation $federation` |
| **Returns** | `Meilisearch\Contracts\MultiSearchFederation` |
| **When** | After limit/offset are set, before the request runs. |

---

#### `scry_ms_multi_search_raw_results`
Inspect or rewrite the raw hits and total count returned by Meilisearch.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $all_results`, `int $total_hits` |
| **Returns** | `array` — a **two‑element** array: `[ array $all_results, int $total_hits ]` |
| **When** | Immediately after the search response is received. |

> **Important:** this filter is destructured into two variables. Your callback **must return a two‑element array** in the order `[ $all_results, $total_hits ]`, even if you only change one of them.

```php
add_filter( 'scry_ms_multi_search_raw_results', function ( $all_results, $total_hits ) {
    // ... inspect / filter $all_results ...
    return array( $all_results, $total_hits );
}, 10, 2 );
```

---

#### `scry_ms_multi_search_final_results`
Modify the final array of posts returned to WordPress.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $posts_array` (list of `WP_Post`) |
| **Returns** | `array` |
| **When** | After hits are resolved into post objects, before they are handed back to the query. |

---

### Autosuggest

#### `scry_ms_autosuggest_query`
Modify the query used to build autosuggest results.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $autosuggest_query` (WP_Query args) |
| **Returns** | `array` |
| **When** | Before the autosuggest query runs. |

The array contains standard `WP_Query` arguments such as `s`, `post_type`, `posts_per_page`, and `no_found_rows`.

---

### Analytics

#### `scry_ms_analytics_event_to_insert`
Modify a search analytics record before it is stored.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $event_to_insert` |
| **Returns** | `array` — the record to store |
| **When** | Before each analytics event is written. |

Keys include: `search_term`, `user_id`, `user_ip`, `user_agent`, `referrer`, `result_count`, `result_ids`, `result_titles`, `post_types_searched`. Use this to add fields or to further anonymize data.

---

### Logs

#### `scry_ms_log_message`
Modify a log message before it is stored.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `string $message`, `string $level` |
| **Returns** | `string` — the message to store |
| **When** | Before each log entry is written. |

`$level` is one of `debug` or `error`. Return the message string (modified or unchanged).

---

### Front‑end window localization

#### `scry_ms_window_localized`
Modify the data passed from PHP to the front‑end `window.localized` object used by the JavaScript runtime.

| | |
|---|---|
| **Type** | Filter |
| **Arguments** | `array $window_localized` |
| **Returns** | `array` |
| **When** | When the front‑end window script is enqueued. |

Default keys include `restApiUrl` and `autoSuggestEnabled`. Add keys here to expose configuration to your own front‑end code.

---

## PHP Actions

#### `scry_ms_after_create_index`
Fires immediately after a new index is created.

| | |
|---|---|
| **Type** | Action |
| **Arguments** | `object $index` (Meilisearch index handle) |
| **When** | Right after a missing index is created. |

Use it to apply additional index configuration on creation.

---

#### `scry_ms_index_settings_restore`
Fires after a saved settings backup is re‑applied to an index.

| | |
|---|---|
| **Type** | Action |
| **Arguments** | `object $index` (Meilisearch index handle), `array $index_settings_backup` |
| **When** | After previously saved settings are restored onto an index. |

`$index_settings_backup` has the same shape as the `scry_ms_index_settings_backup` filter (`ranking_rules`, `searchable_attributes`, `synonyms`, `stop_words`).

---

#### `scry_ms_index_update_settings`
Fires after index settings have been successfully applied to Meilisearch.

| | |
|---|---|
| **Type** | Action |
| **Arguments** | `object $index` (Meilisearch index handle) |
| **When** | After a settings save completes. |

---

#### `scry_ms_index_settings_sections_ui`
Renders inside the per‑index settings dialog, allowing you to output custom settings UI.

| | |
|---|---|
| **Type** | Action |
| **Arguments** | `array $index` (display data for the current index) |
| **When** | While the index settings dialog is rendered. |

Echo your own markup from the callback. `$index` includes display fields such as `name` (post type) and `index_name`.

---

## JavaScript API

The plugin ships a small front‑end runtime, `window.scrySearch`, that discovers search forms on the page and gives you ordered hooks to run code before/after a search — both for normal submits and AJAX submits (used by autosuggest and similar features).

The script is enqueued on the front end. Other scripts can declare it as a dependency using the handle `scry_ms_window-script`.

### Readiness: the `scrySearchReady` event

The runtime initializes on `DOMContentLoaded`, finds the search forms, and then dispatches a `scrySearchReady` event on `document`. **Always wait for this event** before touching the API, so you don't depend on script load order.

```js
document.addEventListener('scrySearchReady', function (e) {
    const { version, searchForms, upgrades } = e.detail;
    // searchForms is an array of ScrySearch_SearchForm instances
});
```

### `window.scrySearch`

| Member | Type | Description |
|---|---|---|
| `version` | `string` | Runtime version. |
| `getSearchForms()` | `() => ScrySearch_SearchForm[]` | All detected search forms. |
| `getSearchFormsByClass(className)` | `(string) => ScrySearch_SearchForm[]` | Search forms whose `<form>` has the given CSS class. |
| `registerUpgrade(name, version)` | `(string, string) => void` | Register a named extension namespace (see [Upgrades](#upgrades)). |

A "search form" is any `<form>` on the page with `role="search"` or that contains a text/search input named `s`.

### `ScrySearch_SearchForm`

Each detected form is wrapped in a `ScrySearch_SearchForm` instance.

| Member | Type | Description |
|---|---|---|
| `formElement` | `HTMLFormElement` | The underlying `<form>`. |
| `searchInput` | `HTMLInputElement` | The detected search input (`name="s"`). |
| `data` | `object` | Arbitrary per‑form storage. Use it to stash state (e.g. `data.core` is used internally by autosuggest). |
| `submit()` | `() => void` | Runs pre‑submit actions, performs a normal form submit, then runs post‑submit actions. |
| `submitAjax()` | `() => Promise<any>` | Runs pre‑AJAX actions, submits the form via AJAX, runs post‑AJAX actions, and resolves with the parsed response. **Debounced.** |
| `addPreSubmitAction(fn, order)` | `(fn, number) => void` | Add an action before a normal submit. |
| `addPostSubmitAction(fn, order)` | `(fn, number) => void` | Add an action after a normal submit. |
| `addPreSubmitAjaxAction(fn, order)` | `(fn, number) => void` | Add an action before an AJAX submit. |
| `addPostSubmitAjaxAction(fn, order)` | `(fn, number) => void` | Add an action after an AJAX submit. |

### The pre/post submit action pipeline

Each form maintains four ordered lists of actions:

- **`preSubmitActions`** — run before a normal (page‑navigating) submit.
- **`postSubmitActions`** — run after a normal submit (often won't run, since the page navigates away).
- **`preSubmitAjaxActions`** — run before an AJAX submit.
- **`postSubmitAjaxActions`** — run after an AJAX submit, once the response is available.

Actions run in ascending `order` (lowest first). Use `order` to position your action relative to others (e.g. built‑in autosuggest actions).

**Callback signatures:**

- Pre actions and normal post actions receive the form: `function (searchForm) { … }`
- Post‑**AJAX** actions also receive the response data: `function (searchForm, data) { … }`

> `data` is the parsed JSON response from the AJAX submit. (Both arguments are always passed for post‑AJAX actions, so falsy values like `0`, `""`, or `null` are preserved rather than dropped.)

The same callback (by function reference) won't be added twice to the same list.

#### Example: react to AJAX search results

```js
document.addEventListener('scrySearchReady', function () {
    window.scrySearch.getSearchForms().forEach(function (form) {
        // Run before the AJAX request fires.
        form.addPreSubmitAjaxAction(function (searchForm) {
            searchForm.formElement.classList.add('is-searching');
        }, 10);

        // Run after the response comes back.
        form.addPostSubmitAjaxAction(function (searchForm, data) {
            searchForm.formElement.classList.remove('is-searching');
            console.log('Results:', data);
        }, 10);
    });
});
```

#### Example: target only forms with a specific class

```js
document.addEventListener('scrySearchReady', function () {
    window.scrySearch.getSearchFormsByClass('my-search').forEach(function (form) {
        form.addPreSubmitAction(function (searchForm) {
            // e.g. add a hidden input before a normal submit
        }, 5);
    });
});
```

#### Triggering an AJAX search yourself

`submitAjax()` is debounced and returns a promise, so you can call it directly and await the result:

```js
const [form] = window.scrySearch.getSearchForms();
const data = await form.submitAjax();
```

### Upgrades

`registerUpgrade(name, version)` reserves a namespace on `window.scrySearch.upgrades[name]` (a `ScrySearch_Upgrade` with `name`, `version`, and a `data` object) for larger add‑ons to store their own state. Registering the same name twice is ignored.

```js
window.scrySearch.registerUpgrade('my_addon', '1.0.0');
window.scrySearch.upgrades.my_addon.data.enabled = true;
```

### Front‑end configuration via `window.localized`

PHP exposes configuration to the runtime through a `localized` object (modifiable with the [`scry_ms_window_localized`](#scry_ms_window_localized) filter). Default keys include `restApiUrl` and `autoSuggestEnabled`. Add your own keys server‑side to read them in your front‑end code.
