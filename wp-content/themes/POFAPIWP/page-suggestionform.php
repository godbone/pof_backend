<?php
/*
Template Name: Suggestion form
*/

$domains = pof_settings_get_suggestions_allowed_domains();

foreach ($domains as $domain) {
    if (strlen(trim($domain)) > 0) {
        header('Access-Control-Allow-Origin: '.$domain);
    }
}

$lang_key = 'fi';
$partio_id = '';
$post_guid = '';

if (   $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST)
    && array_key_exists('suggestion_name', $_POST)
    && $_POST['suggestion_name'] != ""
    && array_key_exists('suggestion_title', $_POST)
    && $_POST['suggestion_title'] != ""
    && array_key_exists('suggestion_content', $_POST)
    && $_POST['suggestion_content'] != "" ) {

    if (array_key_exists('lang', $_POST) && $_POST['lang'] != "") {
        $lang_key = $_POST['fi'];
    }
    if (array_key_exists('partio_id', $_POST) && $_POST['partio_id'] != "") {
        $partio_id = $_POST['partio_id'];
    }
    if (array_key_exists('post_guid', $_POST) && $_POST['post_guid'] != "") {
        $post_guid = $_POST['post_guid'];
    }
    $wp_error = false;

    $suggestion = array(
	    'post_title'    => trim($_POST['suggestion_title']),
		'post_content'  => $_POST['suggestion_content'],
		'post_type' => 'pof_post_suggestion',
		'post_status'   => 'draft'
	);
	$suggestion_id = wp_insert_post( $suggestion, $wp_error );

    $mypost = false;

    if ($post_guid != '') {

        $args = array(
	        'numberposts' => -1,
	        'posts_per_page' => -1,
	        'post_type' => array('pof_post_task', 'pof_post_taskgroup', 'pof_post_program', 'pof_post_agegroup' ),
	        'meta_key' => 'post_guid',
	        'meta_value' => $post_guid
        );

        $the_query = new WP_Query( $args );

        if( $the_query->have_posts() ) {
	        while ( $the_query->have_posts() ) {
		        $the_query->the_post();
		        $mypost = $the_query->post;
                update_post_meta($suggestion_id, "pof_suggestion_task", $mypost->ID);
	        }
        }
    }

    if (array_key_exists('pof_suggestion_file_user', $_FILES)) {

        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        $uploadedfile = $_FILES['pof_suggestion_file_user'];

        $upload_overrides = array( 'test_form' => false );

        $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );

        if ( $movefile && !isset( $movefile['error'] ) ) {
            // echo "File is valid, and was successfully uploaded.\n";
            // var_dump( $movefile);
            // $filename should be the path to a file in the upload directory.
            $filename = $movefile['file'];

            // The ID of the post this attachment is for.
            $parent_post_id = $suggestion_id;

            // Check the type of file. We'll use this as the 'post_mime_type'.
            $filetype = wp_check_filetype( basename( $filename ), null );

            // Get the path to the upload directory.
            $wp_upload_dir = wp_upload_dir();

            // Prepare an array of post data for the attachment.
            $attachment = array(
                'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
                'post_mime_type' => $filetype['type'],
                'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );

            // Insert the attachment.
            $attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );

            // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
            require_once( ABSPATH . 'wp-admin/includes/image.php' );

            // Generate the metadata for the attachment, and update the database record.
            $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
            wp_update_attachment_metadata( $attach_id, $attach_data );

            // Update File Field
//            update_field('pof_suggestion_file_user', $attach_id, $suggestion_id);

            update_post_meta($suggestion_id, "pof_suggestion_file_user", $attach_id);
        }
    }
    update_post_meta($suggestion_id, "pof_suggestion_lang", $lang_key);
	update_post_meta($suggestion_id, "pof_suggestion_writer", $_POST['suggestion_name']);
    update_post_meta($suggestion_id, "pof_suggestion_writer_id", $partio_id);

    $suggestion_guid = get_post_meta( $suggestion_id, "post_guid", true );

    $emails_str = pof_settings_get_suggestions_emails();

    $content = "Uusi vinkki\n\n";
    $content .= "Aktiviteetti: ";
    if ($mypost == false) {
        $content .= "--"."\n\n";
    } else {
        $content .= $mypost->post_title."\n\n";
    }
    $content .= "Vinkin otsikko: ".$_POST['suggestion_title']."\n\n";

    $content .= "Kirjoittaja: ".$_POST['suggestion_name']."\n\n";

    $content .= "Kieli: ".$lang_key."\n\n";

    $content .= "Lue: " . get_site_url()."/wp-admin/post.php?post=".$suggestion_id."&action=edit";


    wp_mail( $emails_str, "[POF] Uusi vinkki", $content, 'From: "' . pof_settings_get_suggestions_email_sender_name() . '" <'.pof_settings_get_suggestions_email_sender_email().'>');

	$return_val = 'json';
	if (array_key_exists('return_val', $_POST)
    && $_POST['return_val'] != "") {
		$return_val = $_POST['return_val'];
	}

    $location = "Location: " . $url=strtok($_SERVER["REQUEST_URI"],'?') . "?form_submit=ok&lang=" . $lang_key . "&return_val=" . $return_val;

    if ($suggestion_guid != "") {
        $location .= "&suggestion_guid=" . $suggestion_guid;
    }

	header($location);
	exit();
