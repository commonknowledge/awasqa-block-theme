<?php

namespace CommonKnowledge\WordPress\Awasqa\Taxonomies;

use CommonKnowledge\WordPress\Awasqa;

/**
 * Load translated taxonomy slugs, returns this format:
 *
 * [
 *   $taxonomy_name => [
 *     $language => $slug,
 *     ...
 *   ],
 *   ...
 * ]
 */
function get_taxonomy_slugs()
{
    global $awasqa_taxonomy_slugs, $wpdb;
    if (!$awasqa_taxonomy_slugs) {
        $awasqa_taxonomy_slugs = [];
        $query = <<<EOF
        SELECT
          s.name AS name,
          s.value AS original,
          s.language AS original_language,
          st.language AS language,
          st.value AS translated
        FROM wp_icl_strings AS s
        INNER JOIN wp_icl_string_translations AS st
        WHERE s.id=st.string_id
          AND s.name like '% tax slug';
EOF;
        $strings = $wpdb->get_results($query);
        foreach ($strings as $string) {
            // The string name in the database is e.g. "URL awasqa_country tax slug"
            // As far as I can tell, this is the only way to pull
            // the taxonomy name from the db
            preg_match('#^URL (.*) tax slug$#', $string->name, $matches);
            if (count($matches) !== 2) {
                continue;
            }
            $tax_name = $matches[1];
            if (empty($awasqa_taxonomy_slugs[$tax_name])) {
                $awasqa_taxonomy_slugs[$tax_name] = [];
            }
            $awasqa_taxonomy_slugs[$tax_name][$string->language] = $string->translated;
            $awasqa_taxonomy_slugs[$tax_name][$string->original_language] = $string->original;
        }
    }
    return $awasqa_taxonomy_slugs;
}

function get_translated_taxonomy_slug($taxonomy_name, $language, $default)
{
    $taxonomy_slugs = get_taxonomy_slugs();
    return ($taxonomy_slugs[$taxonomy_name][$language] ?? $default) ?: $default;
}

function get_taxonomy_name_from_slug($translated_slug)
{
    $taxonomy_slugs = get_taxonomy_slugs();
    foreach ($taxonomy_slugs as $taxonomy => $languages) {
        foreach ($languages as $language_slug) {
            if ($language_slug && $language_slug === $translated_slug) {
                return $taxonomy;
            }
        }
    }
    return false;
}

add_action('init', function () {
    register_taxonomy('awasqa_country', ['post', 'awasqa_organisation', 'awasqa_event'], [
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'country'],
        'labels'            => [
            'name'              => _x('Countries', 'taxonomy general name'),
            'singular_name'     => _x('Country', 'taxonomy singular name'),
        ]
    ]);
}, 9);

add_filter('term_link', function ($termlink, $term_id, $taxonomy) {
    // Categories are already handled by the "category_link" filter
    $taxonomy_name = get_taxonomy_name_from_slug($taxonomy);
    if ($taxonomy_name === 'category') {
        return $termlink;
    }
    $term = get_term($term_id, $taxonomy);
    $lang = Awasqa\WPML\get_current_language('en');
    $articles_page = Awasqa\WPML\get_translated_page_by_slug("articles", $lang);
    $taxonomy_param = get_translated_taxonomy_slug($taxonomy, $lang, $taxonomy);
    $link = get_permalink($articles_page);
    $link = add_query_arg($taxonomy_param, $term->slug, $link);
    return $link;
}, 10, 3);

add_filter("category_link", function ($termlink, $term_id) {
    $category = get_category($term_id);
    $lang = Awasqa\WPML\get_current_language('en');
    $articles_page = Awasqa\WPML\get_translated_page_by_slug("articles", $lang);
    $issue_param = get_translated_taxonomy_slug('category', $lang, 'category');
    $country_param = get_translated_taxonomy_slug('awasqa_country', $lang, 'country');
    $country = $_GET[$country_param] ?? null;
    $link = get_permalink($articles_page);
    $link = add_query_arg($issue_param, urlencode($category->slug), $link);
    if ($country) {
        $link = add_query_arg($country_param, $country, $link);
    }
    return $link;
}, 10, 2);

// Add active class to category link if appropriate
add_filter('category_list_link_attributes', function ($atts, $category, $depth, $args, $current_object_id) {
    $lang = Awasqa\WPML\get_current_language('en');
    $issue_param = get_translated_taxonomy_slug('category', $lang, 'category');
    $issue = $_GET[$issue_param] ?? null;
    if ($issue === $category->slug) {
        $classes = explode(' ', $atts['class'] ?? '');
        $classes[] = 'active';
        $atts['class'] = implode(' ', $classes);
        $atts['href'] = remove_query_arg($issue_param, $atts['href']);
    }
    return $atts;
}, 10, 5);
