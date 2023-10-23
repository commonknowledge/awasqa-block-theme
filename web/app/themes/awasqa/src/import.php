<?php

namespace CommonKnowledge\WordPress\Awasqa\Import;

use CommonKnowledge\WordPress\Awasqa;
use SimpleXMLElement;

if (!class_exists("WP_CLI")) {
    return;
}

define('XMLNS_WP', 'http://wordpress.org/export/1.2/');
define('XMLNS_CONTENT', "http://purl.org/rss/1.0/modules/content/");

global $author_is_org;
$author_is_org = [
    "ACAPANA" => true,
    "APIB - Articulação dos Povos Indígenas do Brasil" => true,
    "Airam Fernández" => false,
    "Alejandro Argumedo" => false,
    "All My Relations" => true,
    "Amarela Varela Huerta" => false,
    "Ana María Morales Troya" => false,
    "Andrés Tapia" => false,
    "Ariadna Solís" => false,
    "Articulação Nacional das Mulheres Guerreiras da Ancestralidade" => true,
    "Asamblea Originaria por la Descolonizacion y la Plurinacionalidad (ASODEPLU)" => true,
    "Ashley McCray" => false,
    "Asociación Interétnica de Desarrollo de la Selva Peruana" => true,
    "Aura Cumes" => false,
    "Aurora Guadalupe Catalán Reyes" => true,
    "Beatriz Cortez Sánchez" => false,
    "Ben Jacklet" => false,
    "Brent Patterson, Peace Brigades International-Canada" => false,
    "Build Back Fossil Free" => true,
    "CONAIE Comunicación" => true,
    "Catherine Windey" => false,
    "Chuck Rosina" => false,
    "Comisión Nacional de Comunicación Indígena, CONCIP" => true,
    "Compromisos por el clima Bolivia" => true,
    "Congreso Nacional Indígena" => true,
    "CooperAcción" => true,
    "Coordenação das Organizações Indígenas da Amazônia Brasileira (COIAB)" => true,
    "Cuencas Sagradas" => true,
    "Devin Beaulieu" => false,
    "Diana Raquel Vela Almeida" => false,
    "Dina Gilio-Whitaker" => false,
    "Duane Brayboy" => false,
    "Earthjustice.org" => true,
    "Eduardo Gudynas" => false,
    "Educa Oaxaca" => true,
    "Elda Mizraim Fernández Acosta" => false,
    "Elizabeth Hoover" => false,
    "Erly Guedes" => false,
    "Eva López Chávez" => false,
    "FGER" => true,
    "Fabiana Bringas" => false,
    "Ficwallmapu" => true,
    "Fundación Colectivo Epew" => true,
    "Gabriela Linares Sosa" => false,
    "Gert Van Hecken" => false,
    "Gidimt'en Clan" => true,
    "Gobierno Territorial Autónomo de la Nación Wampís" => true,
    "Honor the Earth" => true,
    "Indira Vargas" => false,
    "Instituto Socioambiental" => true,
    "Interethnic Association for the Development of the Peruvian Rainforest" => true,
    "Inuit Circumpolar Council (ICC)" => true,
    "Inés Ixierda" => false,
    "Jaime Borda" => false,
    "Kalyn Belsha" => false,
    "Kevin McCann" => false,
    "Km.169 Prensa Comunitaria" => true,
    "Last Real Indians" => true,
    "Leidy Yareth González Romero" => false,
    "Marcos Aguilar" => false,
    "María Luna López" => false,
    "Melissa Moreano" => false,
    "Mesa Regional Permanente de Concertación - Pastos y Quillasingas" => true,
    "Midia Ninja" => false,
    "Moira Millán" => false,
    "Movimiento de mujeres indigenas por el buen vivir" => true,
    "NCAI National Congress of American Indians" => true,
    "NDN Collective" => true,
    "Naomi Klein" => false,
    "National Indigenous Women’s Resource Center, Inc. (NIWRC)" => true,
    "Natália Loyola de Macedo" => false,
    "Nazshonnii Brown-Almaweri" => false,
    "Nicolas Kosoy" => false,
    "Norma Alicia Palma Aguirre" => false,
    "Organización Fraternal Negra Hondureña (OFRANEH)" => true,
    "Organización Fraternal Negra Hondureña (OFRANEH) ES" => true,
    "Pacto Ecosocial del Sur" => true,
    "Parlamento de la Naciones Indígenas de la Amazonía, Oriente y Chaco, PNIAOC" => true,
    "Patricia Yallico" => false,
    "Pedro Uc Be" => false,
    "Pennie Opal Plant" => true,
    "Periódico Fewla" => true,
    "Protect Thacker Pass Campaign" => true,
    "Radio Temblor" => true,
    "Rafael Bautista" => false,
    "Red Muqei" => true,
    "Red Muqui" => true,
    "Robin Wall Kimmerer" => false,
    "Sallisa Rosa" => false,
    "Salvador Quishpe Lozano" => false,
    "Sarah Rose Harper and Jesse Phelps" => false,
    "Servindi" => true,
    "Silvia Riveiro" => false,
    "Sitalin Sánchez" => false,
    "Sofía Jarrín" => false,
    "Soledad Álvarez Velasco" => false,
    "Soluciones Prácticas" => true,
    "Sonja Swift" => false,
    "The Red Road Project" => true,
    "The Voice of the Peoples of the popular revolt to the constitutional assembly" => true,
    "Tk’emlúps te Secwépemc" => true,
    "Txai Suruí" => false,
    "Tzam: Las Trece Semillas Zapatistas" => true,
    "Urban Indian Health Institute" => true,
    "Verónica Yuquilema Yupangui" => false,
    "Vijay Kolinjivadi" => false,
    "Vocería de los Pueblos de la revuelta popular a la constituyente" => true,
    "Water Protector Legal Collective" => true,
    "Yásnaya Aguilar" => false,
];

