<?php

namespace CommonKnowledge\WordPress\Awasqa\WPML;

function get_current_language($default = null)
{
    return apply_filters('wpml_current_language', null) ?: $default;
}


/**
 * Get english version of a page
 */
function get_en_page($page_id)
{
    if (!$page_id) {
        return null;
    }
    $post_id = apply_filters('wpml_object_id', $page_id, 'page', true, 'en');
    return get_post($post_id);
}

function get_original_post_id($post_id, $post_type)
{
    return apply_filters('wpml_original_element_id', null, $post_id, 'post_' . $post_type) ?: $post_id;
}

/**
 * Get translated page
 */
function get_translated_page_by_slug($slug, $lang)
{
    if (!$slug) {
        return null;
    }

    $posts = get_posts(['name' => $slug, 'post_type' => 'page', 'numposts' => 1]);
    if (!$posts) {
        return null;
    }

    $page_id = $posts[0]->ID;
    $translated_page_id = apply_filters('wpml_object_id', $page_id, 'page', true, $lang);
    return get_post($translated_page_id);
}

// Fix weird SitePress bug where the query was being mangled
// Clear SitePress state after it has parsed any query
add_action("parse_query", function () {
    global $wpml_query_filter;
    $r = new \ReflectionObject($wpml_query_filter);
    $p = $r->getProperty('name_filter');
    $p->setAccessible(true);
    $p->setValue($wpml_query_filter, []);
}, 99, 0);

/**
 * Add original slug to the possible templates, so translated pages
 * match the templates of their original versions.
 *
 * Note: the templates must themselves be translated before
 * they can be used for translated pages.
 */
add_filter('page_template_hierarchy', function ($templates) {
    global $post;
    $en_page = get_en_page($post?->ID);
    if (!$en_page) {
        return $templates;
    }
    $slug = $en_page->post_name;
    $template = 'page-' . $slug . '.php';
    if (!in_array($template, $templates)) {
        array_unshift($templates, $template);
    }
    return $templates;
});

/**
 * Hack WPML to always display the language switcher on an author archive page.
 * If there's a better way to do this, I'd like to know...
 */
add_filter('query', function ($query) {
    $backtrace = debug_backtrace();
    $author_query_has_posts = array_filter($backtrace, function ($item) {
        return str_contains($item['function'], 'author_query_has_posts');
    });
    if ($author_query_has_posts) {
        return "SELECT COUNT(1) FROM wp_posts";
    }
    return $query;
});
