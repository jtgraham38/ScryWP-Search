# Scry Search for Meilisearch

**The ultimate Meilisearch for WordPress integration. Lightning-fast, typo-tolerant search with zero frontend changes required.**

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![WordPress: 5.2+](https://img.shields.io/badge/WordPress-5.2%2B-blue.svg)](https://wordpress.org/)
[![PHP: 8.1+](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net/)

## Why Meilisearch for WordPress?

WordPress's default search is notoriously slow, inaccurate, and frustrating for users. **Scry Search for Meilisearch** solves this by seamlessly integrating [Meilisearch](https://www.meilisearch.com/)—a lightning-fast, typo-tolerant, open-source search engine—directly into your WordPress site.

The result? **Instant, relevant search results** that help your visitors find exactly what they're looking for, even when they make typos or use imprecise queries.

## Key Features

### 🔌 Easy Integration with Any Meilisearch Instance

Connect to Meilisearch in minutes with flexible hosting options:

- **[ScryWP Search](https://scrywp.com)** — Our fully managed cloud hosting designed specifically for WordPress (recommended for production)
- **Self-Hosted** — Run Meilisearch on your own servers with Docker, binaries, or package managers
- **Local Development** — Spin up Meilisearch locally for testing and development

Simply enter your Meilisearch URL and API keys in the Connection Settings, and you're ready to go.

### 🎯 Zero Frontend Changes Required

Unlike other search plugins that require theme modifications, shortcode replacements, or widget swaps, Scry Search is a **true drop-in replacement** for WordPress search:

- ✅ Existing search forms continue working unchanged
- ✅ Theme search templates (`search.php`, `searchform.php`) work as-is
- ✅ WordPress search widgets function normally
- ✅ Block Editor search blocks work out of the box
- ✅ Page builder search elements (Elementor, Divi, Beaver Builder) work seamlessly

The plugin intercepts WordPress search queries, routes them through Meilisearch, and returns results in the format WordPress expects. **Activate, configure, done.**

### 📊 Per-Post-Type Indexes with Federated Search

Index **any WordPress post type** independently with its own dedicated Meilisearch index:

- **Posts, Pages, Products** — Index standard WordPress content
- **WooCommerce Products** — Full support for product search with meta fields
- **Custom Post Types** — Any registered post type from any plugin or theme

Each index can be configured independently, and when users search, **federated multi-search** queries all relevant indexes simultaneously, merging results intelligently.

### ⚖️ Configurable Search Weights

Control how results from different post types are ranked in federated search:

```
Products: 2.0 (highest priority)
Pages: 1.5 (medium priority)  
Posts: 1.0 (standard priority)
```

Running an eCommerce store? Weight products higher. Knowledge base? Prioritize documentation. You're in control.

### 🎛️ Full Control Over Ranking & Relevancy

Fine-tune how Meilisearch ranks your search results with per-index configuration:

#### Drag-and-Drop Ranking Rules
Reorder Meilisearch's ranking rules to prioritize what matters most:
- **words** — Number of matching words
- **typo** — Fewer typos = higher rank
- **proximity** — Closer matching words = higher rank
- **attribute** — Matches in important fields rank higher
- **sort** — Custom sorting criteria
- **exactness** — Exact matches rank higher

#### Searchable Fields Configuration
Choose exactly which fields are searchable for each post type:
- Core fields: title, content, excerpt, slug, dates
- Taxonomies: categories, tags
- Author information
- **Custom post meta fields** — Including fields from ACF, Meta Box, and more

### 🔧 Built-In Task Monitor & Debugging

The **Task Drawer** provides complete visibility into your Meilisearch operations:

- **Real-Time Status** — View indexing tasks with status indicators (succeeded, processing, failed)
- **Error Details** — Quickly identify and diagnose failed operations
- **Duration Tracking** — Monitor task performance and timing
- **Paginated History** — Browse through your complete task history
- **One-Click Access** — Available from any Scry Search admin page

No more guessing whether your content is indexed—see exactly what's happening in Meilisearch.

### ⚡ Automatic & Manual Indexing

**Automatic Sync:**
- Posts are indexed automatically when created or updated
- Posts are removed from the index when trashed
- Your search index stays current without manual intervention

**Manual Controls:**
- **Index Posts** — One-click bulk indexing for any post type
- **Wipe Index** — Clear and rebuild an index from scratch
- **Live Preview** — Test search queries directly in the admin panel

## Installation

### 1. Set Up Meilisearch

You'll need a running Meilisearch instance. Choose your preferred option:

**ScryWP Search (Recommended):**
```
Sign up at https://scrywp.com
Create a project and get your URL + API keys
Fully managed, designed specifically for WordPress
```

**Self-Hosted with Docker:**
```bash
docker run -d -p 7700:7700 \
  -v $(pwd)/meili_data:/meili_data \
  getmeili/meilisearch:latest \
  meilisearch --master-key="your-master-key"
```

**Self-Hosted Binary:**
```bash
curl -L https://install.meilisearch.com | sh
./meilisearch --master-key="your-master-key"
```

### 2. Install the Plugin

**Via WordPress Admin:**
1. Download the plugin zip file
2. Go to Plugins → Add New → Upload Plugin
3. Upload and activate

**Via FTP/SFTP:**
1. Upload the `scry-search-meilisearch` folder to `/wp-content/plugins/`
2. Activate through the Plugins screen

### 3. Configure Connection

1. Navigate to **Scry Search → Connection Settings**
2. Enter your Meilisearch URL (e.g., `https://your-project.scrywp.com` or `http://localhost:7700`)
3. Enter your Admin API Key (for managing indexes)
4. Optionally enter a Search API Key (for frontend queries)
5. Click "Test Connection" to verify
6. Save settings

### 4. Create Indexes

1. Go to **Scry Search → Index Settings**
2. Check the post types you want to index
3. Save changes
4. Click **Index Posts** for each post type to perform initial indexing

### 5. Configure Search (Optional)

1. Go to **Scry Search → Search Settings**
2. Adjust search weights for each indexed post type
3. Save settings

**That's it!** Your WordPress search is now powered by Meilisearch.

## Configuration Guide

### Connection Settings

| Setting | Description |
|---------|-------------|
| Meilisearch URL | The full URL to your Meilisearch instance |
| Admin API Key | API key with admin permissions for managing indexes |
| Search API Key | (Optional) Read-only key for search operations |

### Index Settings

For each post type index, you can configure:

- **Searchable Attributes**: Which fields are included in search
- **Ranking Rules**: Order of relevancy factors
- **Index Affix**: Custom prefix for index names (useful for staging/production separation)

### Search Settings

| Setting | Description |
|---------|-------------|
| Post Type Weights | Numeric weight (0.0+) for each post type in federated search |

Higher weights mean results from that post type rank higher when searching across multiple types.

## Compatibility

### Themes
- ✅ Any theme with standard WordPress search
- ✅ Block themes (FSE)
- ✅ Classic themes
- ✅ Custom themes

### Page Builders
- ✅ Elementor
- ✅ Divi
- ✅ Beaver Builder
- ✅ WPBakery
- ✅ Bricks

### Plugins
- ✅ WooCommerce
- ✅ Advanced Custom Fields (ACF)
- ✅ Meta Box
- ✅ Custom Post Type UI
- ✅ Any plugin that creates custom post types

### WordPress Features
- ✅ Standard search forms
- ✅ Search widget
- ✅ Search block (Gutenberg)
- ✅ Search REST API endpoints
- ✅ Multisite (per-site configuration)

## Requirements

- **WordPress** 5.2 or higher
- **PHP** 8.1 or higher
- **Meilisearch** instance (ScryWP Search managed hosting or self-hosted)

## Why Choose Scry Search?

| Feature | Scry Search | Other Plugins |
|---------|-------------|---------------|
| Zero frontend changes | ✅ | ❌ Usually require theme mods |
| Per-post-type indexes | ✅ | ⚠️ Limited |
| Federated search with weights | ✅ | ❌ Rare |
| Custom ranking rules per index | ✅ | ❌ Usually global only |
| Built-in task monitoring | ✅ | ❌ Rare |
| Custom meta field search | ✅ | ⚠️ Limited |
| Managed cloud & self-hosted | ✅ | ⚠️ Limited options |

## Support

- **Documentation**: [GitHub Wiki](https://github.com/jtgraham38/ScryWP-Search/wiki)
- **Issues & Bugs**: [GitHub Issues](https://github.com/jtgraham38/ScryWP-Search/issues)
- **Feature Requests**: [GitHub Discussions](https://github.com/jtgraham38/ScryWP-Search/discussions)
- **Contact**: [JG Web Development](https://jacob-t-graham.com)

## License

Scry Search for Meilisearch is open source software licensed under the [GPL v3](https://www.gnu.org/licenses/gpl-3.0.html).

---

**Transform your WordPress search today.** Install Scry Search for Meilisearch and give your users the instant, accurate search experience they deserve.