global $manual_file_fixes;
$manual_file_fixes = [
    "Wetsuweten-support-Laufowzad-pic.jpg" => "2020/02/Wetsuweten-support-Laufowzad-pic-e1582216482602.jpg",
    "VIII-Encuentro-ECMIA-1024x546.jpg" => "2020/03/VIII-Encuentro-ECMIA-1024x545.jpg",
    "SonjaSwiftProfile-300x300-1-300x300.jpg" => "2020/05/SonjaSwiftProfile-300x300-1.jpg",
    "EZigthfWsAk1Mr8-1024x682.jpg" => "2020/06/EZigthfWsAk1Mr8.jpg",
    "photo_2021-06-07_09-20-00-1024x574.jpg" => "2021/06/photo_2021-06-07_09-20-00.jpg",
    "photo_2021-06-07_09-20-18-1024x574.jpg" => "2021/06/photo_2021-06-07_09-20-18.jpg",
    "photo_2021-06-07_09-20-41-1024x574.jpg" => "2021/06/photo_2021-06-07_09-20-41.jpg",
];

global $author_name_replacements;
$author_name_replacements = [
    "Brent Patterson, Peace Brigades International-Canada" => "Brent Patterson"
];

global $guest_authors_to_skip;
$guest_authors_to_skip = ["Sofía Jarrín" => get_user_by("slug", "sofia")];

global $wp_users_to_skip;
$wp_users_to_skip = ["sofia-jarrin" => get_user_by("slug", "sofia")];

global $manual_translations;
$manual_translations = [
    'the-voice-of-the-peoples-of-the-popular-revolt-to-the-constitutional-assembly' =>
    'voceria-de-los-pueblos-de-la-revuelta-popular-a-la-constituyente'
];

global $attachment_ids_by_orig_id;
$attachment_ids_by_orig_id = [];

global $attachment_paths_by_filename;
$attachment_paths_by_filename = [];

global $user_ids_by_wp_user_id;
$user_ids_by_wp_user_id = [];

global $user_ids_by_guest_author_id;
$user_ids_by_guest_author_id = [];

global $org_ids_by_guest_author_id;
$org_ids_by_guest_author_id = [];

