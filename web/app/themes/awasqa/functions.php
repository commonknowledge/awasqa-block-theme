<?php

namespace CommonKnowledge\WordPress\Awasqa;

use Carbon_Fields\Block;
use Carbon_Fields\Container;
use Carbon_Fields\Field;

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

function get_current_language($default = null)
{
    return apply_filters('wpml_current_language', null) ?: $default;
}

/**
 * $author should be an array:
 *
 * [
 *     'link' => '/author/...',
 *     'name' => 'Alex',
 *     'bio' => 'Lorem ipsum dolor sit...',
 *     'image_url' => '/app/uploads/...jpg'
 * ]
 */
function render_author_column($author, $show_visit_link = true)
{
    if (!$author['name']) {
        return;
    }
    ?>
    <div class="awasqa-author-column">
        <a class="awasqa-author-column__heading" href="<?= $author['link'] ?>">
            <?php if ($author['image_url']) : ?>
                <img alt="<?= $author['name'] ?>" src="<?= $author['image_url'] ?>">
            <?php endif; ?>
            <h2><?= $author['name'] ?></h2>
        </a>
        <p>
            <?= $author['bio'] ?>
        </p>
        <?php if ($show_visit_link) : ?>
            <a class="awasqa-author-column__visit" href="<?= $author['link'] ?>">
                <?= __('Visit author profile', 'awasqa') ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
}

function get_frontend_user_data()
{
    $user_id = get_current_user_id();
    if (!$user_id) {
        return [
            "user_id" => null,
            "organisations" => []
        ];
    }
    $organisations = get_author_organisations($user_id);
    return [
        "user_id" => $user_id,
        "organisations" => $organisations
    ];
}

/**
 * Get organisations associated with an author.
 * Have to do it by getting all the orgs and checking each one,
 * because using a meta query doesn't work in different site locales.
 */
function get_author_organisations($author_id)
{
    $org_query = new \WP_Query([
        'post_type' => 'awasqa_organisation',
    ]);
    $orgs = $org_query->posts ?: [];
    $author_orgs = [];
    foreach ($orgs as $org) {
        $authors = awasqa_carbon_get_post_meta($org->ID, 'members');
        $matching_author = array_filter($authors, function ($author) use ($author_id) {
            return (string) $author['id'] === (string) $author_id;
        });
        if ($matching_author) {
            $author_orgs[] = $org;
        }
    }
    return $author_orgs;
}

function awasqa_get_author_name($author_id)
{
    return get_the_author_meta("display_name", $author_id) ?: get_the_author_meta("user_nicename", $author_id);
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

    $posts = get_posts(['name' => $slug, 'post_type' => 'page']);
    if (!$posts) {
        return null;
    }

    $page_id = $posts[0]->ID;
    $translated_page_id = apply_filters('wpml_object_id', $page_id, 'page', true, $lang);
    return get_post($translated_page_id);
}

/**
 * Sometimes carbon_get_post_meta doesn't work when using translated posts.
 * This function is more reliable, as it gets the original post ID.
 */
function awasqa_carbon_get_post_meta($post_id, $name, $container_id = '')
{
    $post = get_post($post_id);
    if (!$post) {
        return null;
    }
    $original_post_id = get_original_post_id($post->ID, $post->post_type);
    return carbon_get_post_meta($original_post_id, $name, $container_id);
}

function add_user_to_organisation($org_id, $user_id)
{
    $original_org_id = get_original_post_id($org_id, 'awasqa_organisation');
    $members = carbon_get_post_meta($original_org_id, 'members');

    $user_exists = (bool) array_filter($members, function ($member) use ($user_id) {
        return $member['id'] === $user_id;
    });

    if ($user_exists) {
        return;
    }

    $members[] = [
        'value' => 'post:members:' . $user_id,
        'id' => $user_id,
        'type' => 'user',
        'subtype' => ''
    ];
    carbon_set_post_meta(
        $original_org_id,
        'members',
        $members
    );
}

function get_admin_email_from_address()
{
    $site_url = home_url();
    $parsed_url = parse_url($site_url);
    return 'admin@' . $parsed_url['host'];
}

function notify_user_joined_org($user_id, $org)
{
    $user = get_userdata($user_id);
    $language_details = apply_filters('wpml_post_language_details', null, $org->ID);
    $post_language = $language_details['language_code'] ?? null;
    $account = get_translated_page_by_slug('account', $post_language);
    $href = get_permalink($account);
    $link = '<a href"' . $href . '">' . $href . '</a>';
    $from = get_admin_email_from_address();
    
    wp_mail(
        $user->user_email,
        __('Your request to join', 'awasqa') . ' ' . $org->post_title . ' ' . __('has been approved.', 'awasqa'),
        __('Thank you for registering on Awasqa. View your account here: ') . $link,
        headers: ['From: ' => $from]
    );
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

    register_post_type(
        'awasqa_event',
        array(
            'labels'      => array(
                'name'          => __('Events', 'awasqa'),
                'singular_name' => __('Event', 'awasqa'),
            ),
            'public'      => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-calendar',
            'rewrite' => array('slug' => 'event'),
            'show_in_rest' => true,
            'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt'),
            'taxonomies' => array('category', 'awasqa_country')
        )
    );

    register_post_type(
        'awasqa_organisation',
        array(
            'labels'      => array(
                'name'          => 'Organisations',
                'singular_name' => 'Organisation',
            ),
            'public'      => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-megaphone',
            'rewrite' => array('slug' => 'organisation'),
            'show_in_rest' => true,
            'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt'),
            'taxonomies' => array('category', 'awasqa_country')
        )
    );
}, 9);

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
        window.USER_DATA = <?= json_encode(get_frontend_user_data()) ?>;
    </script>
    <?php
});

