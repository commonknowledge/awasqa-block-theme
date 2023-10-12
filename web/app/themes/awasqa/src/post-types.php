<?php

namespace CommonKnowledge\WordPress\Awasqa\PostTypes;

add_action('init', function () {
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
