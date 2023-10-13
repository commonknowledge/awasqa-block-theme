<?php

namespace CommonKnowledge\WordPress\Awasqa\Emails;

use CommonKnowledge\WordPress\Awasqa;

function get_admin_email_from_address()
{
    $site_url = home_url();
    $parsed_url = parse_url($site_url);
    return 'admin@' . $parsed_url['host'];
}

function notify_user_joined_org($user_id, $org)
{
    $orig_language = Awasqa\WPML\get_current_language('en');

    $user = get_userdata($user_id);
    $language_details = apply_filters('wpml_post_language_details', null, $org->ID);
    $post_language = $language_details['language_code'] ?? 'en';
    $account = Awasqa\WPML\get_translated_page_by_slug('account', $post_language);

    do_action('wpml_switch_language', $post_language);

    $href = get_permalink($account);
    $from = get_admin_email_from_address();

    wp_mail(
        $user->user_email,
        __('Your request to join', 'awasqa') . ' ' . $org->post_title . ' ' . __('has been approved.', 'awasqa'),
        __('Thank you for registering on Awasqa. View your account here: ', 'awasqa') . $href,
        headers: ['From: ' => $from]
    );

    do_action('wpml_switch_language', $orig_language);
}

// Notify users when their post is published
function post_published($post)
{
    $orig_language = Awasqa\WPML\get_current_language('en');

    $language_details = apply_filters('wpml_post_language_details', null, $post->ID);
    $post_language = $language_details['language_code'] ?? 'en';

    do_action('wpml_switch_language', $post_language);

    if ($post->post_type === 'awasqa_organisation') {
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        $href = get_permalink($post);

        wp_mail(
            $author->user_email,
            __('Your organisation', 'awasqa') . ' ' . $post->post_title . ' ' . __('has been approved.', 'awasqa'),
            __('Thank you for registering on Awasqa. View the organisation here: ', 'awasqa') . $href,
            headers: ['From: ' => get_admin_email_from_address()]
        );
    } elseif ($post->post_type === 'post') {
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        $href = get_permalink($post);

        wp_mail(
            $author->user_email,
            __('Your article', 'awasqa') . ' ' . $post->post_title . ' ' . __('has been approved.', 'awasqa'),
            __('View the article here: ', 'awasqa') . $href,
            headers: ['From: ' => get_admin_email_from_address()]
        );
    } elseif ($post->post_type === 'awasqa_event') {
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        $href = get_permalink($post);

        wp_mail(
            $author->user_email,
            __('Your event', 'awasqa') . ' ' . $post->post_title . ' ' . __('has been approved.', 'awasqa'),
            __('View the event here: ', 'awasqa') . $href,
            headers: ['From: ' => get_admin_email_from_address()]
        );
    }

    do_action('wpml_switch_language', $orig_language);
}

add_action('draft_to_publish', 'CommonKnowledge\WordPress\Awasqa\Emails\post_published', 10, 1);
add_action('future_to_publish', 'CommonKnowledge\WordPress\Awasqa\Emails\post_published', 10, 1);
add_action('private_to_publish', 'CommonKnowledge\WordPress\Awasqa\Emails\post_published', 10, 1);
