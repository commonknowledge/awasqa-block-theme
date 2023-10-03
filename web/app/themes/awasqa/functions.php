<?php

namespace CommonKnowledge\WordPress\Awasqa;

use Carbon_Fields\Block;
use Carbon_Fields\Container;
use Carbon_Fields\Field;

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
                <?= __('Visit author profile') ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
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

/**
 * Required to convert the post ID to the English post, that has the metadata
 */
function awasqa_carbon_get_post_meta($post_id, $name, $container_id = '')
{
    $en_page = get_en_page($post_id);
    $id = $en_page ? $en_page->ID : $post_id;
    return carbon_get_post_meta($id, $name, $container_id);
}

add_action('init', function () {
    register_taxonomy('awasqa_country', ['post', 'awasqa_organisation'], [
        'hierarchical'      => false,
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

    Block::make(__('Post Source'))
        ->set_icon('search')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Post Source'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $post = get_post();
            $source_url = awasqa_carbon_get_post_meta($post->ID, 'source_url');
            $source_publication = awasqa_carbon_get_post_meta($post->ID, 'source_publication');
            $text_parts = [];
            if ($source_publication) {
                $text_parts[] = __('This article was originally published in ') . $source_publication . '.';
            }
            if ($source_url) {
                $text_parts[] = __('Click link below to see the original article.');
            }
            if (!$text_parts) {
                return;
            }
            ?>
        <div class="awasqa-post-source">
            <h6><?= __('Source') ?></h6>
            <p><?= implode("", $text_parts) ?></p>
            <?php if ($source_url) : ?>
                <p>
                    <a target="_blank" href="<?= $source_url ?>" class="awasqa-link-arrow">
                        <?= __('Visit original article') ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
            <?php
        });

    Block::make(__('Countries List'))
        ->set_icon('admin-site')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Countries List'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $countries = get_terms([
                'taxonomy'   => 'awasqa_country',
                'hide_empty' => false,
            ]);
            ?>
        <ul class="wp-block-categories">
            <?php foreach ($countries as $country) : ?>
                <li>
                    <a href="<?= get_term_link($country->name, 'awasqa_country') ?>">
                        <?= __($country->name) ?>
                    </a>
                </li>
            <?php endforeach; ?>
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

    Block::make(__('Issue Link'))
        ->set_icon('category')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Issue Link')),
            Field::make('select', 'category', __('Issue'))
                ->add_options('CommonKnowledge\WordPress\Awasqa\get_issue_options')
                ->set_default_value("uncategorized")
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $category_slug = $fields['category'];
            $category = get_category_by_slug($category_slug);
            $posts = get_posts(["category_name" => $category_slug, "num_posts" => 4]);
            ?>
        <a class="awasqa-issue-link" href="/category/<?= $category->slug ?>">
            <span class="awasqa-issue-link__title"><?= $category->name ?></span>
            <span class="awasqa-issue-link__more"><?= __('More') ?></span>
            <img src="/app/themes/awasqa/assets/images/arrow-right.svg">
        </a>
            <?php
        });

    Block::make(__('Authors'))
        ->set_icon('groups')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Authors'))
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

    Block::make(__('Organisation Contact Details'))
        ->set_icon('megaphone')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Organisation Contact Details'))
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
            <?php if ($email || $twitter || $facebook) : ?>
            <div class="awasqa-org-contact-details">
                <?php if ($email) : ?>
                    <h3><?= __('Email') ?></h3>
                    <a class="awasqa-org-contact-details__email" href="mailto:<?= $email ?>">
                        <?= $email ?>
                    </a>
                <?php endif; ?>
                <?php if ($twitter || $facebook) : ?>
                    <h3><?= __('Social Media') ?></h3>
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
            <?php endif; ?>

            <?php
        });

    Block::make(__('Organisation Authors'))
        ->set_icon('groups')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Organisation Authors'))
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

    Block::make(__('Author Column'))
        ->set_icon('admin-users')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Author Column'))
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

    Block::make(__('Authors Column'))
        ->set_icon('groups')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Authors Column'))
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
            <h6><?= __('Authors') ?></h6>
            <?php
            foreach ($authors_data as $author_data) {
                render_author_column($author_data, show_visit_link: true);
            }
            ?>
        </div>
            <?php
        });

    Block::make(__('Account Details Form'))
        ->set_icon('admin-users')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Account Details Form'))
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
                <label for="name"><?= __('Name') ?></label>
                <input id="name" name="form-name" type="text" value="<?= $name ?>">
            </div>
            <div class="awasqa-account-details-form__row">
                <label for="profile-pic"><?= __('Profile pic') ?></label>
                <?php if ($image_url) : ?>
                    <img src="<?= $image_url[0] ?>">
                <?php endif; ?>
                <input id="profile-pic" name="form-profile-pic" type="file">
            </div>
            <div class="awasqa-account-details-form__row">
                <label for="bio"><?= __('Bio') ?></label>
                <textarea id="bio" name="form-bio"><?= $bio ?></textarea>
            </div>
            <button><?= __('Submit') ?></button>
        </form>
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
                $author_name = __('Unknown author');
            }
            $block_content = preg_replace('#\[author_archives_author_name\]#i', $author_name, $block_content);
        }
    }
    return $block_content;
}, 10, 2);

add_filter("query_loop_block_query_vars", function ($query) {
    global $post;
    // Modify the query to get posts on an Organisation page
    // to restrict posts to those associated with a (co-)author of that org
    if ($post->post_type === "awasqa_organisation") {
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
    }
    // Fix displaying author posts on translated version of author archive
    if (is_author() && $query['post_type'] === "post") {
        $author = get_queried_object();
        if ($author) {
            $query['author'] = $author->ID;
        }
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
    }
    return $query;
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
        $title = awasqa_get_author_name($author->ID) . ', ' . __('author at') . ' ' . $org->post_title;
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

add_action('template_redirect', function () {
    if (!is_user_logged_in()) {
        $protected_paths = ['/account', '/forums'];
        foreach ($protected_paths as $path) {
            if (str_starts_with($_SERVER['REQUEST_URI'], $path)) {
                auth_redirect();
                exit;
            }
        }
    }

    global $post;
    $en_page = get_en_page($post?->ID);
    $slug = $en_page ? $en_page->post_name : null;
    if ($slug === "account") {
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
        }
    }
});