function import()
{
    $xml_string = file_get_contents(__DIR__ . "/../../../../../awasqa.org.xml");
    $xml = new SimpleXMLElement($xml_string);
    $channel = $xml->channel[0];
    $posts_by_type = [
        "post" => [],
        "attachment" => [],
        "guest_author" => []
    ];
    foreach ($channel->children(XMLNS_WP)->author as $user) {
        import_wp_user($user);
    }
    foreach ($channel->item as $item) {
        $wp_props = $item->children(XMLNS_WP);
        $post_type = (string) $wp_props->post_type;
        if (!in_array($post_type, ["post", "attachment", "guest_author"])) {
            continue;
        }
        if ($post_type !== "attachment") {
            $post_name = strip_lang((string) $wp_props->post_name);
        } else {
            $post_name = (string) $wp_props->post_name;
        }

        $posts = null;
        if ($post_type === "post") {
            $posts = $posts_by_type["post"];
        }
        if ($post_type === "attachment") {
            $posts = $posts_by_type["attachment"];
        }
        if ($post_type === "guest_author") {
            $posts = $posts_by_type["guest_author"];
        }
        if ($posts !== null) {
            $posts_by_slug = $posts[$post_name] ?? [];
            $posts_by_slug[] = $item;
            $posts[$post_name] = $posts_by_slug;
            $posts_by_type[$post_type] = $posts;
        }
    }

    global $translated_posts_by_type;
    $translated_posts_by_type = [
        "post" => [],
        "attachment" => [],
        "guest_author" => []
    ];

    $untranslated_posts_by_type = [
        "post" => [],
        "attachment" => [],
        "guest_author" => []
    ];

    foreach ($posts_by_type as $post_type => $posts_by_slug) {
        foreach ($posts_by_slug as $slug => $posts) {
            if (count($posts) === 2) {
                $translated_posts_by_type[$post_type][$slug] = $posts;
                continue;
            }
            // Only radio-temblor has > 2 $posts and the first 2 are best
            if (count($posts) > 2) {
                $translated_posts_by_type[$post_type][$slug] = array_slice($posts, 0, 2);
                continue;
            }
            $post = $posts[0];
            $translation_key = get_translation_key($slug, $post);
            if ($translation_key) {
                $translations = $translated_posts_by_type[$post_type][$translation_key] ?? [];
                $translations[] = $post;
                $translated_posts_by_type[$post_type][$translation_key] = $translations;
            } else {
                $untranslated_posts_by_type[$post_type][$slug] = $post;
            }
        }
    }

    foreach ($posts_by_type['attachment'] as $slug => $attachments) {
        import_attachment($slug, $attachments);
    }

    populate_attachment_paths_by_filename();

    foreach ($posts_by_type['guest_author'] as $slug => $authors) {
        import_guest_author($slug, $authors);
    }

    foreach ($posts_by_type['post'] as $slug => $posts) {
        import_post($slug, $posts);
    }
}

function import_wp_user($user_xml)
{
    global $user_ids_by_wp_user_id;
    global $wp_users_to_skip;

    $wp_children = $user_xml->children(XMLNS_WP);
    $orig_id = (string) $wp_children->author_id;
    $username = (string) $wp_children->author_login;
    if (array_key_exists($username, $wp_users_to_skip)) {
        $user_ids_by_wp_user_id[$orig_id] = $wp_users_to_skip[$username]->ID;
        return;
    }
    $existing_user = get_user_by("slug", $username);
    if ($existing_user) {
        $user_ids_by_wp_user_id[$orig_id] = $existing_user->ID;
        return;
    }
    $user_data = [
        "user_login" => $username,
        "user_nicename" => $username,
        "user_email" => (string) $wp_children->author_email,
        "user_pass" => wp_generate_password(),
        "nickname" => (string) $wp_children->author_display_name,
        "display_name" => (string) $wp_children->author_display_name,
        "role" => "admin",
        "locale" => "es"
    ];
    $user_id = wp_insert_user($user_data);
    $user_ids_by_wp_user_id[$orig_id] = $user_id;
    echo "Created user " . $username . "\n";
}

function get_translation_key($slug, $post_xml)
{
    global $manual_translations;
    global $translated_posts_by_type;
    foreach ($translated_posts_by_type as $type => $translated_posts_by_key) {
        if (array_key_exists($slug, $translated_posts_by_key)) {
            return $slug;
        }
    }
    if (array_key_exists($slug, $manual_translations)) {
        $translation_key = $manual_translations[$slug];
    } else {
        $translation_key = get_category($post_xml, "post_translations");
    }
    return $translation_key;
}

function strip_lang($post_name)
{
    $post_name = preg_replace('#-[0-9]$#', '', $post_name);
    $post_name = preg_replace('#-es$#', '', $post_name);
    $post_name = preg_replace('#-en$#', '', $post_name);
    return $post_name;
}

