<?php

namespace CommonKnowledge\WordPress\Awasqa\GravityForms;

use CommonKnowledge\WordPress\Awasqa;

// Make sure translations are registered for Gravity Form registration form links
add_filter('gform_user_registration_login_args', function ($args) {
    $args['logged_in_message'] = __($args['logged_in_message'] ?? 'You are logged in!', 'awasqa');
    foreach ($args['logged_out_links'] as $i => $logged_out_link) {
        $args['logged_out_links'][$i]['text'] = __($logged_out_link['text'] ?? '', 'awasqa');
    }
    return $args;
});

// Disable asynchronous processing if not in production
// Doesn't work in Docker because it makes a request to WP_HOME, localhost:8082,
// which fails from within the wordpress container.
add_filter('gform_is_feed_asynchronous', function ($is_asynchronous, $feed) {
    return $is_asynchronous && WP_ENV === "production";
}, 10, 2);

function populate_form_organisations($form)
{
    foreach ($form['fields'] as &$field) {
        if ($field->type !== 'select' || !str_contains($field->cssClass, 'awasqa-form-organisations')) {
            continue;
        }

        $posts = get_posts(['post_type' => 'awasqa_organisation']);

        $choices = array();

        $lang = Awasqa\WPML\get_current_language();

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
add_filter('gform_pre_render_1', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_organisations');
add_filter('gform_pre_validation_1', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_organisations');
add_filter('gform_pre_submission_filter_1', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_organisations');
add_filter('gform_admin_pre_render_1', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_organisations');

add_filter('gform_pre_render_2', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_organisations');
add_filter('gform_pre_validation_2', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_organisations');
add_filter('gform_pre_submission_filter_2', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_organisations');
add_filter('gform_admin_pre_render_2', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_organisations');

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
    $form['fields'][2]['defaultValue'] = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($org_id, 'email');
    $form['fields'][4]['defaultValue'] = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($org_id, 'facebook');
    $form['fields'][5]['defaultValue'] = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($org_id, 'twitter');
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

        foreach ($categories as $category) {
            $choices[] = array('text' => $category->name, 'value' => $category->name);
        }

        $field->choices = $choices;
    }

    return $form;
}

add_filter('gform_pre_render_5', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_issues');
add_filter('gform_pre_validation_5', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_issues');
add_filter('gform_pre_submission_filter_5', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_issues');
add_filter('gform_admin_pre_render_5', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_issues');

function populate_form_countries($form)
{
    foreach ($form['fields'] as &$field) {
        if ($field->type !== 'select' || !str_contains($field->cssClass, 'awasqa-form-countries')) {
            continue;
        }

        $countries = get_terms([
            'taxonomy'   => 'awasqa_country',
            'hide_empty' => false
        ]);

        $choices = array();

        foreach ($countries as $country) {
            $choices[] = array('text' => $country->name, 'value' => $country->name);
        }

        $field->choices = $choices;
    }

    return $form;
}

add_filter('gform_pre_render_5', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_countries');
add_filter('gform_pre_validation_5', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_countries');
add_filter('gform_pre_submission_filter_5', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_countries');
add_filter('gform_admin_pre_render_5', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_countries');

add_filter('gform_pre_render_7', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_countries');
add_filter('gform_pre_validation_7', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_countries');
add_filter('gform_pre_submission_filter_7', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_countries');
add_filter('gform_admin_pre_render_7', 'CommonKnowledge\WordPress\Awasqa\GravityForms\populate_form_countries');

add_action('gform_activate_user', function ($user_id, $user_data, $user_meta) {
    $org_id = get_user_meta($user_id, 'awasqa_user_organisation', single: true);
    if ($org_id && $org_id !== 'NEW') {
        Awasqa\CarbonFields\add_user_to_organisation($org_id, $user_id);
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
    $orig_org_id = $organisation->ID;

    if (!$organisation) {
        return str_replace($custom_merge_tag, '[Error: organisation not found]', $text);
    }

    // Create a key that will be used to verify the join organisation request
    $domain = home_url();
    $key = substr(md5(time() . wp_rand() . $domain), 0, 16);

    // Save the key in the organisation post meta
    $organisation_id = Awasqa\WPML\get_original_post_id($entry[1], $organisation->post_type);
    add_post_meta($organisation_id, "awasqa_add_user_key:$key", $entry['created_by'], true);

    $href = home_url();
    $href = add_query_arg('org', $organisation_id, $href);
    $href = add_query_arg('orig_org', $orig_org_id, $href);
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

            Awasqa\CarbonFields\add_user_to_organisation($post_id, $entry['created_by']);
        }

        if ($post->post_type === "awasqa_event") {
            $source_post_id = $entry[4];
            $date_time = $entry[11];
            if ($date_time) {
                $date_time_parts = explode('T', $date_time);
                if (count($date_time_parts) === 2) {
                    $date = $date_time_parts[0];
                    $time = explode('.', $date_time_parts[1])[0];
                    carbon_set_post_meta($post->ID, 'event_date', $date);
                    carbon_set_post_meta($post->ID, 'event_time', $time);
                }
            }
        }

        if ($post->post_type === "post") {
            $source_post_id = $entry[4];
            $mp3s = get_attached_media('audio/mpeg', $post->ID);
            foreach ($mp3s as $mp3) {
                $src = wp_get_attachment_url($mp3->ID);
                $post->post_content .= ('<!-- wp:heading -->' .
                '<h2 class="wp-block-heading">' . __('Listen now:', 'awasqa') . '</h2>' .
                '<!-- /wp:heading -->'
                );

                $post->post_content .= ('<!-- wp:audio {"id":' . $mp3->ID . '} -->' .
                    '<figure class="wp-block-audio">' .
                    '    <audio controls src="' . $src . '"></audio>' .
                    '</figure>' .
                    '<!-- /wp:audio -->'
                );
            }
            wp_update_post($post);

            $source_publication = $entry[11];
            $source_url = $entry[12];

            carbon_set_post_meta($post_id, 'source_publication', $source_publication);
            carbon_set_post_meta($post_id, 'source_url', $source_url);
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
    $members = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($org->ID, 'members');
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
