<?php

namespace CommonKnowledge\WordPress\Awasqa\Authors;

use CommonKnowledge\WordPress\Awasqa;

use function CommonKnowledge\WordPress\Awasqa\WPML\get_current_language;

function awasqa_get_coauthors($post_id)
{
    $post = get_post($post_id);
    if (!$post) {
        return null;
    }
    $original_post_id = Awasqa\WPML\get_original_post_id($post->ID, $post->post_type);
    return get_coauthors($original_post_id);
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
        'posts_per_page' => -1
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

function handle_update_profile_pic($user_id)
{
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
        $protected_pages = ['account', 'join-organisation', 'edit-organisation', 'submit-article', 'submit-event'];
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
        $display_name = $_POST['form-name'];
        $description = $_POST['form-bio'];
        $userdata = array(
            'ID' => $user_id,
            'nickname' => $display_name,
            'display_name' => $display_name,
            'description' => $description
        );
        wp_update_user($userdata);

        handle_update_profile_pic($user_id);
    }
});

/**
 * Use custom login page
 */
add_filter('login_url', function ($login_url, $redirect, $force_reauth) {
    $path = $_SERVER['REQUEST_URI'] ?? "/";
    $is_admin = $_GET['admin'] ?? false;
    if ($path === "/wp/wp-login.php" && $is_admin) {
        return $login_url;
    }
    if (str_contains($redirect, "wp-admin")) {
        return $login_url;
    }
    $log_in = Awasqa\WPML\get_translated_page_by_slug('log-in', Awasqa\WPML\get_current_language('en'));
    if (!$log_in) {
        return $login_url;
    }
    $login_url = get_permalink($log_in);
    $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
    return $login_url;
}, 10, 3);

/**
 * Detect 2FA errors and redirect to standard WP login page
 * (the gravity forms page does not work with 2FA).
 */
add_filter('authenticate', function ($user, $username, $password) {
    if ($user instanceof \WP_Error) {
        if ($user->get_error_code() === 'wfls_twofactor_required') {
            $redirect = $_GET["redirect_to"] ?? "/";
            $login_url = "/wp/wp-login.php?admin=true";
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
            wp_redirect($login_url);
            exit;
        }
    }
    return $user;
}, 26, 3);


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

function extra_admin_user_fields($user)
{
    global $wpdb;

    $user_id = $user->ID;
    $meta = get_user_meta($user_id);
    $image_id = $meta['awasqa_profile_pic_id'][0] ?? 0;
    $image_url = $image_id ? wp_get_attachment_image_src($image_id) : null;

    $sql = $wpdb->prepare(
        'SELECT id FROM wp_icl_strings WHERE context=%s AND name=%s',
        "Authors",
        "description_" . $user_id
    );
    $cols = $wpdb->get_col($sql);
    $string_id = $cols[0] ?? "";
    $hide_user = (bool) get_user_meta($user_id, 'awasqa-hide-user', true);
    $hide_user_checked = $hide_user ? 'checked="checked"' : '';
    ?>
    <h2>Awasqa</h2>
    <table class="form-table">
        <tr>
            <th>Ocultar Autor</th>
            <td>
                <input id="awasqa-hide-user" type="checkbox" name="awasqa-hide-user" value="1" <?= $hide_user_checked ?>>
                <label for="awasqa-hide-user">Ocultar Autor</label>
            </td>
        </tr>
    </table>
    <table class="form-table">
        <tr>
            <th><label for="translated-bio"><?= __('Bio en Ingles', 'awasqa') ?></label></th>
            <td>
                <input type="hidden" name="form-translated-bio-string-id" value="<?= $string_id ?>">
                <textarea id="translated-bio" name="form-translated-bio" rows="5" cols="30"><?= get_translated_author_bio($user_id, 'es') ?></textarea>
            </td>
        </tr>
    </table>
    <table class="form-table">
        <tr>
            <th><label for="profile-pic"><?= __('Profile pic', 'awasqa') ?></label></th>
            <?php if ($image_url) : ?>
                <td>
                    <img width="100px" height="100px" style="object-fit: contain" src="<?= $image_url[0] ?>">
                </td>
            <?php endif; ?>
            <td>
                <input id="profile-pic" name="form-profile-pic" type="file">
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'CommonKnowledge\WordPress\Awasqa\Authors\extra_admin_user_fields');
add_action('edit_user_profile', 'CommonKnowledge\WordPress\Awasqa\Authors\extra_admin_user_fields');
add_action('user_edit_form_tag', function () {
    echo 'enctype="multipart/form-data"';
});

function awasqa_save_admin_user_fields($user_id)
{
    if (current_user_can('edit_user', $user_id)) {
        handle_update_profile_pic($user_id);

        // Hide author
        $hide_user = $_POST['awasqa-hide-user'] ?? 0;
        update_user_meta($user_id, 'awasqa-hide-user', $hide_user);

        // Add translated bio
        $spanish_description = $_POST['description'] ?? null;
        $string_id = $_POST['form-translated-bio-string-id'] ?? null;
        $english_description = $_POST['form-translated-bio'] ?? null;
        if ($string_id) {
            if ($spanish_description) {
                icl_add_string_translation($string_id, 'es', $spanish_description, ICL_TM_COMPLETE);
            }
            if ($english_description) {
                icl_add_string_translation($string_id, 'en', $english_description, ICL_TM_COMPLETE);
            }
        }
    }
}
add_action('personal_options_update', 'CommonKnowledge\WordPress\Awasqa\Authors\awasqa_save_admin_user_fields');
add_action('edit_user_profile_update', 'CommonKnowledge\WordPress\Awasqa\Authors\awasqa_save_admin_user_fields');

// Hide built-in avatar field in wordpress admin pages
add_filter("option_show_avatars", function ($val) {
    return !is_admin();
});

function add_avatar_column($column)
{
    $column['profile_pic'] = __('Profile pic', 'awasqa');
    return $column;
}
add_filter('manage_users_columns', 'CommonKnowledge\WordPress\Awasqa\Authors\add_avatar_column');

function do_avatar_column($val, $column_name, $user_id)
{
    switch ($column_name) {
        case 'profile_pic':
            $meta = get_user_meta($user_id);
            $image_id = $meta['awasqa_profile_pic_id'][0] ?? 0;
            $image_url = $image_id ? wp_get_attachment_image_src($image_id) : null;
            if ($image_url) {
                return '<img width="100px" height="100px" style="object-fit: contain" src="' . $image_url[0] . '">';
            }
            return $val;
        default:
    }
    return $val;
}
add_filter('manage_users_custom_column', 'CommonKnowledge\WordPress\Awasqa\Authors\do_avatar_column', 10, 3);

function get_translated_author_bio($author_id, $lang = null)
{
    global $wpdb;

    $description = get_user_meta($author_id, 'description', true);

    $sql = "SELECT id FROM wp_icl_strings WHERE context='Authors' AND name='description_{$author_id}' LIMIT 1;";
    $string_id = $wpdb->get_col($sql);
    if (!$string_id) {
        return $description;
    }

    $lang = $lang ?? get_current_language('es');

    $translations = icl_get_string_translations_by_id($string_id[0]);
    return $translations[$lang]['value'] ?? $description;
}

add_filter('coauthors_edit_author_cap', function ($capabilities) {
    return 'read';
});