function get_category($post_xml, $category_name, $is_single = true)
{
    $found_categories = [];
    $categories = $post_xml->category ?? [];
    foreach ($categories as $category) {
        $category_domain = get_attribute($category, "domain");
        if ($category_domain === $category_name) {
            $category = get_attribute($category, "nicename") ?? (string) $category;
            if ($is_single) {
                return $category;
            }
            $found_categories[] = $category;
        }
    }
    return $is_single ? null : $found_categories;
}

function get_attribute($xml, $attr)
{
    foreach ($xml->attributes() as $name => $value) {
        if ($name === $attr) {
            return (string) $value;
        }
    }
    return null;
}

function get_post_meta($xml, $name, $single = true)
{
    $values = [];
    $metas = $xml->children(XMLNS_WP)->postmeta;
    foreach ($metas as $meta) {
        $key = (string) $meta->children(XMLNS_WP)->meta_key;
        if ($key === $name) {
            $value = (string) $meta->children(XMLNS_WP)->meta_value;
            if ($single) {
                return $value;
            }
            $values[] = $value;
        }
    }
    return $single ? null : $values;
}

function import_attachment($slug, $attachment_xmls)
{
    global $attachment_ids_by_orig_id;

    foreach ($attachment_xmls as $attachment_xml) {
        $wp_props = $attachment_xml->children(XMLNS_WP);

        $post_name = (string) $wp_props->post_name;

        $url = (string) $wp_props->attachment_url;
        $url_parts = explode("/", $url);

        $path = get_post_meta($attachment_xml, "_wp_attached_file");
        $path_parts = explode("/", $path);
        if (count($path_parts) !== 3) {
            $filename = array_pop($url_parts);

            $post_date = (string) $wp_props->post_date;

            $date = explode(" ", $post_date)[0];
            list($year, $month, $day) = explode("-", $date);
            $path = "$year/$month/$filename";
        }

        $full_path = __DIR__ . "/../../../uploads/" . $path;
        if (!file_exists($full_path)) {
            $full_path = remove_accents($full_path);
            if (!file_exists($full_path)) {
                $path_parts = pathinfo($full_path);
                $full_path = $path_parts['dirname'] . "/" . $path_parts['filename'] . '-1.' . $path_parts['extension'];
                if (!file_exists($full_path)) {
                    if ($path_parts['extension'] === 'jpg') {
                        $full_path = $path_parts['dirname'] . "/" . $path_parts['filename'] . '.png';
                    }
                    if (!file_exists($full_path)) {
                        // Can't find these files anywhere :(
                        $skip = [
                            "fiestas-indigenas.mp3",
                            "Indira-Vargas-Manual-de-Plantas-1.mp3"
                        ];
                        if (!in_array($path_parts["basename"], $skip)) {
                            echo "MISSING FILE " . $path . "\n";
                            exit(0);
                        }
                    }
                }
            }
        }
        $path = explode("uploads/", $full_path)[1];

        $attachment = get_posts([
            "post_type" => "attachment",
            "name" => $post_name
        ]);

        if ($attachment) {
            $attachment_id = $attachment[0]->ID;
        } else {
            $attachment_id = wp_insert_attachment([
                "post_name" => (string) $wp_props->post_name,
                "post_title" => (string) $attachment_xml->title,
                "post_mime_type" => "import"
            ], $path);

            $alt = get_post_meta($attachment_xml, "_wp_attachment_image_alt") ?: "";

            add_post_meta($attachment_id, "_wp_attachment_image_alt", $alt, true);
            update_post_meta($attachment_id, "_wp_attachment_image_alt", $alt);
        }

        $attachment_ids_by_orig_id[(string) $wp_props->post_id] = $attachment_id;
        echo "imported attachment " . $path . "\n";
    }
}

function populate_attachment_paths_by_filename()
{
    global $attachment_paths_by_filename;
    $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . "/../../../uploads"));
    $files = array();

    /** @var SplFileInfo $file */
    foreach ($rii as $file) {
        if ($file->isDir()) {
            continue;
        }
        $path = '/app/uploads/' . explode("/uploads/", $file->getPathname())[1];
        $attachment_paths_by_filename[$file->getFilename()] = $path;
    }
}

