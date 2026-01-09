=== Scry Search for Meilisearch ===
Contributors: JG Web Development, jtgraham38
Tags: meilisearch, search, wordpress search, meilisearch for wordpress, fast search
Requires at least: 5.2
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

The ultimate Meilisearch for WordPress integration. Lightning-fast, typo-tolerant search with zero frontend changes required.

== Description ==

**Scry Search for Meilisearch** is the most seamless way to add **Meilisearch for WordPress** to your site. Replace WordPress's slow, limited default search with the blazing-fast, typo-tolerant power of Meilisearch—without touching a single line of theme code or modifying your frontend.

= The Best Meilisearch for WordPress Integration =

Whether you're running a blog, an eCommerce store, a membership site, or a complex multi-post-type WordPress installation, Scry Search delivers enterprise-grade search performance with minimal setup. Use **[ScryWP Search](https://scrywp.com)** for a fully managed cloud experience, or **self-host Meilisearch** on your own infrastructure—the choice is yours.

= Zero Frontend Changes Required =

Unlike other search plugins, Scry Search is a true **drop-in replacement** for WordPress search. Your existing search forms, search widgets, and theme search templates continue to work exactly as before. The plugin intercepts WordPress search queries and routes them through Meilisearch, then returns results in the format WordPress expects. **No theme modifications, no shortcode replacements, no widget swaps**—just activate, configure, and enjoy instant search.

= Per-Post-Type Indexes with Federated Search =

Index **any WordPress post type** independently—posts, pages, WooCommerce products, custom post types from any plugin, or your own custom content types. Each post type gets its own dedicated Meilisearch index, giving you granular control over:

* **Searchable Fields**: Choose exactly which fields are searchable for each post type, including custom post meta fields
* **Ranking Rules**: Drag-and-drop interface to reorder Meilisearch's ranking rules (words, typo, proximity, attribute, sort, exactness) per index
* **Search Weights**: Assign different importance weights to different post types so products can rank higher than blog posts, or vice versa

When users search, Scry Search uses **federated multi-search** to query all relevant indexes simultaneously and merge results intelligently based on your configured weights.

= Customizable Relevancy & Ranking =

Fine-tune how Meilisearch ranks your search results:

* **Reorder Ranking Rules**: Use the intuitive drag-and-drop interface to prioritize typo tolerance over word proximity, or exactness over attribute order
* **Searchable Attributes Control**: Decide which fields matter for search—include post titles, content, excerpts, categories, tags, author names, and any custom meta fields
* **Post Type Weighting**: Running a store? Weight products higher than blog posts. Running a knowledge base? Prioritize documentation over news articles.

= Built-In Task Monitor & Debugging =

The fully-featured **Task Drawer** gives you complete visibility into your Meilisearch operations:

* **Real-Time Task Tracking**: View all indexing tasks with status, duration, and error details
* **Paginated Task History**: Browse through your complete task history with easy navigation
* **Error Diagnosis**: Quickly identify and troubleshoot failed indexing operations
* **Performance Monitoring**: Track task durations to optimize your indexing strategy

= Automatic & Manual Indexing =

* **Automatic Indexing**: Posts are automatically indexed when created, updated, or trashed—your search index stays in sync without any manual intervention
* **One-Click Bulk Indexing**: Re-index all posts of any type with a single click
* **Wipe & Rebuild**: Clear an index and start fresh when needed
* **Live Search Preview**: Test your search queries directly from the admin panel before going live

= Easy Meilisearch Integration =

Getting started with Meilisearch for WordPress has never been easier:

