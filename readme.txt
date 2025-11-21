=== Scry Search for Meilisearch ===
Contributors: JG Web Development
Tags: search, meilisearch, fast-search, typo-tolerant, search-engine, relevancy, indexing, multi-post-type
Requires at least: 5.2
Tested up to: 6.8
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Transform your WordPress site's search experience with lightning-fast, typo-tolerant search powered by Meilisearch.

== Description ==

Scry Search replaces WordPress's default search functionality with Meilisearch, a powerful open-source search engine that delivers instant, relevant results. Say goodbye to slow, inaccurate searches and hello to a professional search experience that your users will love.

= Key Features =

* **Blazing Fast Search**: Instant search results, even on large sites with thousands of posts. Typo-tolerant search that finds results even when users make spelling mistakes.

* **Highly Customizable Relevancy**: 
  * Drag-and-drop interface to reorder ranking rules (words, typo, proximity, attribute, sort, exactness) for each index
  * Choose exactly which fields are searchable, including custom post meta fields
  * Assign different importance weights to different post types for federated search results

* **Multi-Post-Type Support**: Index multiple post types independently with separate search settings for each. Perfect for sites with posts, pages, products, custom post types, and more.

* **Easy Management**:
  * One-click re-indexing for any post type
  * View index statistics, document counts, and indexing status
  * Live search preview directly from the admin panel
  * Automatic indexing when posts are created or updated

* **Seamless Integration**: Drop-in replacement for WordPress search - no theme changes required. Works with existing WordPress search forms and queries.

= Why Choose Scry Search for Meilisearch? =

**For Site Managers:**
* Better user experience - users find what they're looking for faster
* Professional search capabilities without enterprise costs
* Simple admin interface for managing search settings
* Scalable - handles sites with thousands of posts without performance degradation

**For Developers:**
* Well-structured, maintainable codebase
* Extensible with hooks and filters
* Follows WordPress coding standards and security best practices

== Installation ==

1. **Install Meilisearch**: You'll need a Meilisearch instance running. You can use Meilisearch Cloud (recommended for production), self-host Meilisearch on your server, or run Meilisearch locally for development.

2. **Install the Plugin**: Upload the plugin files to `/wp-content/plugins/scry-search-ms/` directory, or install the plugin through the WordPress plugins screen directly.

3. **Activate the Plugin**: Activate the plugin through the 'Plugins' screen in WordPress.

4. **Configure Connection**: Navigate to Scry Search for Meilisearch > Connection Settings and enter your Meilisearch URL and API keys.

5. **Set Up Indexes**: Go to Scry Search > Indexes, select which post types you want to index, and click "Index Posts" for each post type.

== Frequently Asked Questions ==

= Do I need to modify my theme? =
No! Scry Search for Meilisearch is a drop-in replacement for WordPress search. It works with your existing search forms and queries without any theme modifications.

= What is Meilisearch? =
Meilisearch is an open-source, fast, and typo-tolerant search engine. You can host it yourself or use Meilisearch Cloud. Visit https://www.meilisearch.com/ to learn more.

= Can I customize which fields are searchable? =
Yes! For each post type index, you can configure exactly which fields are searchable, including custom post meta fields. This gives you complete control over what content is searchable.

= Can I customize the ranking/relevancy? =
Absolutely! You can reorder the built-in ranking rules (words, typo, proximity, attribute, sort, exactness) using a drag-and-drop interface. You can also assign search weights to different post types.

= Will this work with WooCommerce/other plugins? =
Yes! Scry Search for Meilisearch works with any post type, including WooCommerce products and custom post types created by other plugins. Simply select the post types you want to index.

= How do I re-index my content? =
Go to Scry Search > Indexes, find the post type you want to re-index, and click the "Index Posts" button. You can also wipe and rebuild an index if needed.

= Is this plugin secure? =
Yes. The plugin follows WordPress security best practices, uses nonces for all AJAX requests, checks user capabilities, and sanitizes all user input.

== Screenshots ==

1. Index Management Dashboard - View and manage all your search indexes
2. Index Configuration Modal - Customize ranking rules and searchable fields
3. Connection Settings - Configure your Meilisearch connection
4. Search Preview - Test your search queries directly from the admin panel

== Changelog ==

= 1.0.0 =
* Initial release
* Meilisearch integration
* Multi-post-type indexing
* Customizable ranking rules
* Searchable fields configuration
* Search weights for federated search
* Automatic post indexing
* Admin dashboard for index management
* Live search preview

== Upgrade Notice ==

= 1.0.0 =
Initial release. Install and configure your Meilisearch connection to get started.

== Requirements ==

* WordPress 5.2 or higher
* PHP 7.2 or higher
* Meilisearch instance (self-hosted or cloud)

== Support ==

For support, feature requests, or bug reports, please visit the plugin repository or contact JG Web Development at https://jacob-t-graham.com.
