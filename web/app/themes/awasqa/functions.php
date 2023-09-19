<?php

use Carbon_Fields\Block;
use Carbon_Fields\Field;

add_action('init', function () {
    register_taxonomy('awasqa_country', ['post'], [
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
        return get_query_template('single');
    }
    return $template;
});