function is_original_post($post_type, $slug, $post_xml)
{
    global $translated_posts_by_type;
    $post_id = (string) $post_xml->children(XMLNS_WP)->post_id;
    $translation_key = get_translation_key($slug, $post_xml);
    $translations = $translated_posts_by_type[$post_type][$translation_key] ?? null;
    if (!$translations) {
        return true;
    }

    // Always make the Spanish post the original
    $spanish_translation = null;
    foreach ($translations as $translation) {
        $language = get_category($translation, "language");
        if ($language === "es") {
            $spanish_translation = $translation;
        }
    }

    if ($spanish_translation) {
        $translation_id = (string) $spanish_translation->children(XMLNS_WP)->post_id;
        return $translation_id === $post_id;
    }

    // Default to the first post being original
    $ids = array_map(function ($translation) {
        return (string) $translation->children(XMLNS_WP)->post_id;
    }, $translations);
    sort($ids);
    return $post_id === $ids[0];
}

function import_guest_author($slug, $author_xmls)
{
    global $author_is_org;
    $author_xml = $author_xmls[0];
    $name = (string) $author_xml->title;
    $is_org = $author_is_org[$name];
    if ($is_org) {
        import_org($slug, $author_xmls);
    } else {
        import_user($slug, $author_xmls);
    }
}

function import_org($slug, $org_xmls)
{
    global $attachment_ids_by_orig_id;
    global $org_ids_by_guest_author_id;

    if (count($org_xmls) === 1) {
        $original_org = $org_xmls[0];
        $translated_org = null;
    } else if (is_original_post("guest_author", $slug, $org_xmls[0])) {
        $original_org = $org_xmls[0];
        $translated_org = $org_xmls[1];
    } else {
        $original_org = $org_xmls[1];
        $translated_org = $org_xmls[0];
    }

    $post_title = (string) $original_org->title;
    $post_type = "awasqa_organisation";

    $post_name = (string) $original_org->children(XMLNS_WP)->post_name;
    $org = get_posts([
        "post_type" => $post_type,
        "name" => $post_name
    ]);

    if ($org) {
        $orig_id = (string) $original_org->children(XMLNS_WP)->post_id;
        $org_ids_by_guest_author_id[$orig_id] = $org[0]->ID;
        return;
    }

    $org_id = wp_insert_post([
        "post_title" => $post_title,
        "post_name" => $post_name,
        "post_content" => (string) $original_org->children(XMLNS_CONTENT)->encoded,
        "post_type" => $post_type,
        "post_status" => "publish"
    ]);

    $attachment_id = null;
    $featured_image_id = get_post_meta($original_org, "_thumbnail_id");
    if ($featured_image_id) {
        $attachment_id = $attachment_ids_by_orig_id[$featured_image_id];
        add_post_meta($org_id, "_thumbnail_id", $attachment_id, true);
        update_post_meta($org_id, "_thumbnail_id", $attachment_id);
    }

    $orig_id = (string) $original_org->children(XMLNS_WP)->post_id;
    $org_ids_by_guest_author_id[$orig_id] = $org_id;

    $orig_language = get_category($original_org, "language");

    // Update the language of the original post
    $trid = apply_filters('wpml_element_trid', null, $org_id, 'post_' . $post_type);
    $language_args = [
        'element_id' => $org_id,
        'element_type' => 'post_' . $post_type,
        'trid' => $trid,
        'language_code' => $orig_language,
        'source_language_code' => null,
    ];

    do_action('wpml_set_element_language_details', $language_args);

    if ($translated_org) {
        $trans_post_title = (string) $translated_org->title;
        $trans_language = get_category($translated_org, "language");
        $trans_slug = (string) $translated_org->children(XMLNS_WP)->post_name;
        if ($trans_slug === $slug) {
            $trans_slug = $slug . "-" . $trans_language;
        }

        $trans_org_id = wp_insert_post([
            "post_title" => $trans_post_title,
            "post_name" => $trans_slug,
            "post_content" => (string) $translated_org->children(XMLNS_CONTENT)->encoded,
            "post_type" => $post_type,
            "post_status" => "publish"
        ]);

        if ($attachment_id) {
            $attachment_id = $attachment_ids_by_orig_id[$featured_image_id];
            add_post_meta($trans_org_id, "_thumbnail_id", $attachment_id, true);
            update_post_meta($trans_org_id, "_thumbnail_id", $attachment_id);
        }

        global $org_ids_by_guest_author_id;
        $trans_id = (string) $translated_org->children(XMLNS_WP)->post_id;
        $org_ids_by_guest_author_id[$trans_id] = $org_id;

        // Update the language of the translated post
        $language_args = [
            'element_id' => $trans_org_id,
            'element_type' => 'post_' . $post_type,
            'trid' => $trid,
            'language_code' => $trans_language,
            'source_language_code' => $orig_language,
        ];

        do_action('wpml_set_element_language_details', $language_args);
    }
}