add_action('carbon_fields_register_fields', function () {
    Container::make('post_meta', 'Source')
        ->where('post_type', '=', 'post')
        ->add_fields(array(
            Field::make('text', 'source_publication', 'Source Publication Name'),
            Field::make('text', 'source_url', 'Source URL')->set_attribute('type', 'url'),
        ));

    Container::make('post_meta', 'Related Posts')
        ->where('post_type', '=', 'post')
        ->add_fields(array(
            Field::make('association', 'related_posts', 'Posts')
                ->set_types([
                    [
                        'type'      => 'post',
                        'post_type' => 'post'
                    ]
                ])
        ));

    Container::make('post_meta', 'Contact')
        ->where('post_type', '=', 'awasqa_organisation')
        ->add_fields(array(
            Field::make('text', 'email', 'Email address')->set_attribute('type', 'email'),
            Field::make('text', 'twitter', 'Twitter URL')->set_attribute('type', 'url'),
            Field::make('text', 'facebook', 'Facebook URL')->set_attribute('type', 'url'),
        ));

    Container::make('post_meta', 'Members')
        ->where('post_type', '=', 'awasqa_organisation')
        ->add_fields(array(
            Field::make('association', 'members', 'Authors')
                ->set_types([
                    [
                        'type'      => 'user',
                    ]
                ])
        ));

    Container::make('post_meta', 'Event Details')
        ->where('post_type', '=', 'awasqa_event')
        ->add_fields([
            Field::make('date', 'event_date', 'Event Date'),
            Field::make('time', 'event_time', 'Event Time (in UTC, i.e. +00:00)')
                ->set_input_format('H:i', 'H:i')
                ->set_picker_options([
                    'altInput' => false,
                    'dateFormat' => 'H:i',
                    'time_24hr' => true,
                    'enableSeconds' => false
                ])
        ]);

    Block::make('Post Source')
        ->set_icon('search')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Post Source', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $post = get_post();
            $source_url = awasqa_carbon_get_post_meta($post->ID, 'source_url');
            $source_publication = awasqa_carbon_get_post_meta($post->ID, 'source_publication');
            $text_parts = [];
            if ($source_publication) {
                $text_parts[] = __('This article was originally published in ', 'awasqa') . $source_publication . '.';
            }
            if ($source_url) {
                $text_parts[] = __('Click link below to see the original article.', 'awasqa');
            }
            if (!$text_parts) {
                return;
            }
            ?>
        <div class="awasqa-post-source">
            <h6><?= __('Source', 'awasqa') ?></h6>
            <p><?= implode("", $text_parts) ?></p>
            <?php if ($source_url) : ?>
                <p>
                    <a target="_blank" href="<?= $source_url ?>" class="awasqa-link-arrow">
                        <?= __('Visit original article', 'awasqa') ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
            <?php
        });

    Block::make('Countries List')
        ->set_icon('admin-site')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Countries List', 'awasqa')),
            Field::make('checkbox', 'is_filter', __('Filter Mode', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $countries = get_terms([
                'taxonomy'   => 'awasqa_country',
                'hide_empty' => false,
            ]);
            $is_filter = $fields['is_filter'] ?? false;

            function get_link($country, $is_filter)
            {
                $link = get_term_link($country->slug, 'awasqa_country');
                if (!$is_filter) {
                    return [
                        'href' => $link,
                        'class' => ''
                    ];
                }

                // If the link is for a filter, return e.g. ?country=mexico instead of /country/mexico/
                // Also append the language if necessary, e.g. ?lang=es&pais=mexico
                $language = get_current_language('en');
                $translated_slug = get_translated_taxonomy_slug('awasqa_country', $language, 'country');

                $current_url = parse_url($_SERVER['REQUEST_URI']);

                $query = '';
                $locale = get_current_language();
                if ($locale) {
                    // Add the lang query parameter if required
                    $query = '?lang=' . $locale;
                }

                $active = ($_GET[$translated_slug] ?? '') === $country->slug;
                if (!$active) {
                    // Add query ?country=mexico
                    $query_param = $translated_slug . '=' . $country->slug;
                    $query_glue = $query ? '&' : '?';
                    $query .= $query_glue . $query_param;
                }

                $href = $current_url['path'] . $query;

                $issue_param = get_translated_taxonomy_slug('category', $locale, 'category');
                $issue = $_GET[$issue_param] ?? null;
                if ($issue) {
                    $href = add_query_arg($issue_param, $issue, $href);
                }

                return [
                    'href' => $href,
                    'class' => $active ? 'active' : ''
                ];
            }
            ?>
        <ul class="wp-block-categories">
            <?php foreach ($countries as $country) {
                $link = get_link($country, $is_filter);
                ?>
                <li>
                    <a href="<?= $link['href'] ?>" class="<?= $link['class'] ?>">
                        <?= __($country->name, 'awasqa') ?>
                    </a>
                </li>
            <?php } ?>
        </ul>
            <?php
        });

    function get_issue_options()
    {
        $categories = get_categories(['hide_empty' => false]);
        $options = [];
        foreach ($categories as $category) {
            $options[$category->slug] = $category->name;
        }
        return $options;
    }

    Block::make('Issue Link')
        ->set_icon('category')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Issue Link', 'awasqa')),
            Field::make('select', 'category', __('Issue', 'awasqa'))
                ->add_options('CommonKnowledge\WordPress\Awasqa\get_issue_options')
                ->set_default_value("uncategorized")
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $category_slug = $fields['category'];
            $category = get_category_by_slug($category_slug);
            ?>
        <a class="awasqa-issue-link" href="/category/<?= $category->slug ?>">
            <span class="awasqa-issue-link__title"><?= $category->name ?></span>
            <span class="awasqa-issue-link__more"><?= __('More', 'awasqa') ?></span>
            <img src="/app/themes/awasqa/assets/images/arrow-right.svg">
        </a>
            <?php
        });

    Block::make('Authors')
        ->set_icon('groups')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Authors', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $post = get_post();
            $authors = get_coauthors($post->ID);
            if (!$authors) {
                return;
            }
            $author_data = [];
            foreach ($authors as $author) {
                $name = awasqa_get_author_name($author->ID);
                $author_data[] = [
                    "link" => get_author_posts_url($author->ID),
                    "name" => $name
                ];
            }
            ?>
        <ul class="awasqa-authors">
            <?php foreach ($author_data as $author) : ?>
                <li>
                    <a href="<?= $author['link'] ?>"><?= $author['name'] ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
            <?php
        });

    Block::make('Organisation Contact Details')
        ->set_icon('megaphone')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Organisation Contact Details', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            if (is_author()) {
                $author = get_queried_object();
                if (!$author) {
                    return;
                }
                $orgs = get_author_organisations($author->ID);
                if (!$orgs) {
                    return;
                }
                $org = $orgs[0];
            } else {
                $org = get_post();
            }
            $email = awasqa_carbon_get_post_meta($org->ID, 'email');
            $twitter = awasqa_carbon_get_post_meta($org->ID, 'twitter');
            $facebook = awasqa_carbon_get_post_meta($org->ID, 'facebook');
            ?>
        <div class="awasqa-org-contact-details">
            <?php if ($email) : ?>
                <h3><?= __('Email', 'awasqa') ?></h3>
                <a class="awasqa-org-contact-details__email" href="mailto:<?= $email ?>">
                    <?= $email ?>
                </a>
            <?php endif; ?>
            <?php if ($twitter || $facebook) : ?>
                <h3><?= __('Social Media', 'awasqa') ?></h3>
                <?php if ($twitter) : ?>
                    <a class="awasqa-org-contact-details__twitter" href="<?= $twitter ?>">
                        Twitter
                    </a>
                <?php endif; ?>
                <?php if ($facebook) : ?>
                    <a class="awasqa-org-contact-details__facebook" href="<?= $facebook ?>">
                        Facebook
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
            <?php
        });

    Block::make('Organisation Authors')
        ->set_icon('groups')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Organisation Authors', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $post = get_post();
            $members = awasqa_carbon_get_post_meta($post->ID, 'members');
            $author_data = [];
            foreach ($members as $member) {
                $user_id = $member['id'];
                $meta = get_user_meta($user_id);
                $name = awasqa_get_author_name($user_id);
                $image_id = $meta['awasqa_profile_pic_id'][0] ?? 0;
                $image_url = $image_id ? wp_get_attachment_image_src($image_id) : null;
                $author_data[] = [
                    "link" => get_author_posts_url($user_id),
                    "name" => $name,
                    "bio" => $meta['description'][0] ?? "",
                    "image_url" => $image_url[0] ?? null
                ];
            }
            ?>
        <ul class="awasqa-organisation-authors">
            <?php foreach ($author_data as $author) : ?>
                <li>
                    <?php render_author_column($author); ?>
                </li>
            <?php endforeach; ?>
        </ul>
            <?php
        });

    Block::make('Author Column')
        ->set_icon('admin-users')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Author Column', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $author = get_queried_object();
            if (!$author) {
                return;
            }
            $author_id = $author->data->ID;
            $author_name = $author->data->display_name ?: $author->data->user_nicename;

            $meta = get_user_meta($author_id);
            $image_id = $meta['awasqa_profile_pic_id'][0] ?? null;
            $image_url = $image_id ? wp_get_attachment_image_src($image_id) : null;
            $author_data = [
                "link" => get_author_posts_url($author_id),
                "name" => $author_name,
                "bio" => $meta['description'][0] ?? null,
                "image_url" => $image_url[0] ?? null
            ];

            render_author_column($author_data, show_visit_link: false);
        });

    Block::make('Authors Column')
        ->set_icon('groups')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Authors Column', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $post = get_post();
            $authors = get_coauthors($post->ID);
            if (!$authors) {
                return;
            }
            $authors_data = [];
            foreach ($authors as $author) {
                $name = awasqa_get_author_name($author->ID);
                $meta = get_user_meta($author->ID);
                $image_id = $meta['awasqa_profile_pic_id'][0] ?? null;
                $image_url = $image_id ? wp_get_attachment_image_src($image_id) : null;
                $authors_data[] = [
                    "link" => get_author_posts_url($author->ID),
                    "name" => $name,
                    "bio" => $meta['description'][0] ?? null,
                    "image_url" => $image_url[0] ?? null
                ];
            }
            ?>
        <div class="awasqa-authors-column">
            <h6><?= __('Authors', 'awasqa') ?></h6>
            <?php
            foreach ($authors_data as $author_data) {
                render_author_column($author_data, show_visit_link: true);
            }
            ?>
        </div>
            <?php
        });

    Block::make('Account Details Form')
        ->set_icon('admin-users')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Account Details Form', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $user_id = get_current_user_id();
            if (!$user_id) {
                return;
            }
            $name = get_the_author_meta("display_name", $user_id);
            $meta = get_user_meta($user_id);
            $bio = $meta['description'][0] ?? "";
            $image_id = $meta['awasqa_profile_pic_id'][0] ?? 0;
            $image_url = $image_id ? wp_get_attachment_image_src($image_id) : null;
            ?>
        <form class="awasqa-account-details-form" method="post" enctype="multipart/form-data">
            <input name="form-nonce" type="hidden" value="<?= wp_create_nonce('awasqa_account_details_form') ?>" />
            <div class="awasqa-account-details-form__row">
                <label for="name"><?= __('Name', 'awasqa') ?></label>
                <input id="name" name="form-name" type="text" value="<?= $name ?>">
            </div>
            <div class="awasqa-account-details-form__row">
                <label for="profile-pic"><?= __('Profile pic', 'awasqa') ?></label>
                <?php if ($image_url) : ?>
                    <img src="<?= $image_url[0] ?>">
                <?php endif; ?>
                <input id="profile-pic" name="form-profile-pic" type="file">
            </div>
            <div class="awasqa-account-details-form__row">
                <label for="bio"><?= __('Bio', 'awasqa') ?></label>
                <textarea id="bio" name="form-bio"><?= $bio ?></textarea>
            </div>
            <button><?= __('Submit', 'awasqa') ?></button>
        </form>
            <?php
        });

    Block::make('Event Date')
        ->set_icon('calendar')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Event Date', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $event = get_post();
            $event_date = awasqa_carbon_get_post_meta($event->ID, 'event_date');
            // Default to 6pm to prevent events with no specified time having the wrong date displayed in Western timezones
            // E.G. Mexico is up to UTC-7, so if the default were midnight, the date would go back one day when displayed
            // in a Mexican user's locale
            $event_time = awasqa_carbon_get_post_meta($event->ID, 'event_time') ?: '18:00:00';
            ?>
        <div class="wp-block-post-date">
            <time datetime="<?= $event_date ?>T<?= $event_time ?>+00:00"></time>
            <?= $event_date ?>
        </div>
            <?php
        });

    Block::make('User Activation Success')
        ->set_icon('yes')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('User Activation Success', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $action = $_GET['action'] ?? null;
            $user = $_GET['user'] ?? null;
            $org = $_GET['org'] ?? null;
            if (!$action) {
                return;
            }
            ?>
            <div class="awasqa-user-activation-success">
                <?php if ($action === "activated") : ?>
                    <p>User <?= $user ?> has been activated. We have sent them an email to let them know.</p>
                <?php endif; ?>
                <?php if ($action === "approved") : ?>
                    <p>
                        User <?= $user ?> has been approved as a member of the organisation <?= $org ?>.
                        We have sent them an email to let them know.
                    </p>
                <?php endif; ?>
            </div>
            <?php
        });
});