1. **[ScryWP Search](https://scrywp.com)** (Recommended): Use our fully managed cloud-based Meilisearch hosting designed specifically for WordPress
2. **Self-Hosted**: Run Meilisearch on your own server with full control over your data
3. **Local Development**: Spin up Meilisearch locally for development and testing

Simply enter your Meilisearch URL and API keys, select which post types to index, and you're live in minutes.

= Works With Your Existing WordPress Setup =

Scry Search respects WordPress conventions and integrates seamlessly with:

* **Theme Search Templates**: Your `search.php` and `searchform.php` templates work unchanged
* **Search Widgets**: Standard WordPress search widgets continue functioning
* **Block Editor Search Blocks**: Gutenberg search blocks work out of the box
* **Page Builders**: Elementor, Divi, Beaver Builder search elements work seamlessly
* **WooCommerce**: Index and search products with full support for product meta
* **Custom Post Types**: Any registered post type can be indexed and searched

== Installation ==

1. **Set Up Meilisearch**: You'll need a running Meilisearch instance:
   * [ScryWP Search](https://scrywp.com) - Our managed cloud hosting (recommended for production)
   * Self-host on your server using Docker, binaries, or package managers
   * Run locally for development

2. **Install the Plugin**: Upload the plugin files to `/wp-content/plugins/scry-search-meilisearch/` or install directly through the WordPress plugins screen.

3. **Activate**: Activate the plugin through the 'Plugins' screen in WordPress.

4. **Configure Connection**: Navigate to **Scry Search > Connection Settings** and enter your Meilisearch URL, Admin API Key, and optionally a Search API Key.

5. **Create Indexes**: Go to **Scry Search > Index Settings**, select which post types to index, save, then click "Index Posts" for each post type.

6. **Search!**: Your WordPress search is now powered by Meilisearch. No frontend changes needed.

== Frequently Asked Questions ==

= What makes this the best Meilisearch for WordPress plugin? =

Scry Search offers the most complete integration: per-post-type indexes with independent settings, federated search with configurable weights, full control over ranking rules and searchable fields, a built-in task monitor for debugging, and zero frontend modifications required.

= Do I need to modify my theme? =

No! Scry Search is a true drop-in replacement. Your existing search forms, search templates, and search widgets continue working exactly as before. Just activate, configure, and enjoy.

= What is Meilisearch? =

Meilisearch is an open-source, lightning-fast, typo-tolerant search engine. It's designed for speed and relevance, making it perfect for WordPress sites that need better search than the default. Learn more at [meilisearch.com](https://www.meilisearch.com/).

= Should I use ScryWP Search or self-host? =

**[ScryWP Search](https://scrywp.com)** is recommended for most users—it's fully managed cloud hosting designed for WordPress, with automatic scaling and zero server maintenance. **Self-hosting** is ideal if you need complete data control, have specific compliance requirements, or want to minimize costs at scale.

= Can I index custom post types? =

Absolutely! Scry Search works with any registered WordPress post type. Select the post types you want in Index Settings, and each gets its own dedicated Meilisearch index with independent configuration.

= Can I search custom meta fields? =

Yes! When configuring searchable fields for an index, you can select from all available post meta keys. This includes custom fields from plugins like ACF, Meta Box, and any other custom meta.

= How does federated search work? =

When searching across multiple post types, Scry Search queries all relevant indexes simultaneously using Meilisearch's federated multi-search. Results are merged and ranked according to the weights you've assigned to each post type.

= How do I debug indexing issues? =

Use the built-in Task Drawer (accessible from any plugin admin page) to view all Meilisearch tasks. You can see status, duration, error details, and browse your complete task history with pagination.

= Will this work with WooCommerce? =

Yes! WooCommerce products are just another post type. Select "product" in your Index Settings, configure which product fields and meta to search, and your product search is instantly upgraded.

= Is this plugin secure? =

Yes. Scry Search follows WordPress security best practices: all AJAX requests use nonces, user capabilities are checked on every action, and all input is properly sanitized and escaped.

== Screenshots ==

1. Index Settings Dashboard - Manage post type indexes, view document counts, and trigger indexing operations
2. Index Configuration Modal - Drag-and-drop ranking rules, configure searchable fields with post meta support
3. Connection Settings - Configure Meilisearch URL and API keys with connection testing
4. Search Settings - Configure post type search weights for federated search
5. Task Drawer - Monitor Meilisearch tasks with status, timing, and error details
6. Live Search Preview - Test search queries directly from the admin panel

== Changelog ==

= 1.0.0 =
* Initial release
* Full Meilisearch integration with support for managed cloud and self-hosted instances
* Per-post-type indexing with independent index configuration
* Federated search across multiple post types with configurable weights
* Drag-and-drop ranking rule customization per index
* Searchable fields configuration including custom post meta
* Automatic indexing on post create, update, and trash
* One-click bulk indexing and index wipe/rebuild
* Built-in Task Drawer for monitoring and debugging
* Live search preview in admin panel
* Zero frontend modifications required - true drop-in replacement
* Full compatibility with existing WordPress search forms and templates

== Upgrade Notice ==

= 1.0.0 =
Initial release. Install, connect to your Meilisearch instance, and transform your WordPress search experience.

== Requirements ==

* WordPress 5.2 or higher
* PHP 8.1 or higher
* Meilisearch instance (ScryWP Search managed hosting or self-hosted)

== Support ==

For support, feature requests, or bug reports, please visit the [plugin repository](https://github.com/jtgraham38/ScryWP-Search) or contact [JG Web Development](https://jacob-t-graham.com).
