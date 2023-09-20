<?php

use Carbon_Fields\Block;
use Carbon_Fields\Field;

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

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('awasqa', get_template_directory_uri() . '/style.css');
    wp_enqueue_script(
        'awasqa-pre',
        get_template_directory_uri() . '/pre-script.js'
    );
    wp_enqueue_script(
        'awasqa-post',
        get_template_directory_uri() . '/script.js',
        ver: false,
        args: true // in_footer = true
    );
});

add_action('carbon_fields_register_fields', function () {
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
                    <a href="/country/<?= $country->slug ?>/">
                        <?= $country->name ?>
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
                ->add_options('get_issue_options')
                ->set_default_value("uncategorized")
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $category_slug = $fields['category'];
            $category = get_category_by_slug($category_slug);
            $posts = get_posts(["category_name" => $category_slug, "num_posts" => 4]);
            ?>
        <a class="awasqa-issue-link" href="/category/<?= $category->slug ?>">
            <span class="awasqa-issue-link__title"><?= $category->name ?></span>
            <span class="awasqa-issue-link__more">More</span>
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
                $author_data[] = [
                    "link" => get_author_posts_url($author->ID),
                    "name" => get_the_author_meta("user_nicename", $author->ID)
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
