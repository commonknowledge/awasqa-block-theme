<?php

namespace CommonKnowledge\WordPress\Awasqa;

// Logic around user data: includes handling of submitting user data,
// activating users and approving them as organisation members
require_once(__DIR__ . '/src/authors.php');

// Create Carbon Fields metadata
require_once(__DIR__ . '/src/carbon-fields.php');

// Create Carbon Fields blocks
require_once(__DIR__ . '/src/carbon-fields-blocks.php');

// Logic notifying users when they are activated or their posts are approved
require_once(__DIR__ . '/src/emails.php');

// Gravity forms logic
require_once(__DIR__ . '/src/gravity-forms.php');

// Alter queries used in query loops
require_once(__DIR__ . '/src/queries.php');

// Register post types and taxonomies
require_once(__DIR__ . '/src/post-types.php');
require_once(__DIR__ . '/src/taxonomies.php');

// WPML logic
require_once(__DIR__ . '/src/wpml.php');

$ver = "1.0";
add_action('wp_enqueue_scripts', function () use ($ver) {
    wp_enqueue_style(
        'awasqa',
        get_template_directory_uri() . '/style.css',
        ver: $ver
    );
    wp_enqueue_script(
        'awasqa-pre',
        get_template_directory_uri() . '/pre-script.js',
        ver: $ver
    );
    wp_enqueue_script(
        'awasqa-post',
        get_template_directory_uri() . '/script.js',
        ver: $ver,
        args: true // in_footer = true
    );
});

add_action('wp_footer', function () {
    ?>
    <script>
        window.USER_DATA = <?= json_encode(Authors\get_frontend_user_data()) ?>;
    </script>
    <?php
});

// bbpress is not compatible with block themes. It tries to find
// the old-style PHP templates (single.php, archive.php, etc), and fails.
// This filter makes bbpress use the default "Single Page" block template.
add_filter('bbp_template_include_theme_compat', function ($template) {
    if (!$template) {
        return get_query_template('wp-custom-template-forums');
    }
    return $template;
});

// The default User Agent is 'WordPress/6.3.1; $SITE_URL'
// This fails if the SITE_URL is http://localhost:8082
add_filter('http_headers_useragent', function ($user_agent) {
    return 'WordPress/6.3.1';
});

add_filter('render_block', function ($block_content, $block) {
    if ($block['blockName'] === 'core/heading') {
        global $post;
        $title = get_the_title($post);
        // This template is needed for e.g. "Articles by Common Knowledge" title on org page
        $block_content = preg_replace('#\[post_title\]#i', $title, $block_content);

        if (is_author()) {
            $author = get_queried_object();
            if ($author) {
                $author_name = $author->data->display_name ?: $author->data->user_nicename;
            } else {
                $author_name = __('Unknown author', 'awasqa');
            }
            // "Articles by Alex" template
            $block_content = preg_replace('#\[author_archives_author_name\]#i', $author_name, $block_content);
        }
    }
    return $block_content;
}, 10, 2);

// Fix forums URL in language switcher
add_filter('post_type_archive_link', function ($link, $post_type) {
    global $sitepress;
    $lang = $sitepress->get_this_lang();
    if ($lang !== 'en' && $post_type === 'forum') {
        $translated = WPML\get_translated_page_by_slug('forums', $lang);
        if ($translated) {
            return get_permalink($translated);
        }
    }
    return $link;
}, 10, 2);