//    echo pof_taxonomy_translate_get_translation_content("common", "suggestion_form_done", 0, $lang_key);

}
else {
    if (array_key_exists('lang', $_GET) && $_GET['lang'] != "") {
        $lang_key = $_GET['lang'];
    }

    if (   array_key_exists('form_submit', $_GET) && $_GET['form_submit'] != ""
        && array_key_exists('return_val', $_GET) && $_GET['return_val'] != "html") {
        $tmp = new stdClass();
        if ($_GET['form_submit'] == "ok") {
            $tmp->status = "ok";
            $tmp->message = pof_taxonomy_translate_get_translation_content("common", "suggestion_form_done", 0, $lang_key);
            if (   array_key_exists('suggestion_guid', $_GET) && $_GET['suggestion_guid'] != "") {
                $tmp->suggestion_guid = $_GET['suggestion_guid'];
            }
        } else {
            $tmp->status = "error";
            $tmp->message = pof_taxonomy_translate_get_translation_content("common", "suggestion_form_error", 0, $lang_key);
        }
        echo json_encode($tmp);
        exit();
    }
    else {

        get_header();

?>


	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	            <header class="entry-header">
		            <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
	            </header><!-- .entry-header -->

			    <div class="entry-content">
                    <?php
                    if (array_key_exists('form_submit', $_GET) && $_GET['form_submit'] != "") {
                        if ($_GET['form_submit'] == "ok") {
                            echo '<h2>' . pof_taxonomy_translate_get_translation_content("common", "suggestion_form_done", 0, $lang_key) . "</h2>";
                        } else {
                            echo '<h2>' . pof_taxonomy_translate_get_translation_content("common", "suggestion_form_error", 0, $lang_key) . "</h2>";
                        }
                    }

                        
                    ?>
                    <form action="" method="POST" class="tips__form" enctype="multipart/form-data">
                        <input type="hidden" name="return_val" value="html" />
                        <br />
                        <input class="radius" type="text" name="suggestion_name" placeholder="<?php echo pof_taxonomy_translate_get_translation_content("common", "suggestion_form_name_placeholder", 0, $lang_key, true); ?> *" aria-label="Name" />
                        <br />
                        <br />
                        <input class="radius" type="text" name="suggestion_title" placeholder="<?php echo pof_taxonomy_translate_get_translation_content("common", "suggestion_form_title_placeholder", 0, $lang_key, true); ?> *" aria-label="Title" />
                        <br />
                        <br />
                        <textarea class="radius form-textarea" name="suggestion_content" placeholder="<?php echo pof_taxonomy_translate_get_translation_content("common", "suggestion_form_content_placeholder", 0, $lang_key, true); ?>"></textarea>
                        <br />
                        <br />
                        <input class="button radius" type="submit" name="submit-tip" value="<?php echo pof_taxonomy_translate_get_translation_content("common", "suggestion_form_sendbutton", 0, $lang_key, true); ?>" aria-label="Send" />

                    </form>
                </div>
            </article>
		</main><!-- .site-main -->
	</div><!-- .content-area -->

<?php get_footer(); ?>

<?php    }
    } 
?>