function import_post($slug, $post_xmls)
{
    global $attachment_ids_by_orig_id;

    if (count($post_xmls) === 1) {
        $original_post = $post_xmls[0];
        $translated_post = null;
    } else if (is_original_post("post", $slug, $post_xmls[0])) {
        $original_post = $post_xmls[0];
        $translated_post = $post_xmls[1];
    } else {
        $original_post = $post_xmls[1];
        $translated_post = $post_xmls[0];
    }

    $post_title = (string) $original_post->title;
    $post_type = "post";

    $post_content = fix_content((string) $original_post->children(XMLNS_CONTENT)->encoded);

    $post_name = (string) $original_post->children(XMLNS_WP)->post_name;
    $post = get_posts([
        "post_type" => "post",
        "name" => $post_name
    ]);

    if ($post) {
        //return;
    }

    $post_id = wp_insert_post([
        "post_title" => $post_title,
        "post_name" => $post_name,
        "post_content" => $post_content,
        "post_type" => $post_type,
        "post_status" => "publish"
    ]);

    $attachment_id = null;
    $featured_image_id = get_post_meta($original_post, "_thumbnail_id");
    if ($featured_image_id) {
        $attachment_id = $attachment_ids_by_orig_id[$featured_image_id];
        add_post_meta($post_id, "_thumbnail_id", $attachment_id, true);
        update_post_meta($post_id, "_thumbnail_id", $attachment_id);
    }

    $orig_language = get_category($original_post, "language");

    // Update the language of the original post
    $trid = apply_filters('wpml_element_trid', null, $post_id, 'post_' . $post_type);
    $language_args = [
        'element_id' => $post_id,
        'element_type' => 'post_' . $post_type,
        'trid' => $trid,
        'language_code' => $orig_language,
        'source_language_code' => null,
    ];

    do_action('wpml_set_element_language_details', $language_args);

    global $user_ids_by_guest_author_id;
    global $user_ids_by_wp_user_id;
    global $org_ids_by_guest_author_id;
    global $coauthors_plus;
    $authors = get_post_meta($original_post, "_molongui_author", single: false);
    foreach ($authors as $author) {
        if (str_starts_with($author, "user-")) {
            $id = explode("user-", $author)[1];
            $new_author_id = $user_ids_by_wp_user_id[$id];
            $coauthors_plus->add_coauthors($post_id, [$new_author_id], false, "id");
        } elseif (str_starts_with($author, "guest-")) {
            $id = explode("guest-", $author)[1];
            $new_author_id = $user_ids_by_guest_author_id[$id] ?? null;
            $new_org_id = $org_ids_by_guest_author_id[$id] ?? null;

            if ($new_author_id) {
                $coauthors_plus->add_coauthors($post_id, [$new_author_id], false, "id");
            }
            if ($new_org_id) {
                $orgs = carbon_get_post_meta($post_id, "related_organisations") ?? [];
                $orgs[] = [
                    "value" => "post:awasqa_organisation:" . $new_org_id,
                    "type" => "post",
                    "subtype" => "awasqa_organisation",
                    "id" => $new_org_id
                ];
                carbon_set_post_meta($post_id, "related_organisations", $orgs);
            }
            if ($new_author_id && $new_org_id) {
                echo "Error: could not determine if author is person or org.";
                exit(1);
            }
        }
    }

    $categories = get_post_category_ids($original_post, $orig_language);
    if ($categories) {
        wp_set_post_categories($post_id, $categories);
    }

    if ($translated_post) {
        $trans_post_title = (string) $translated_post->title;
        $trans_language = get_category($translated_post, "language");
        $trans_slug = (string) $translated_post->children(XMLNS_WP)->post_name;
        if ($trans_slug === $slug) {
            $trans_slug = $slug . "-" . $trans_language;
        }

        $translated_content = fix_content((string) $translated_post->children(XMLNS_CONTENT)->encoded);

        $trans_post_id = wp_insert_post([
            "post_title" => $trans_post_title,
            "post_name" => $trans_slug,
            "post_content" => $translated_content,
            "post_type" => $post_type,
            "post_status" => "publish"
        ]);

        if ($attachment_id) {
            $attachment_id = $attachment_ids_by_orig_id[$featured_image_id];
            add_post_meta($trans_post_id, "_thumbnail_id", $attachment_id, true);
            update_post_meta($trans_post_id, "_thumbnail_id", $attachment_id);
        }

        // Update the language of the translated post
        $language_args = [
            'element_id' => $trans_post_id,
            'element_type' => 'post_' . $post_type,
            'trid' => $trid,
            'language_code' => $trans_language,
            'source_language_code' => $orig_language,
        ];

        do_action('wpml_set_element_language_details', $language_args);

        $categories = get_post_category_ids($translated_post, $trans_language);
        wp_set_post_categories($trans_post_id, $categories);
    }

    echo "Imported " . $post_name . "\n";
}

