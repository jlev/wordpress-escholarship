<?php
/*
Plugin Name: BPJ eScholarship Article integration
Description: This plugin grabs citation info from escholarship for articles in the Berkeley Planning Journal
Author: Josh Levinger
Version: 0.1
Author URI: http://www.levinger.net/josh
License: MIT License
*/

//based heavily on http://wp.smashingmagazine.com/2011/10/04/create-custom-post-meta-boxes-wordpress/
//example article_id = 31b359g4

function escholarship_create_admin_box() {
	add_meta_box('escholarship_meta','eScholarship Integration','escholarship_admin_box','post');
}

function escholarship_admin_box($post) {
	/* prints the box for the admin */

	$escholarship_id = get_post_meta($post->ID, '_escholarship_id',true);
	wp_nonce_field( plugin_basename( __FILE__ ), 'escholarship_nonce' );
	echo '<label for="escholarship_article_id">Article ID</label>';
	echo '<input type="text" id="escholarship_article_id" name="escholarship_article_id" value="'.$escholarship_id.'" size="25" />';
	echo '<br><b>You can find this at blah blah blah,</b>';
	
	if ($escholarship_id != "") {
		//display current values
		echo "<h2>Article Metadata</h2>";
		echo escholarship_get_article_meta($post->ID); 
	}
}

function escholarship_set_article_meta($post_id) {
	// Verify the nonce before proceeding.
	if ( !isset( $_POST['escholarship_nonce'] ) || !wp_verify_nonce( $_POST['escholarship_nonce'], basename( __FILE__ ) ) )
		return $post_id;
	
	// Get the posted data and sanitize it for use as an HTML class.
	if (isset( $_POST['escholarship_article_id'])) {
		$escholarship_id = sanitize_html_class( $_POST['escholarship_article_id'] );
	} else {
		update_post_meta($post_id, '_escholarship_id', "ERROR");
		return $post_id;
	}
	
	// hit the escholarship API for the info
	$url = "http://www.escholarship.org/uc/oai?verb=GetRecord&metadataPrefix=oai_dc&identifier=qt$escholarship_id";
	$xml = new SimpleXmlElement(file_get_contents($url));
	$namespaces = $xml->getNamespaces(true);
	
	if ($xml->error->attributes()->code[0] == "idDoesNotExist") {
		//probably didn't get a good article id
		update_post_meta($post_id, '_escholarship_id', "ERROR");
		delete_post_meta($post_id, '_escholarship_title');
		delete_post_meta($post_id, '_escholarship_author');
		delete_post_meta($post_id, '_escholarship_description');
		delete_post_meta($post_id, '_escholarship_citation');
		delete_post_meta($post_id, '_escholarship_link'); 
		return $post_id;
	}

	$oai_dc = $xml->GetRecord->record->metadata->children($namespaces['oai_dc']);
	$dc = $oai_dc->children($namespaces['dc']);
	//all the data we're interested in is in the dublin core namespace
	
	if (get_post_meta($post_id, '_escholarship_id', true) == "") {
		// add_post_meta
		// add_post_meta($post_id, $meta_key, $meta_value, $unique=false);
		add_post_meta($post_id, '_escholarship_id', $escholarship_id);
		add_post_meta($post_id, '_escholarship_title', (string) $dc->title);
		add_post_meta($post_id, '_escholarship_author', (string) $dc->creator);
		add_post_meta($post_id, '_escholarship_description', (string) $dc->description);
		add_post_meta($post_id, '_escholarship_citation', (string) $dc->source);
		add_post_meta($post_id, '_escholarship_link', (string) $dc->relation); 
	} else {
		//update_post_meta 
		update_post_meta($post_id, '_escholarship_id', (string) $escholarship_id);
		update_post_meta($post_id, '_escholarship_title', (string) $dc->title);
		update_post_meta($post_id, '_escholarship_author', (string) $dc->creator);
		update_post_meta($post_id, '_escholarship_description', (string) $dc->description);
		update_post_meta($post_id, '_escholarship_citation', (string) $dc->source);
		update_post_meta($post_id, '_escholarship_link', (string) $dc->relation); 
	}
}

function escholarship_get_article_meta($post_id) {
	//gets the escholarship information from the post meta
	if (get_post_meta($post_id, '_escholarship_id',true) == "ERROR") {
		return "Invalid Article ID";
	}
	
	$title = get_post_meta($post_id, '_escholarship_title',true);
	$author = get_post_meta($post_id, '_escholarship_author',true);
	$description = get_post_meta($post_id, '_escholarship_description',true);
	$citation = get_post_meta($post_id, '_escholarship_citation',true);
	$link = get_post_meta($post_id, '_escholarship_link',true);
	
	//return it in a ul
	return "<ul class='escholarship_article_meta'>
		<li><b>Title</b>: $title</li>
		<li><b>Author</b>: $author</li>
		<li><b>Description</b>: $description</li>
		<li><b>Cite</b>: $citation</li>
		<li><b>Link</b>: <a href='$link'>$link</a></li>
		</ul>";
}

add_action('load-post.php', 'escholarship_create_admin_box');
add_action('save_post', 'escholarship_set_article_meta'); 
//add_action('the_content', 'escholarship_get_article_meta'); 
	?>