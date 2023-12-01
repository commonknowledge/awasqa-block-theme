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

function connect_translations_page()
{
    $original_post_links = [];
    if ($_POST) {
        $processed_ids = [];
        foreach ($_POST as $key => $value) {
            if (!$value || $value == 0) {
                continue;
            }

            if (in_array($key, $processed_ids)) {
                continue;
            }

            $post_id_a = preg_replace('#^post-#', '', $key);
            $key_b = array_values(array_filter(array_keys($_POST), function ($k) use ($key, $value) {
                return $k !== $key && $_POST[$k] === $value;
            }))[0];
            $post_id_b = preg_replace('#^post-#', '', $key_b);
            $original_id = $post_id_a < $post_id_b ? $post_id_a : $post_id_b;
            $translated_id = $original_id === $post_id_a ? $post_id_b : $post_id_a;

            $original_slug = get_post_field('post_name', $original_id);
            $translated_slug = get_post_field('post_name', $translated_id);

            $trid = apply_filters('wpml_element_trid', null, $original_id, 'post_post');
            if (!$trid) {
                echo "NO TRID FOR ORIGINAL POST $original_id: $original_slug\n";
                return;
            }

            $original_lang_details = apply_filters('wpml_post_language_details', null, $original_id);
            $original_language = $original_lang_details['language_code'] ?? null;

            $translated_lang_details = apply_filters('wpml_post_language_details', null, $translated_id);
            $translated_language = $translated_lang_details['language_code'] ?? null;

            if (!$original_language) {
                echo "NO LANG FOR ORIGINAL POST $original_id: $original_slug\n";
                return;
            }

            if (!$translated_language) {
                echo "NO LANG FOR TRANSLATED POST $translated_id: $translated_slug\n";
                return;
            }


            if ($original_language == $translated_language) {
                echo "BOTH POSTS $original_id: $original_slug AND $translated_id: $translated_slug HAVE LANGUAGE $original_language\n";
                return;
            }

            global $wpdb;
            $wpdb->query("DELETE FROM wp_icl_translations WHERE element_id = $translated_id;");

            $language_args = [
                'element_id' => $translated_id,
                'element_type' => 'post_post',
                'trid' => $trid,
                'language_code' => $translated_language,
                'source_language_code' => $original_language,
            ];

            do_action('wpml_set_element_language_details', $language_args);

            $processed_keys[] = $key;
            $processed_keys[] = $key_b;

            $original_post_links[get_the_permalink($original_id)] = get_the_title($original_id);
        }
    }
    $posts = get_posts(['posts_per_page' => -1, 'post_type' => 'post']);
    $untranslated_posts = [];
    foreach ($posts as $post) {
        $en_post_id = apply_filters('wpml_object_id', $post->ID, 'post', true, 'en');
        $es_post_id = apply_filters('wpml_object_id', $post->ID, 'post', true, 'es');
        if (!$en_post_id || !$es_post_id || $en_post_id === $es_post_id) {
            $untranslated_posts[] = $post;
        }
    }
    ?>
    <h1>Connect Translations</h1>
    <?php if ($original_post_links) : ?>
        <p><strong>Successfully linked:</strong></p>
        <ul>
            <?php foreach ($original_post_links as $link => $title) : ?>
                <li><a href="<?= $link ?>"><?= $title ?></a></li>
            <?php endforeach ?>
        </ul>
    <?php endif; ?>
    <p>
        <strong>
            Mark matching posts with the same number then click the
            "Connect" button at the bottom of the page.
        </strong>
    </p>
    <form method="POST">
        <ul>
            <?php foreach ($untranslated_posts as $post) : ?>
                <li>
                    <select class="connect-translation-select" name="post-<?= $post->ID ?>">
                        <option value="0" selected>---</option>
                        <?php for ($i = 1; $i <= 10; ++$i) : ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor ?>
                    </select>
                    <a href="<?= get_the_permalink($post->ID) ?>"><?= get_the_title($post->ID) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <button class="button button-primary save">Connect</button>
    </form>
    <script>
        const selects = document.querySelectorAll('.connect-translation-select')
        selects.forEach(select => {
            select.addEventListener('change', function() {
                const v = select.value
                if (v === '0') {
                    return
                }
                let count = 0
                selects.forEach(select => {
                    if (select.value === v) {
                        count++
                    }
                })
                selects.forEach(select => {
                    if (select.value !== v) {
                        const options = select.querySelectorAll('option')
                        for (const option of options) {
                            if (option.value === v) {
                                if (count > 1) {
                                    option.setAttribute('disabled', "true")
                                } else {
                                    option.removeAttribute('disabled')
                                }
                            }
                        }
                    }
                })
            })
        })
    </script>
    <?php
}

// Add page to connect pages that are translations of each other
add_action('admin_menu', function () {
    add_menu_page(
        'Connect Translations',
        'Connect Translations',
        'manage_options',
        'connect-translations',
        'CommonKnowledge\WordPress\Awasqa\WPML\connect_translations_page',
        'dashicons-admin-site',
        3
    );
});

// Disable WPML notifications by default
add_action('user_register', function ($user_id, $userdata) {
    update_user_meta($user_id, \WPML_User_Jobs_Notification_Settings::BLOCK_NEW_NOTIFICATION_FIELD, 1);
}, 10, 2);

add_filter('get_block_templates', function ($query_result, $query, $template_type) {
    if ($template_type !== "wp_template") {
        return $query_result;
    }
    $not_found_text = __('Not found: %1$s (%2$s)');
    $not_found_part = explode(":", $not_found_text)[0] . ": ";
    foreach ($query_result as $result) {
        $result->title = str_replace($not_found_part, "", $result->title);
    }
    return $query_result;
}, 10, 3);

add_action('init', function () {
    // Bug in WPML means that these roles break creating a Translation Manager
    $role = get_role('wpseo_manager');
    $role->remove_cap('edit_private_posts');
    $role = get_role('wpseo_editor');
    $role->remove_cap('edit_private_posts');
});
