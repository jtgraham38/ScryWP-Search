# ScryWP Search

**Transform your WordPress site's search experience with lightning-fast, typo-tolerant search powered by Meilisearch.**

## Overview

ScryWP Search replaces WordPress's default search functionality with [Meilisearch](https://www.meilisearch.com/), a powerful open-source search engine that delivers instant, relevant results. Say goodbye to slow, inaccurate searches and hello to a professional search experience that your users will love.

## Key Features

### 🚀 **Blazing Fast Search**
- Instant search results, even on large sites with thousands of posts
- Typo-tolerant search that finds results even when users make spelling mistakes
- Handles complex queries with multiple keywords effortlessly

### 🎯 **Highly Customizable Relevancy**
- **Custom Ranking Rules**: Drag-and-drop interface to reorder ranking rules (words, typo, proximity, attribute, sort, exactness) for each index
- **Searchable Fields Control**: Choose exactly which fields are searchable, including custom post meta fields
- **Search Weights**: Assign different importance weights to different post types for federated search results

### 📊 **Multi-Post-Type Support**
- Index multiple post types independently
- Configure separate search settings for each post type
- Perfect for sites with posts, pages, products, custom post types, and more

### 🔧 **Easy Management**
- **One-Click Indexing**: Re-index all posts of a specific type with a single click
- **Index Management**: View index statistics, document counts, and indexing status
- **Live Search Preview**: Test your search queries directly from the admin panel
- **Automatic Updates**: Posts are automatically indexed when created or updated

### 🎨 **Seamless Integration**
- Drop-in replacement for WordPress search - no theme changes required
- Works with existing WordPress search forms and queries
- Maintains compatibility with WordPress search filters and hooks

## Installation

1. **Install Meilisearch**: You'll need a Meilisearch instance running. You can:
   - Use [Meilisearch Cloud](https://www.meilisearch.com/cloud) (recommended for production)
   - Self-host Meilisearch on your server
   - Run Meilisearch locally for development

2. **Install the Plugin**:
   - Upload the plugin files to `/wp-content/plugins/scrywp-search/`
   - Activate the plugin through the WordPress 'Plugins' screen

3. **Configure Connection**:
   - Navigate to **ScryWP Search > Connection Settings**
   - Enter your Meilisearch URL and API keys
   - Save settings

4. **Set Up Indexes**:
   - Go to **ScryWP Search > Indexes**
   - Select which post types you want to index
   - Click "Index Posts" for each post type

## Configuration

### Connection Settings
Configure your Meilisearch connection with:
- **Meilisearch URL**: Your Meilisearch server URL
- **Admin API Key**: For managing indexes and settings
- **Search API Key**: (Optional) For frontend search queries

### Index Configuration
For each post type index, you can:
- **Customize Ranking Rules**: Reorder built-in ranking rules to prioritize different relevancy factors
- **Configure Searchable Fields**: Select which fields (title, content, meta, etc.) are searchable
- **Set Search Weights**: Assign importance weights for federated search across multiple post types

### Search Settings
- Configure search weights for different post types
- Fine-tune how results from different content types are combined

## Requirements

- WordPress 5.2 or higher
- PHP 7.2 or higher
- Meilisearch instance (self-hosted or cloud)

## Why Choose ScryWP Search?

### For Site Managers
- **Better User Experience**: Users find what they're looking for faster, leading to increased engagement
- **Professional Search**: Enterprise-grade search capabilities without enterprise costs
- **Easy to Maintain**: Simple admin interface for managing search settings
- **Scalable**: Handles sites with thousands of posts without performance degradation

### For Developers
- **Well-Structured Code**: Clean, maintainable codebase following WordPress best practices
- **Extensible**: Hooks and filters for customizations
- **Standards Compliant**: Follows WordPress coding standards and security best practices

## Support

For support, feature requests, or bug reports, please visit the [plugin repository](https://github.com/your-repo/scrywp-search) or contact [JG Web Development](https://jacob-t-graham.com).

## License

This plugin is licensed under the GPLv3 or later.

---

**Transform your WordPress search today. Install ScryWP Search and give your users the search experience they deserve.**