function get_post_category_ids($post_xml, $language)
{
    $cat_names = [];
    $post_categories = [];
    $category_names = get_category($post_xml, "category", is_single: false);
    foreach ($category_names as $category_name) {
        $is_arts = in_array($category_name, ['arts-and-culture', 'arte-y-cultura']);
        $is_science = in_array($category_name, ['ciencia', 'science']);
        $is_health = in_array($category_name, ['salud', 'health']);
        $slug = null;
        if ($is_arts) {
            $slug = $language === "en" ? 'arts-and-culture' : 'arte-y-cultura';
        } else if ($is_science) {
            $slug = $language === "en" ? 'science' : 'ciencia';
        } else if ($is_health) {
            $slug = $language === "en" ? 'health' : 'salud';
        }
        if (!$slug) {
            continue;
        }
        $cat_names[] = $slug;
        $category = get_category_by_slug($slug);
        if (!$category) {
            echo "NO CATEGORY " . $slug . "\n";
            exit(0);
        }
        $post_categories[] = $category->term_id;
    }
    return $post_categories;
}

// Replace WordPress uploads with relative paths
function fix_content($content)
{
    global $attachment_paths_by_filename;
    global $manual_file_fixes;
    preg_match_all('#src="([^"]+)"#', $content, $matches);
    $urls = $matches[1];
    foreach ($urls as $url) {
        $parsed_url = parse_url($url);
        $host = $parsed_url["host"];
        if (in_array($host, [
            "greennetworkproject.org",
            "awasqa.org"
        ]) || !$host) {
            $path = preg_replace('#^/?wp-content/#', '/app/', $parsed_url["path"]);
            $has_no_folders = !preg_match('#/app/uploads/[0-9]{4}/[0-9]{2}/#', $path);
            if ($has_no_folders) {
                $path_parts = explode('/', $path);
                $filename = array_pop($path_parts);
                if (array_key_exists($filename, $manual_file_fixes)) {
                    $path = "/app/uploads/" . $manual_file_fixes[$filename];
                } else if (!array_key_exists($filename, $attachment_paths_by_filename)) {
                    $filename_parts = explode('.', $filename);
                    $filename = $filename_parts[0] . '-1.' . $filename_parts[1];
                    if (!array_key_exists($filename, $attachment_paths_by_filename)) {
                        echo "MISSING " . $filename . "\n";
                    } else {
                        $path = $attachment_paths_by_filename[$filename];
                    }
                } else {
                    $path = $attachment_paths_by_filename[$filename];
                }
            }
            $content = str_replace($url, $path, $content);
        }
    }

    preg_match_all('#\[penci_video ([^\]])*\]#', $content, $penci_matches);
    $embeds = $penci_matches[0];
    foreach ($embeds as $embed) {
        preg_match('#url ?= ?"([^"]+)"#i', $embed, $url_matches);
        $url = $url_matches[1];
        if (!$url) {
            echo $embed . "\n";
            exit(0);
        }
        $parsed_url = parse_url($url);
        $host = $parsed_url["host"] ?? "";
        if (in_array($host, [
            "youtu.be",
            "youtube.com"
        ])) {
            $class = "is-provider-youtube";
            $providerNameSlug = '"providerNameSlug":"youtube"';
        } else {
            $class = "";
            $providerNameSlug = "";
        }


        $block = <<<EOF
        <!-- wp:embed
            {
                "url":"$url",
                "type":"video",
                $providerNameSlug,
                "responsive":true,
                "className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"
            }
        -->
        <figure
            class="wp-block-embed is-type-video $class wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
                <div class="wp-block-embed__wrapper">
                    $url
                </div>
        </figure>
        <!-- /wp:embed -->
        EOF;

        $content = str_replace($embed, $block, $content);
    }

    return $content;
}