add_action('after_setup_theme', function () {
    // Minor edits to Carbon Fields blocks in backend
    add_theme_support('editor-styles');
    add_editor_style('style-editor.css');

    \Carbon_Fields\Carbon_Fields::boot();
});

// bbpress is not compatible with block themes. It tries to find
// the old-style PHP templates (single.php, archive.php, etc), and fails.
// This filter makes bbpress use the default "Single Page" block template.
// It may be worth creating a specific template for bbpress pages and using that here.
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

add_filter('get_the_date', function ($the_date, $format, $post) {
    return $the_date;
}, 10, 3);

add_filter('render_block', function ($block_content, $block) {
    if ($block['blockName'] === 'core/heading') {
        global $post;
        $title = get_the_title($post);
        $block_content = preg_replace('#\[post_title\]#i', $title, $block_content);

        if (is_author()) {
            $author = get_queried_object();
            if ($author) {
                $author_name = $author->data->display_name ?: $author->data->user_nicename;
            } else {
                $author_name = __('Unknown author', 'awasqa');
            }
            $block_content = preg_replace('#\[author_archives_author_name\]#i', $author_name, $block_content);
        }
    }
    return $block_content;
}, 10, 2);

add_filter('term_link', function ($termlink, $term_id, $taxonomy) {
    // Categories are already handled by the "category_link" filter
    $taxonomy_name = get_taxonomy_name_from_slug($taxonomy);
    if ($taxonomy_name === 'category') {
        return $termlink;
    }
    $term = get_term($term_id, $taxonomy);
    $lang = get_current_language('en');
    $articles_page = get_translated_page_by_slug("articles", $lang);
    $taxonomy_param = get_translated_taxonomy_slug($taxonomy, $lang, $taxonomy);
    $link = get_permalink($articles_page);
    $link = add_query_arg($taxonomy_param, $term->slug, $link);
    return $link;
}, 10, 3);

