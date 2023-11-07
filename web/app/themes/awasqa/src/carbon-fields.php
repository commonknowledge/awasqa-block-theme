<?php

namespace CommonKnowledge\WordPress\Awasqa\CarbonFields;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

use CommonKnowledge\WordPress\Awasqa;

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
    $original_post_id = Awasqa\WPML\get_original_post_id($post->ID, $post->post_type);
    return carbon_get_post_meta($original_post_id, $name, $container_id);
}

function add_user_to_organisation($org_id, $user_id)
{
    $original_org_id = Awasqa\WPML\get_original_post_id($org_id, 'awasqa_organisation');
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

add_action('carbon_fields_register_fields', function () {
    Container::make('post_meta', 'Source')
        ->where('post_type', '=', 'post')
        ->add_fields(array(
            Field::make('text', 'source_publication', 'Source Publication Name'),
            Field::make('text', 'source_url', 'Source URL')->set_attribute('type', 'url'),
        ));

    Container::make('post_meta', 'Related Organisations and Posts')
        ->where('post_type', 'IN', ['post', 'awasqa_event'])
        ->add_fields(array(
            Field::make('association', 'related_organisations', 'Organisations')
                ->set_types([
                    [
                        'type'      => 'post',
                        'post_type' => 'awasqa_organisation'
                    ],
                ]),
            Field::make('association', 'related_posts', 'Posts')
                ->set_types([
                    [
                        'type'      => 'post',
                        'post_type' => 'post'
                    ],
                    [
                        'type'      => 'post',
                        'post_type' => 'awasqa_event'
                    ]
                ])
        ));

    Container::make('post_meta', 'Contact')
        ->where('post_type', '=', 'awasqa_organisation')
        ->add_fields(array(
            Field::make('text', 'twitter', 'Twitter URL')->set_attribute('type', 'url'),
            Field::make('text', 'facebook', 'Facebook URL')->set_attribute('type', 'url'),
            Field::make('text', 'instagram', 'Instagram URL')->set_attribute('type', 'url'),
            Field::make('text', 'youtube', 'Youtube URL')->set_attribute('type', 'url'),
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
});

add_action('after_setup_theme', function () {
    // Minor edits to Carbon Fields blocks in backend
    add_theme_support('editor-styles');
    add_editor_style('style-editor.css');

    \Carbon_Fields\Carbon_Fields::boot();
});