function import_user($slug, $author_xmls)
{
    global $guest_authors_to_skip;
    global $user_ids_by_guest_author_id;

    $username = str_replace('-', '', $slug);
    if (count($author_xmls) === 1) {
        $original_author = $author_xmls[0];
        $translated_author = null;
    } else if (is_original_post("guest_author", $slug, $author_xmls[0])) {
        $original_author = $author_xmls[0];
        $translated_author = $author_xmls[1];
    } else {
        $original_author = $author_xmls[1];
        $translated_author = $author_xmls[0];
    }

    $display_name = (string) $original_author->title;
    $description = strip_tags((string) $original_author->children(XMLNS_CONTENT)->encoded);
    $locale = get_category($original_author, "language");

    echo "Importing " . $display_name . "\n";

    $existing_user = get_user_by("slug", $username);
    if ($existing_user) {
        $orig_id = (string) $original_author->children(XMLNS_WP)->post_id;
        $user_ids_by_guest_author_id[$orig_id] = $existing_user->ID;
        return;
    }

    if (array_key_exists($display_name, $guest_authors_to_skip)) {
        $user_id = $guest_authors_to_skip[$display_name]->ID;
        wp_update_user([
            'ID' => $user_id,
            'nickname' => $display_name,
            'display_name' => $display_name,
            'description' => $description
        ]);
    } else {
        $user_email = get_post_meta($original_author, "_molongui_guest_author_mail") ?? "sjarrint+{$username}@gmail.com";
        $user_data = [
            "user_login" => $username,
            "user_nicename" => $username,
            "user_email" => $user_email,
            "user_pass" => wp_generate_password(),
            "nickname" => $display_name,
            "display_name" => $display_name,
            "description" => $description,
            "role" => "subscriber",
            "locale" => $locale
        ];
        $user_id = wp_insert_user($user_data);
    }

    // Update the author description string language (it defaults to 'en')
    global $wpdb;
    $sql = $wpdb->prepare(
        'UPDATE wp_icl_strings SET language=%s WHERE context=%s AND name=%s',
        $locale,
        "Authors",
        "description_" . $user_id
    );
    $wpdb->query($sql);

    $orig_id = (string) $original_author->children(XMLNS_WP)->post_id;
    $user_ids_by_guest_author_id[$orig_id] = $user_id;

    if ($translated_author) {
        // Add translated bio
        $trans_lang = get_category($translated_author, "language");
        $trans_description = strip_tags((string) $translated_author->children(XMLNS_CONTENT)->encoded);
        $string_id = icl_get_string_id($description, "Authors", "description_" . $user_id);
        icl_add_string_translation($string_id, $trans_lang, $trans_description, ICL_TM_COMPLETE);

        global $user_ids_by_guest_author_id;
        $trans_id = (string) $translated_author->children(XMLNS_WP)->post_id;
        $user_ids_by_guest_author_id[$trans_id] = $user_id;
    }

    $avatar_id = get_post_meta($original_author, "_thumbnail_id");
    if ($avatar_id) {
        global $attachment_ids_by_orig_id;
        $attachment_id = $attachment_ids_by_orig_id[$avatar_id];
        add_user_meta($user_id, meta_key: "awasqa_profile_pic_id", meta_value: $attachment_id, unique: true);
        update_user_meta($user_id, meta_key: "awasqa_profile_pic_id", meta_value: $attachment_id);
    }
}

function print_authors_list($author_xmls)
{
    global $author_is_org;
    global $author_name_replacements;
    echo "Name,Email\n";
    foreach ($author_xmls as $author_xml) {
        $name = (string) $author_xml->title;
        $is_org = $author_is_org[$name];
        $name = $author_name_replacements[$name] ?? $name;
        if (!$is_org) {
            $email = get_post_meta($author_xml, "_molongui_guest_author_mail") ?? "";
            echo $name . "," . $email . "\n";
        }
    }
}

\WP_CLI::add_command('import_awasqa', 'CommonKnowledge\WordPress\Awasqa\Import\import');