add_filter("category_link", function ($termlink, $term_id) {
    $category = get_category($term_id);
    $lang = get_current_language('en');
    $articles_page = get_translated_page_by_slug("articles", $lang);
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
    $lang = get_current_language('en');
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

add_filter("query_loop_block_query_vars", function ($query) {
    global $post;

    /**
     * In this first section, if blocks DO NOT return the
     * $query, so filters can be combined. In the second
     * section, if blocks DO return the $query, so page
     * specific behaviour isn't mixed.
     */

    foreach ($_GET as $key => $value) {
        $taxonomy = get_taxonomy_name_from_slug($key);
        if ($taxonomy) {
            if (empty($query['tax_query'])) {
                $query['tax_query'] = [];
            }
            $query['tax_query'][] = [
                'taxonomy' => $taxonomy,
                'terms' => $value,
                'field' => 'slug'
            ];
        }
    }

    /**
     * From this point onwards, all the if(...) blocks
     * return the $query, to avoid mixed behaviour.
     */

    // Modify the query to get posts on an Organisation page
    // to show posts by (co-)authors of that org
    if ($post && $post->post_type === "awasqa_organisation") {
        $members = awasqa_carbon_get_post_meta($post->ID, 'members');
        $post_ids = [];
        foreach ($members as $member) {
            $author_query = new \WP_Query(['author' => $member['id']]);
            foreach ($author_query->posts as $post) {
                $post_ids[] = $post->ID;
            }
        }
        if (!$post_ids) {
            $post_ids = [0];
        }
        $query['post__in'] = $post_ids;
        # Prevent sticky posts from always appearing
        $query['ignore_sticky_posts'] = 1;
        return $query;
    }
    // Fix displaying author organisations on translated version of author archive
    if (is_author() && $query['post_type'] === "awasqa_organisation") {
        $author = get_queried_object();
        if ($author) {
            $organisations = get_author_organisations($author->ID);
            $post_ids = [];
            foreach ($organisations as $organisation) {
                $post_ids[] = $organisation->ID;
            }
            if (!$post_ids) {
                $post_ids = [0];
            }
            $query['post__in'] = $post_ids;
            # Prevent sticky posts from always appearing
            $query['ignore_sticky_posts'] = 1;
        }
        return $query;
    }
    // Fix displaying author posts on translated version of author archive
    if (is_author() && $query['post_type'] === "post") {
        $author = get_queried_object();
        if ($author) {
            $query['author'] = $author->ID;
        }
        return $query;
    }
    // Display related posts on single post page
    if (($query['s'] ?? '') === ':related') {
        $query['s'] = '';
        $post_ids = [];
        $related_posts = awasqa_carbon_get_post_meta($post->ID, 'related_posts') ?? [];
        foreach ($related_posts as $related_post) {
            $post_ids[] = $related_post['id'];
        }
        if (!$post_ids) {
            $post_ids = [0];
        }
        $query['post__in'] = $post_ids;
        $query['ignore_sticky_posts'] = 1;
        return $query;
    }
    // Filter events. To work with WPML, the filter has to be
    // done by post ID. Meta queries do not work. So first
    // a query is done to find the post IDs, then these are
    // passed to the block 'post__in' $query parameter.
    $search = $query['s'] ?? '';
    if ($search === ':upcoming' || $search === ':previous') {
        $query['s'] = '';

        $today = date('Y-m-d');
        $is_future = $search === ':upcoming';

        $meta_query = [
            'event_date' => [
                'key' => 'event_date',
                'compare' => $is_future ? '>=' : '<',
                'value' => $today
            ]
        ];

        $events = get_posts([
            'orderby' => 'event_date',
            'order' => $is_future ? 'ASC' : 'DESC',
            'post_type' => 'awasqa_event',
            'meta_query' => $meta_query
        ]);

        $post_ids = [];
        foreach ($events as $event) {
            $post_ids[] = $event->ID;
        }
        if (!$post_ids) {
            $post_ids = [0];
        }

        $query['orderby'] = 'post__in';
        $query['post__in'] = $post_ids;

        // Show essentially unlimited posts in the events query loop that
        // is not restricted to showing the single latest event.
        if ($query['posts_per_page'] > 1) {
            $query['posts_per_page'] = 999;
        }

        return $query;
    }
    return $query;
});

add_action('pre_get_posts', function ($query) {
    // Ignore special parameters in block editor
    $search = $query->get('s') ?: '';
    if (str_starts_with($search, ':')) {
        $query->set("s", "");
        return $query;
    }
});

add_filter('wpseo_title', function ($title) {
    if (is_author()) {
        $author = get_queried_object();
        if (!$author) {
            return;
        }
        $orgs = get_author_organisations($author->ID);
        if (!$orgs) {
            return $title;
        }
        $org = $orgs[0];
        $title = awasqa_get_author_name($author->ID) . ', ' . __('author at', 'awasqa') . ' ' . $org->post_title;
    }
    return $title;
});

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

/**
 * Use custom login page
 */
add_filter('login_url', function ($login_url, $redirect, $force_reauth) {
    $log_in = get_translated_page_by_slug('log-in', get_current_language('en'));
    if (!$log_in) {
        return $login_url;
    }
    $login_url = get_permalink($log_in);
    $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
    return $login_url;
}, 10, 3);

/**
 * Use custom register page
 */
add_filter('register_url', function ($register_url) {
    $register = get_translated_page_by_slug('register', get_current_language('en'));
    if (!$register) {
        return $register_url;
    }
    return get_permalink($register);
});

// Make sure translations are registered for Gravity Form registration form links
add_filter('gform_user_registration_login_args', function ($args) {
    $args['logged_in_message'] = __($args['logged_in_message'] ?? 'You are logged in!', 'awasqa');
    foreach ($args['logged_out_links'] as $i => $logged_out_link) {
        $args['logged_out_links'][$i]['text'] = __($logged_out_link['text'] ?? '', 'awasqa');
    }
    return $args;
});

add_action('template_redirect', function () {
    global $post;

    $en_page = get_en_page($post?->ID);
    $en_slug = $en_page?->post_name;

    if (!is_user_logged_in()) {
        if (is_bbpress()) {
            auth_redirect();
            exit;
        }
        $protected_pages = ['account', 'join-organisation', 'edit-organisation'];
        foreach ($protected_pages as $slug) {
            if ($slug === $en_slug) {
                auth_redirect();
                exit;
            }
        }
    } else {
        $anonymous_only_pages = ['log-in', 'register'];
        foreach ($anonymous_only_pages as $slug) {
            if ($slug === $en_slug) {
                wp_redirect(home_url());
                exit;
            }
        }
    }

    if (!empty($_GET['org']) && !empty($_GET['key'])) {
        $org_id = $_GET['org'];
        $key = $_GET['key'];
        $org = get_post($org_id);
        $user_id_to_add = get_post_meta($org_id, "awasqa_add_user_key:$key", true);
        if ($org && $user_id_to_add) {
            add_user_to_organisation($org_id, $user_id_to_add);
            notify_user_joined_org($user_id_to_add, $org);
            $user_data = get_user_by('ID', $user_id_to_add);
            $org_title = urlencode(get_the_title($org));
            wp_redirect('/success/?action=approved&user=' . $user_data->user_login . '&org=' . $org_title);
            exit();
        }
        wp_redirect(home_url());
        exit;
    }

    if ($en_slug === "account") {
        if (empty($_POST)) {
            return;
        }
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        if (!wp_verify_nonce($_POST['form-nonce'], 'awasqa_account_details_form')) {
            return;
        }
        $dislay_name = $_POST['form-name'];
        $description = $_POST['form-bio'];
        $userdata = array(
            'ID' => $user_id,
            'display_name' => $dislay_name,
            'description' => $description
        );
        wp_update_user($userdata);

        if (!empty($_FILES['form-profile-pic']['tmp_name'])) {
            $FILE = $_FILES['form-profile-pic'];

            $upload_dir = wp_upload_dir();

            $filename = $FILE['name'];

            if (wp_mkdir_p($upload_dir['path'])) {
                $dest = $upload_dir['path'] . '/' . $filename;
            } else {
                $dest = $upload_dir['basedir'] . '/' . $filename;
            }

            $tmp_filepath = $FILE['tmp_name'];

            $mimetype = mime_content_type($tmp_filepath);

            if (!$mimetype || !str_starts_with($mimetype, 'image')) {
                return;
            }

            copy($tmp_filepath, $dest);

            $attachment = array(
                'post_mime_type' => $mimetype,
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            $attach_id = wp_insert_attachment($attachment, $dest);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $dest);
            wp_update_attachment_metadata($attach_id, $attach_data);

            add_user_meta($user_id, meta_key: "awasqa_profile_pic_id", meta_value: $attach_id, unique: true);
            update_user_meta($user_id, meta_key: "awasqa_profile_pic_id", meta_value: $attach_id);
        }
    }
});


// Disable asynchronous processing if not in production
// Doesn't work in Docker because it makes a request to WP_HOME, localhost:8082,
// which fails from within the wordpress container.
add_filter('gform_is_feed_asynchronous', function ($is_asynchronous, $feed) {
    return $is_asynchronous && WP_ENV === "production";
}, 10, 2);

// Prevent subscribers from accessing the WP backend
add_action('admin_init', function () {
    if (!is_user_logged_in()) {
        return;
    }

    $roles = (array) wp_get_current_user()->roles;
    $allowed_roles = ['administrator', 'editor', 'author', 'contributor'];

    if (!array_intersect($allowed_roles, $roles)) {
        wp_die('Sorry, you are not allowed to access this page.');
    }
});

function populate_form_organisations($form)
{
    foreach ($form['fields'] as &$field) {
        if ($field->type !== 'select' || !str_contains($field->cssClass, 'awasqa-form-organisations')) {
            continue;
        }

        $posts = get_posts(['post_type' => 'awasqa_organisation']);

        $choices = array();

        $lang = get_current_language();

        // Have to filter by language because the WPML query filters are not active
        // when the Gravity Forms filters run (seemingly)
        foreach ($posts as $post) {
            $language_details = apply_filters('wpml_post_language_details', null, $post->ID);
            $post_language = $language_details['language_code'] ?? null;
            if ($post_language === $lang) {
                $choices[] = array('text' => $post->post_title, 'value' => $post->ID);
            }
        }

        // Add "New Organisation" on Register form but not Join Organisation form
        if ($form['id'] === 1) {
            $choices[] = array('text' => __('New organisation', 'awasqa'), 'value' => 'NEW');
        }

        $field->placeholder = __('No organisation', 'awasqa');
        $field->choices = $choices;
    }

    return $form;
}

// Gravity form filters work by form ID >_<
add_filter('gform_pre_render_1', 'CommonKnowledge\WordPress\Awasqa\populate_form_organisations');
add_filter('gform_pre_validation_1', 'CommonKnowledge\WordPress\Awasqa\populate_form_organisations');
add_filter('gform_pre_submission_filter_1', 'CommonKnowledge\WordPress\Awasqa\populate_form_organisations');
add_filter('gform_admin_pre_render_1', 'CommonKnowledge\WordPress\Awasqa\populate_form_organisations');

add_filter('gform_pre_render_2', 'CommonKnowledge\WordPress\Awasqa\populate_form_organisations');
add_filter('gform_pre_validation_2', 'CommonKnowledge\WordPress\Awasqa\populate_form_organisations');
add_filter('gform_pre_submission_filter_2', 'CommonKnowledge\WordPress\Awasqa\populate_form_organisations');
add_filter('gform_admin_pre_render_2', 'CommonKnowledge\WordPress\Awasqa\populate_form_organisations');

/**
 * Prefill form when rendering an Edit Organisation page
 */
add_filter('gform_pre_render_3', function ($form) {
    $org_id = $_GET['org_id'] ?? null;
    if (!$org_id) {
        return $form;
    }
    $org = get_post($org_id);
    $form['fields'][0]['defaultValue'] = $org->post_title;
    $form['fields'][1]['defaultValue'] = strip_tags(html_entity_decode($org->post_content));
    $form['fields'][2]['defaultValue'] = awasqa_carbon_get_post_meta($org_id, 'email');
    $form['fields'][3]['defaultValue'] = awasqa_carbon_get_post_meta($org_id, 'facebook');
    $form['fields'][4]['defaultValue'] = awasqa_carbon_get_post_meta($org_id, 'twitter');
    return $form;
});

/**
 * Disable no duplicates on organisation name when editing an organisation
 */
add_filter('gform_pre_validation_3', function ($form) {
    $org_id = $_GET['org_id'] ?? null;
    if (!$org_id) {
        return $form;
    }
    $org = get_post($org_id);
    if (!$org) {
        return $form;
    }
    $form['fields'][0]['noDuplicates'] = false;
    return $form;
});

function populate_form_issues($form)
{
    foreach ($form['fields'] as &$field) {
        if ($field->type !== 'select' || !str_contains($field->cssClass, 'awasqa-form-issues')) {
            continue;
        }

        $categories = get_categories(['hide_empty' => false]);

        $choices = array();

        // Have to filter by language because the WPML query filters are not active
        // when the Gravity Forms filters run (seemingly)
        foreach ($categories as $category) {
            $choices[] = array('text' => $category->name, 'value' => $category->name);
        }

        $field->choices = $choices;
    }

    return $form;
}

add_filter('gform_pre_render_5', 'CommonKnowledge\WordPress\Awasqa\populate_form_issues');
add_filter('gform_pre_validation_5', 'CommonKnowledge\WordPress\Awasqa\populate_form_issues');
add_filter('gform_pre_submission_filter_5', 'CommonKnowledge\WordPress\Awasqa\populate_form_issues');
add_filter('gform_admin_pre_render_5', 'CommonKnowledge\WordPress\Awasqa\populate_form_issues');

add_action('gform_activate_user', function ($user_id, $user_data, $user_meta) {
    $org_id = get_user_meta($user_id, 'awasqa_user_organisation', single: true);
    if ($org_id && $org_id !== 'NEW') {
        add_user_to_organisation($org_id, $user_id);
    }
    $bio = get_user_meta($user_id, 'awasqa_user_bio', single: true);
    $userdata = array(
        'ID' => $user_id,
        'description' => $bio
    );
    wp_update_user($userdata);
    wp_redirect('/success/?action=activated&user=' . $user_data['user_login']);
    exit();
}, 10, 3);

add_filter('gform_custom_merge_tags', function ($merge_tags, $form_id, $fields, $element_id) {
    $merge_tags[] = array(
        'label' => __('Add user to organisation', 'awasqa'),
        'tag'   => '{awasqa_join_organisation_url}',
    );

    $merge_tags[] = array(
        'label' => __('Admin Organisations URL', 'awasqa'),
        'tag'   => '{admin_organisations_url}',
    );

    return $merge_tags;
}, 10, 4);

/**
 * Process the {awasqa_join_organisation_url} to create a link for the admin to click
 * that will add the user to the organisation.
 */
add_filter('gform_replace_merge_tags', function ($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format) {
    $custom_merge_tag = '{awasqa_join_organisation_url}';

    if (!str_contains($text, $custom_merge_tag)) {
        return $text;
    }

    $organisation = get_post($entry[1]);

    if (!$organisation) {
        return str_replace($custom_merge_tag, '[Error: organisation not found]', $text);
    }

    // Create a key that will be used to verify the join organisation request
    $domain = home_url();
    $key = substr(md5(time() . wp_rand() . $domain), 0, 16);

    // Save the key in the organisation post meta
    $organisation_id = get_original_post_id($entry[1], $organisation->post_type);
    add_post_meta($organisation_id, "awasqa_add_user_key:$key", $entry['created_by'], true);

    $href = home_url();
    $href = add_query_arg('org', $organisation_id, $href);
    $href = add_query_arg('key', $key, $href);

    $link = '<a href="' . $href . '">' . __('click here', 'awasqa') . '</a>';
    return str_replace($custom_merge_tag, $link, $text);
}, 10, 7);

/**
 * Process the {admin_organisations_url} tag to create a link to
 * the admin organisations page.
 */
add_filter('gform_replace_merge_tags', function ($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format) {
    $custom_merge_tag = '{admin_organisations_url}';

    if (!str_contains($text, $custom_merge_tag)) {
        return $text;
    }

    $href = admin_url('/edit.php?post_type=awasqa_organisation&lang=all;');
    $link = '<a href="' . $href . '">' . __('Go to the admin site.', 'awasqa') . '</a>';
    return str_replace($custom_merge_tag, $link, $text);
}, 10, 7);

/**
 * Add metadata to the post and set the correct language after it
 * has been created by Gravity Forms.
 */
add_action(
    'gform_advancedpostcreation_post_after_creation',
    function ($post_id, $feed, $entry, $form) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $source_post_id = null;
        if ($post->post_type === "awasqa_organisation") {
            $email = $entry[6];
            $facebook_url = $entry[7];
            $twitter_url = $entry[8];

            carbon_set_post_meta($post_id, 'email', $email);
            carbon_set_post_meta($post_id, 'twitter', $twitter_url);
            carbon_set_post_meta($post_id, 'facebook', $facebook_url);

            $source_post_id = $entry[9];

            add_user_to_organisation($post_id, $entry['created_by']);
        }

        if ($post->post_type === "post") {
            $source_post_id = $entry[4];
            $pdfs = get_attached_media('application/pdf', $post->ID);
            foreach ($pdfs as $pdf) {
                $post->post_content .= (
                    "<!-- wp:paragraph -->" .
                    "[pdfjs-viewer url=" . wp_get_attachment_url($pdf->ID) . " " .
                    "viewer_width=600px viewer_height=700px fullscreen=true download=true print=true]" .
                    "\n" .
                    "<!-- /wp:paragraph -->"
                );
            }
            wp_update_post($post);
        }

        // ID of the post that had the form on it - used to determine lang of new post
        if ($source_post_id) {
            $language_details = apply_filters('wpml_post_language_details', null, $source_post_id);
            $source_lang = $language_details['language_code'];

            // Update the language of the post
            $trid = apply_filters('wpml_element_trid', null, $post_id, 'post_' . $post->post_type);
            $language_args = [
                'element_id' => $post_id,
                'element_type' => 'post_' . $post->post_type,
                'trid' => $trid,
                'language_code' => $source_lang,
                'source_language_code' => null,
            ];

            do_action('wpml_set_element_language_details', $language_args);
        }
    },
    10,
    4
);

/**
 * Make the Edit Organisation form support editing as well as creating new organisations.
 * Check the submitting user is an author for that org, then delete the new post ($post parameter)
 * and return the Organisation post.
 */
add_filter('gform_advancedpostcreation_post', function ($post, $feed, $entry, $form) {
    if ($post['post_type'] === 'awasqa_organisation') {
        // Make post content block friendly
        $post_content_lines = explode('<br />', $post['post_content']);
        $post_content = '';
        foreach ($post_content_lines as $line) {
            $line = trim($line);
            if ($line) {
                $post_content .=  "<!-- wp:paragraph -->" . $line . "\n" . "<!-- /wp:paragraph -->";
            }
        }
        $post['post_content'] = $post_content;
    }

    // Update existing organisation if ?org_id= is included
    $org_id = $_GET['org_id'] ?? null;
    if (!$org_id) {
        return $post;
    }
    $org = get_post($org_id);
    if (!$org || $org->post_type !== "awasqa_organisation") {
        return $post;
    }
    $members = awasqa_carbon_get_post_meta($org->ID, 'members');
    $is_member = false;
    foreach ($members as $member) {
        if ($member['id'] == $entry['created_by']) {
            $is_member = true;
            break;
        }
    }
    if (!$is_member) {
        header('HTTP/1.0 403 Forbidden');
        die('Forbidden.');
    }
    wp_delete_post($post['ID']);
    $post['ID'] = $org->ID;
    $post['post_status'] = $org->post_status;
    return $post;
}, 10, 4);

function post_published($post)
{
    if ($post->post_type === 'awasqa_organisation') {
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        $href = get_permalink($post);
        $link = '<a href"' . $href . '">' . $href . '</a>';

        wp_mail(
            $author->user_email,
            __('Your organisation', 'awasqa') . ' ' . $post->post_title . ' ' . __('has been approved.', 'awasqa'),
            __('Thank you for registering on Awasqa. View the organisation here: ') . $link,
            headers: ['From: ' => get_admin_email_from_address()]
        );
    } elseif ($post->post_type === 'post') {
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        $href = get_permalink($post);
        $link = '<a href"' . $href . '">' . $href . '</a>';

        wp_mail(
            $author->user_email,
            __('Your article', 'awasqa') . ' ' . $post->post_title . ' ' . __('has been approved.', 'awasqa'),
            __('View the article here: ') . $link,
            headers: ['From: ' => get_admin_email_from_address()]
        );
    }
}

add_action('draft_to_publish', 'CommonKnowledge\WordPress\Awasqa\post_published', 10, 1);
add_action('future_to_publish', 'CommonKnowledge\WordPress\Awasqa\post_published', 10, 1);
add_action('private_to_publish', 'CommonKnowledge\WordPress\Awasqa\post_published', 10, 1);