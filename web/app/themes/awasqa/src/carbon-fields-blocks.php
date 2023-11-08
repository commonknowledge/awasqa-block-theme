<?php

namespace CommonKnowledge\WordPress\Awasqa\CarbonFieldsBlocks;

use Carbon_Fields\Block;
use Carbon_Fields\Field;
use CommonKnowledge\WordPress\Awasqa;

use function CommonKnowledge\WordPress\Awasqa\CarbonFields\awasqa_carbon_get_post_meta;

add_action('carbon_fields_register_fields', function () {
    Block::make('Post Source')
        ->set_icon('search')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Post Source', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $post = get_post();
            $source_url = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($post->ID, 'source_url');
            $source_publication = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($post->ID, 'source_publication');
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
                'taxonomy'   => 'awasqa_country'
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
                $language = Awasqa\WPML\get_current_language('en');
                $translated_slug = Awasqa\Taxonomies\get_translated_taxonomy_slug('awasqa_country', $language, 'country');

                $current_url = parse_url($_SERVER['REQUEST_URI']);

                $query = '';
                $locale = Awasqa\WPML\get_current_language();
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

                $issue_param = Awasqa\Taxonomies\get_translated_taxonomy_slug('category', $locale, 'category');
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
                ->add_options('CommonKnowledge\WordPress\Awasqa\CarbonFieldsBlocks\get_issue_options')
                ->set_default_value("uncategorized")
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $category_slug = $fields['category'];
            $category = get_category_by_slug($category_slug);
            ?>
        <a class="awasqa-issue-link" href="<?= get_category_link($category) ?>">
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
            $authors = Awasqa\Authors\awasqa_get_coauthors($post->ID) ?? [];
            $organisations = [];
            $author_data = [];
            foreach ($authors as $author) {
                // Skip admin
                if ($author->ID == 1) {
                    continue;
                }
                $name = Awasqa\Authors\awasqa_get_author_name($author->ID);
                $author_data[] = [
                    "link" => get_author_posts_url($author->ID),
                    "name" => $name
                ];
            }
            $primary_author = $post->post_author;
            $organisations = [];
            $related_orgs = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($post->ID, 'related_organisations');
            if ($related_orgs) {
                $org_ids = array_map(function ($related_org) {
                    return $related_org['id'];
                }, $related_orgs);
                $organisations = get_posts([
                    "posts_per_page" => -1,
                    "post_type" => "awasqa_organisation",
                    "post__in" => $org_ids
                ]);
            }
            // Fallback to author organisation if there is only one
            if (!$organisations) {
                $author_organisations = Awasqa\Authors\get_author_organisations($primary_author);
                if (count($author_organisations) === 1) {
                    $organisations = $author_organisations;
                }
            }
            foreach ($organisations as $org) {
                $author_data[] = [
                    "link" => get_permalink($org),
                    "name" => get_the_title($org)
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

    Block::make('All Authors')
        ->set_icon('groups')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('All Authors', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $user_ids = get_users(['orderby' => 'name', 'fields' => 'ID']);
            $authors_data = [];
            foreach ($user_ids as $author_id) {
                $meta = get_user_meta($author_id);
                $hide_user = (bool) get_user_meta($author_id, 'awasqa-hide-user', true);
                if ($hide_user) {
                    continue;
                }
                $image_id = $meta['awasqa_profile_pic_id'][0] ?? null;
                $image_url = $image_id ? wp_get_attachment_image_src($image_id) : null;
                $name = Awasqa\Authors\awasqa_get_author_name($author_id);
                $authors_data[] = [
                    "link" => get_author_posts_url($author_id),
                    "name" => $name,
                    "bio" => Awasqa\Authors\get_translated_author_bio($author_id),
                    "image_url" => $image_url[0] ?? null
                ];
            }
            ?>
        <ul class="awasqa-all-authors">
            <?php foreach ($authors_data as $author_data) : ?>
                <li>
                    <?= Awasqa\Authors\render_author_column($author_data, show_visit_link: true); ?>
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
                $orgs = Awasqa\Authors\get_author_organisations($author->ID);
                if (!$orgs) {
                    return;
                }
                $org = $orgs[0];
            } else {
                $org = get_post();
            }
            $twitter = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($org->ID, 'twitter');
            $facebook = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($org->ID, 'facebook');
            $instagram = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($org->ID, 'instagram');
            $youtube = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($org->ID, 'youtube');
            ?>
        <div class="awasqa-org-contact-details">
            <?php if ($twitter || $facebook || $instagram || $youtube) : ?>
                <h3><?= __('Social Media', 'awasqa') ?></h3>
                <?php if ($twitter) : ?>
                    <a class="awasqa-org-contact-details__twitter" href="<?= $twitter ?>" target="_blank">
                        Twitter
                    </a>
                <?php endif; ?>
                <?php if ($facebook) : ?>
                    <a class="awasqa-org-contact-details__facebook" href="<?= $facebook ?>" target="_blank">
                        Facebook
                    </a>
                <?php endif; ?>
                <?php if ($instagram) : ?>
                    <a class="awasqa-org-contact-details__instagram" href="<?= $instagram ?>" target="_blank">
                        Instagram
                    </a>
                <?php endif; ?>
                <?php if ($youtube) : ?>
                    <a class="awasqa-org-contact-details__youtube" href="<?= $youtube ?>" target="_blank">
                        YouTube
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
            $members = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($post->ID, 'members');
            $author_data = [];
            foreach ($members as $member) {
                $user_id = $member['id'];
                $meta = get_user_meta($user_id);
                $name = Awasqa\Authors\awasqa_get_author_name($user_id);
                $image_id = $meta['awasqa_profile_pic_id'][0] ?? 0;
                $image_url = $image_id ? wp_get_attachment_image_src($image_id) : null;
                $bio = Awasqa\Authors\get_translated_author_bio($user_id);
                $author_data[] = [
                    "link" => get_author_posts_url($user_id),
                    "name" => $name,
                    "bio" => $bio,
                    "image_url" => $image_url[0] ?? null
                ];
            }
            if (!$author_data) {
                ?>
            <ul class="awasqa-organisation-authors"></ul>
                <?php
            }
            ?>
        <ul class="awasqa-organisation-authors">
            <?php foreach ($author_data as $author) : ?>
                <li>
                    <?php Awasqa\Authors\render_author_column($author); ?>
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
                "bio" => Awasqa\Authors\get_translated_author_bio($author_id),
                "image_url" => $image_url[0] ?? null
            ];

            Awasqa\Authors\render_author_column($author_data, show_visit_link: false);
        });

    Block::make('Authors Column')
        ->set_icon('groups')
        ->add_fields(array(
            Field::make('separator', 'crb_separator', __('Authors Column', 'awasqa'))
        ))
        ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
            $post = get_post();
            $authors = Awasqa\Authors\awasqa_get_coauthors($post->ID);
            if (!$authors) {
                return;
            }
            $authors_data = [];
            foreach ($authors as $author) {
                // Skip admin
                if ($author->ID == 1) {
                    continue;
                }
                $name = Awasqa\Authors\awasqa_get_author_name($author->ID);
                $meta = get_user_meta($author->ID);
                $image_id = $meta['awasqa_profile_pic_id'][0] ?? null;
                $image_url = $image_id ? wp_get_attachment_image_src($image_id) : null;
                $authors_data[] = [
                    "link" => get_author_posts_url($author->ID),
                    "name" => $name,
                    "bio" => Awasqa\Authors\get_translated_author_bio($author->ID),
                    "image_url" => $image_url[0] ?? null
                ];
            }
            if ($authors_data) {
                ?>
            <div class="awasqa-authors-column">
                <h6><?= __('Authors', 'awasqa') ?></h6>
                <?php
                foreach ($authors_data as $author_data) {
                    Awasqa\Authors\render_author_column($author_data, show_visit_link: true);
                }
                ?>
            </div>
                <?php
            }
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
            $event_date = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($event->ID, 'event_date');
            // Default to 6pm to prevent events with no specified time having the wrong date displayed in Western timezones
            // E.G. Mexico is up to UTC-7, so if the default were midnight, the date would go back one day when displayed
            // in a Mexican user's locale
            $event_time = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($event->ID, 'event_time') ?: '18:00:00';
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
                <p><?= sprintf(__("User %s has been activated. We have sent them an email to let them know.", "awasqa"), $user) ?></p>
            <?php endif; ?>
            <?php if ($action === "approved") : ?>
                <p>
                    <?php
                    echo sprintf(
                        __(
                            "User %s has been approved as a member of the organisation %s. " .
                                "We have sent them an email to let them know.",
                            "awasqa"
                        ),
                        $user,
                        $org
                    )
                    ?>
                </p>
            <?php endif; ?>
        </div>
            <?php
        });
});
