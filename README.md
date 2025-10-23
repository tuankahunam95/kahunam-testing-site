# InternalLink Manager - WordPress Plugin

A powerful WordPress plugin that automatically identifies orphaned pages and provides actionable internal linking suggestions to improve your site's SEO.

## Description

**InternalLink Manager** helps you solve one of the most common SEO problems: orphaned pages. Orphaned pages are pages with zero internal links pointing to them, making them difficult for search engines to discover and crawl. This plugin automatically scans your WordPress site, identifies orphaned pages, and suggests natural, contextual internal linking opportunities.

## Features

### Core Features (MVP)

- **Orphaned Page Detection**: Automatically scan your entire WordPress site to identify pages with zero internal links
- **Site-Wide Content Analysis**: Crawl all published posts and pages to build a searchable content index
- **Intelligent Link Suggestions**: AI-powered algorithm finds relevant linking opportunities based on:
  - Keyword matching
  - Contextual relevance
  - Topic clustering (categories/tags)
- **Detailed Suggestion Interface**: For each orphaned page, view:
  - Source page where the link should be added
  - Exact paragraph and sentence location
  - Suggested anchor text options
  - Relevance score (0-100)
  - Context preview
- **Manual Link Implementation**: Review suggestions and manually add links to your content
- **Progress Tracking**: Mark suggestions as accepted, rejected, or pending

### Dashboard Features

- **Statistics Overview**: See total orphaned pages at a glance
- **Filterable Lists**: Filter orphaned pages by post type, date, and status
- **Batch Scanning**: Efficiently scan large sites with configurable batch processing
- **Real-time Progress**: Track scanning progress with visual progress bars

### Settings & Customization

- **Post Type Selection**: Choose which post types to scan (posts, pages, custom post types)
- **Relevance Threshold**: Set minimum relevance score for suggestions
- **Suggestion Limits**: Configure max suggestions per orphaned page
- **Performance Tuning**: Adjust batch size for optimal performance
- **Link Density Control**: Avoid over-linking by excluding high link density pages

## Installation

### Manual Installation

1. Download the plugin files
2. Upload the `internallink-manager` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **InternalLink Manager** in the WordPress admin menu

### WordPress Dashboard Installation

1. Go to **Plugins > Add New**
2. Upload the plugin ZIP file
3. Click **Install Now** and then **Activate**

## Usage

### Getting Started

1. **Initial Scan**
   - Navigate to **InternalLink Manager > Scan Site**
   - Click **Start Scanning** to scan your entire site
   - Wait for the scan to complete (progress bar shows real-time status)

2. **View Orphaned Pages**
   - Go to **InternalLink Manager > Dashboard**
   - See all orphaned pages listed with their details
   - Click **Generate Suggestions** for any orphaned page

3. **Review Link Suggestions**
   - Click **View Suggestions** on any orphaned page
   - Review each suggestion's relevance score and context
   - Click **Edit Source Page** to manually add the suggested link
   - Copy the suggested anchor text for easy implementation
   - Mark suggestions as **Accepted** or **Rejected**

4. **Configure Settings**
   - Go to **InternalLink Manager > Settings**
   - Adjust relevance thresholds, batch sizes, and other preferences
   - Save your settings

### Best Practices

1. **Start with High-Value Pages**: Focus on orphaned pages that are important for SEO or conversions
2. **Review Suggestions Carefully**: Not all suggestions may be contextually appropriate
3. **Use Natural Anchor Text**: Choose anchor text that reads naturally in the sentence
4. **Avoid Over-Linking**: Don't add too many links to a single page
5. **Re-scan Regularly**: Run scans periodically as you add new content

## Technical Details

### System Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher (for database tables)

### Database Tables

The plugin creates two custom tables:

1. **`wp_ilm_pages`**: Tracks all pages and their internal link status
   - Stores post ID, internal link count, scan timestamp, orphaned status

2. **`wp_ilm_link_suggestions`**: Stores link suggestions
   - Stores target post, source post, paragraph index, sentence text, anchor text, relevance score, status

### Performance Considerations

- **Batch Processing**: Scans are processed in configurable batches to prevent timeouts
- **Incremental Scanning**: Only scans what's needed, avoiding full site crawls
- **AJAX-Powered**: Background scanning doesn't block the admin interface
- **Caching**: Results are cached in the database for quick retrieval

### Matching Algorithm

The plugin uses a multi-factor relevance scoring system:

1. **Exact Title Match**: 100% relevance score
2. **Keyword Matching**: Scores based on keyword frequency and position
3. **Contextual Analysis**: Considers sentence structure and content flow
4. **Topic Clustering**: Uses WordPress categories and tags for relevance

### Compatibility

- ✅ Gutenberg Block Editor
- ✅ Classic Editor
- ✅ Page Builders (Elementor, Divi, etc.)
- ✅ Custom Post Types
- ✅ Multisite (basic support)

## Frequently Asked Questions

### What are orphaned pages?

Orphaned pages are pages on your website that have zero internal links pointing to them from other pages on your site. This makes them difficult for search engines to discover and can hurt your SEO.

### How does the plugin find link opportunities?

The plugin analyzes the content of your orphaned pages, extracts key topics and keywords, then searches through all other pages on your site to find sentences where these topics are mentioned. It suggests adding a link in those locations.

### Will the plugin automatically add links?

No, the MVP version provides manual suggestions only. You need to review each suggestion and manually add the links by editing the source page. This gives you full control over your content.

### How accurate are the relevance scores?

Relevance scores are calculated based on keyword matches, contextual similarity, and topic clustering. Higher scores (80-100%) are usually highly relevant, while lower scores (50-70%) may require more careful review.

### Can I exclude certain pages?

Currently, you can exclude entire post types in the settings. Future versions may include more granular exclusion options.

### How often should I scan my site?

It's recommended to scan your site whenever you publish significant new content or make major structural changes. For active blogs, monthly scans are a good practice.

### Does this work with page builders?

Yes, the plugin extracts plain text content from all major page builders including Elementor, Divi, and others. It strips HTML and shortcodes to analyze the actual content.

### Will this slow down my site?

No, the scanning process runs only in the admin area and uses AJAX for background processing. There is no impact on front-end performance.

## Changelog

### Version 1.0.0 - Initial Release

- Orphaned page detection
- Site-wide content scanning
- Link opportunity detection algorithm
- Dashboard with orphaned pages list
- Link suggestions interface
- Settings page with customization options
- Batch processing for large sites
- AJAX-powered scanning
- Manual link implementation workflow

## Future Enhancements

Planned features for future releases:

- **Auto-Insert Mode**: Automatically insert links with user approval
- **Semantic Analysis**: Advanced AI/ML for better relevance matching
- **Link Health Monitor**: Track internal link changes over time
- **Bulk Operations**: Accept/reject multiple suggestions at once
- **Email Reports**: Scheduled reports of orphaned pages
- **Integration with SEO Plugins**: Work with Yoast, RankMath, etc.
- **Link Decay Detection**: Find and fix broken internal links

## Support

For bug reports, feature requests, or support questions, please visit:
- GitHub Issues: [https://github.com/tuankahunam95/kahunam-testing-site/issues](https://github.com/tuankahunam95/kahunam-testing-site/issues)

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by **KahunaM**
- GitHub: [@tuankahunam95](https://github.com/tuankahunam95)

---

**Note**: This is an MVP (Minimum Viable Product) release focused on core functionality. We're actively working on enhancements and appreciate your feedback!
