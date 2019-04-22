# Multisite Taxonomies
## A WordPress plugin
Multisite Taxonomies brings the ability to register custom taxonomies, accessible on an entire multisite network, to WordPress.

Master branch: [![CircleCI](https://circleci.com/gh/HarvardChanSchool/multisite-taxonomies.svg?style=svg)](https://circleci.com/gh/HarvardChanSchool/multisite-taxonomies)

## Coding standards
We follow [WordPress Coding Standards](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) and enforce them using PHP Code Sniffer.

To test localy simply run:
- `$ composer install` (if you haven't already)
- `$ ./vendor/bin/phpcs ./`

### Dependencies:
- [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx) (globally installed)

### How to get started?
- Start by copying the plugin to your website's WordPress plugin directory.
- Activate the plugin. 
- A Multisite Taxonomy menu will appear in the admin but it will be blank. 
- Add taxonomies to the website by using register_multisite_taxonomy called on the `init` hook. We would recommend doing this in a separate plugin of your creation. 
- Multisite tags can then be added to posts through the post edit screen on any site on the network.

### Register Taxonomy Example:

```php
add_action( 'init', 'register_multisite_taxonomies', 0 );

/**
 * Load in all taxonomies.
 *
 * @return void
 */
function register_multisite_taxonomies() {
    /**
     * Load taxonomy for Tags
     */
    $labels     = array(
        'name'                       => __( 'Tags', 'hsph-plugin-tagging' ),
        'singular_name'              => __( 'Tag', 'hsph-plugin-tagging' ),
        'menu_name'                  => __( 'Tags', 'hsph-plugin-tagging' ),
        'all_items'                  => __( 'All Tags', 'hsph-plugin-tagging' ),
        'new_item_name'              => __( 'New Tag Name', 'hsph-plugin-tagging' ),
        'add_new_item'               => __( 'Add New Tag', 'hsph-plugin-tagging' ),
        'edit_item'                  => __( 'Edit Tag', 'hsph-plugin-tagging' ),
        'update_item'                => __( 'Update Tag', 'hsph-plugin-tagging' ),
        'view_item'                  => __( 'View Tag', 'hsph-plugin-tagging' ),
        'separate_items_with_commas' => __( 'Separate tags with commas', 'hsph-plugin-tagging' ),
        'add_or_remove_items'        => __( 'Add or remove tags', 'hsph-plugin-tagging' ),
        'choose_from_most_used'      => __( 'Choose from the most used tags', 'hsph-plugin-tagging' ),
        'popular_items'              => __( 'Popular Tags', 'hsph-plugin-tagging' ),
        'search_items'               => __( 'Search Tags', 'hsph-plugin-tagging' ),
        'not_found'                  => __( 'No Tags Found', 'hsph-plugin-tagging' ),
        'no_terms'                   => __( 'No tags for this category', 'hsph-plugin-tagging' ),
        'most_used'                  => __( 'Most Used', 'hsph-plugin-tagging' ),
        'items_list'                 => __( 'Tags list', 'hsph-plugin-tagging' ),
        'items_list_navigation'      => __( 'Tags list navigation', 'hsph-plugin-tagging' ),
    );

    $args       = array(
        'labels'       => $labels,
        'hierarchical' => false,
    );
    
    $post_types = apply_filters( 'multisite_taxonomy_tags_post_types', array( 'post' ) );
    register_multisite_taxonomy( 'tag', $post_types, $args );
}
```
