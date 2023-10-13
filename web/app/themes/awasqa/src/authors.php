<?php

namespace CommonKnowledge\WordPress\Awasqa\Authors;

use CommonKnowledge\WordPress\Awasqa;

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
        $authors = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($org->ID, 'members');
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

// Add auth control of protected pages
// Add handling of submitting user data and approving users
add_action('template_redirect', function () {
    global $post;

    $en_page = Awasqa\WPML\get_en_page($post?->ID);
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
            $orig_org_id = $_GET['orig_org'] ?? $_GET['org'];
            Awasqa\CarbonFields\add_user_to_organisation($org_id, $user_id_to_add);
            Awasqa\Emails\notify_user_joined_org($user_id_to_add, $orig_org_id);
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

/**
 * Use custom login page
 */
add_filter('login_url', function ($login_url, $redirect, $force_reauth) {
    $log_in = Awasqa\WPML\get_translated_page_by_slug('log-in', Awasqa\WPML\get_current_language('en'));
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
    $register = Awasqa\WPML\get_translated_page_by_slug('register', Awasqa\WPML\get_current_language('en'));
    if (!$register) {
        return $register_url;
    }
    return get_permalink($register);
});

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

add_action('after_setup_theme', function () {
    if (!current_user_can('administrator') && !is_admin()) {
        show_admin_bar(false);
    }
});

// Improve title on Author archives page
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
