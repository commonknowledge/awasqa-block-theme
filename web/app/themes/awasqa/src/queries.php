<?php

namespace CommonKnowledge\WordPress\Awasqa\Queries;

use CommonKnowledge\WordPress\Awasqa;

/**
 * Return the post IDs associated to an organisation.
 * Have to iterate through all posts, as meta queries
 * don't work with translated posts.
 */
function get_org_post_ids($org_id)
{
    $query = new \WP_Query([
        'post_type' => 'post',
    ]);
    $posts = $query->posts ?: [];
    $org_post_ids = [];
    foreach ($posts as $post) {
        $orgs = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($post->ID, 'related_organisations') ?? [];
        $matching_org = array_filter($orgs, function ($org) use ($org_id) {
            return (string) $org['id'] === (string) $org_id;
        });
        if ($matching_org) {
            $org_post_ids[] = $post->ID;
        }
    }
    return $org_post_ids;
}

add_filter("query_loop_block_query_vars", function ($query) {
    global $post;

    /**
     * In this first section, if blocks DO NOT return the
     * $query, so filters can be combined. In the second
     * section, if blocks DO return the $query, so page
     * specific behaviour isn't mixed.
     */

    foreach ($_GET as $key => $value) {
        $taxonomy = Awasqa\Taxonomies\get_taxonomy_name_from_slug($key);
        if ($taxonomy) {
            if (empty($query['tax_query'])) {
                $query['tax_query'] = [];
            }
            $query['tax_query'][] = [
                'taxonomy' => $taxonomy,
                'terms' => $value,
                'field' => 'slug'
            ];
        }
    }

    /**
     * From this point onwards, all the if(...) blocks
     * return the $query, to avoid mixed behaviour.
     */

    // Modify the query to get posts on an Organisation page
    // to show posts by (co-)authors of that org
    if ($post && $post->post_type === "awasqa_organisation") {
        $members = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($post->ID, 'members');
        $post_ids = [];
        foreach ($members as $member) {
            $author_query = new \WP_Query(['author' => $member['id']]);
            foreach ($author_query->posts as $post) {
                $post_ids[] = $post->ID;
            }
        }

        $org_post_ids = get_org_post_ids($post->ID);
        $post_ids = array_merge($post_ids, $org_post_ids);

        if (!$post_ids) {
            $post_ids = [0];
        }
        $query['post__in'] = $post_ids;
        # Prevent sticky posts from always appearing
        $query['ignore_sticky_posts'] = 1;
        return $query;
    }
    // Display related orgs on post/event page
    if (is_single() && $post &&
        in_array($post->post_type, ["post", "awasqa_event"]) &&
        $query['post_type'] === "awasqa_organisation"
    ) {
        $author = $post->post_author;
        $org_ids = [];
        $related_orgs = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($post->ID, 'related_organisations');
        if ($related_orgs) {
            $org_ids = array_map(function ($related_org) {
                return $related_org['id'];
            }, $related_orgs);
        }
        // Fallback to author organisation if there is only one
        if (!$org_ids) {
            $author_organisations = Awasqa\Authors\get_author_organisations($author);
            if (count($author_organisations) === 1) {
                $org_ids = [$author_organisations[0]->ID];
            }
        }
        if (!$org_ids) {
            $org_ids = [0];
        }
        $query['post__in'] = $org_ids;
        # Prevent sticky posts from always appearing
        $query['ignore_sticky_posts'] = 1;
        return $query;
    }
    // Fix displaying author organisations on translated version of author archive
    if (is_author() && $query['post_type'] === "awasqa_organisation") {
        $author = get_queried_object();
        if ($author) {
            $organisations = Awasqa\Authors\get_author_organisations($author->ID);
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
        return $query;
    }
    // Fix displaying author posts on translated version of author archive
    if (is_author() && $query['post_type'] === "post") {
        $author = get_queried_object();
        if ($author) {
            $query['author'] = $author->ID;
        }
        return $query;
    }
    // Display related posts on single post page
    if (($query['s'] ?? '') === ':related') {
        $query['s'] = '';
        $query['post_type'] = ['post', 'awasqa_event'];
        $post_ids = [];
        $related_posts = Awasqa\CarbonFields\awasqa_carbon_get_post_meta($post->ID, 'related_posts') ?? [];
        foreach ($related_posts as $related_post) {
            $post_ids[] = $related_post['id'];
        }
        if (!$post_ids) {
            $post_ids = [0];
        }
        $query['post__in'] = $post_ids;
        $query['ignore_sticky_posts'] = 1;
        return $query;
    }
    // Filter events. To work with WPML, the filter has to be
    // done by post ID. Meta queries do not work. So first
    // a query is done to find the post IDs, then these are
    // passed to the block 'post__in' $query parameter.
    $search = $query['s'] ?? '';
    if ($search === ':upcoming' || $search === ':previous') {
        $query['s'] = '';

        $today = date('Y-m-d');
        $is_future = $search === ':upcoming';

        $meta_query = [
            'event_date' => [
                'key' => 'event_date',
                'compare' => $is_future ? '>=' : '<',
                'value' => $today
            ]
        ];

        $events = get_posts([
            'orderby' => 'event_date',
            'order' => $is_future ? 'ASC' : 'DESC',
            'post_type' => 'awasqa_event',
            'meta_query' => $meta_query
        ]);

        $post_ids = [];
        foreach ($events as $event) {
            $post_ids[] = $event->ID;
        }
        if (!$post_ids) {
            $post_ids = [0];
        }

        $query['orderby'] = 'post__in';
        $query['post__in'] = $post_ids;

        // Show essentially unlimited posts in the events query loop that
        // is not restricted to showing the single latest event.
        if ($query['posts_per_page'] > 1) {
            $query['posts_per_page'] = 999;
        }

        return $query;
    }
    return $query;
});

add_action('pre_get_posts', function ($query) {
    // Ignore special parameters in block editor
    $search = $query->get('s') ?: '';
    if (str_starts_with($search, ':')) {
        $query->set("s", "");
        return $query;
    }
});
