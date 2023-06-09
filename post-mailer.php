<?php


/**
 * @package post_mailer
 * @version 1.0
 */
/*
Plugin Name: Foto's versturen
Plugin URI: https://github.com/J-a-s-p-er/Wordpress-Post-Mailer
Description: Wijs foto's aan gebruikers toe stuur ze automatisch een e-mail wanneer je een post bijwerkt.
Author: J-a-s-p-er
Version: 1.0
Author URI: https://github.com/J-a-s-p-er/
*/

function pa_register_post_type()
{
    register_post_type('post_mailer', array(
        'labels' => array(
            'name' => __('Foto\'s versturen'),
            'singular_name' => __('Foto versturen')
        ),
        'public' => true,
        'show_ui' => true,
        'supports' => array('title', 'editor')
    ));
}

add_action('init', 'pa_register_post_type');

function pa_add_custom_meta_box()
{
    add_meta_box('pa_assigned_user', 'E-mail sturen', 'pa_render_assigned_user_meta_box', 'post_mailer', 'side');
}

add_action('add_meta_boxes', 'pa_add_custom_meta_box');

function pa_render_assigned_user_meta_box($post)
{
    wp_nonce_field('pa_save_assigned_user_meta', 'pa_assigned_user_meta_nonce');

    $assigned_user_id = get_post_meta($post->ID, '_pa_assigned_user_id', true);

    $users = get_users();

    echo '<label for="pa_assigned_user_id" class="">Stuur een link naar deze post aan:</label>';
    echo '<select name="pa_assigned_user_id" class="widefat">';
    echo '<option value="">-- Kies een gebruiker --</option>';
    foreach ($users as $user) {
        $selected = ($user->ID == $assigned_user_id) ? 'selected="selected"' : '';
        echo '<option value="' . esc_attr($user->ID) . '" ' . $selected . '>' . esc_html($user->display_name) . ' (' . esc_attr($user->user_email) . '</option>';
    }
    echo '</select>';
}

function pa_save_assigned_user_meta($post_id)
{
    if (!isset($_POST['pa_assigned_user_meta_nonce']) || !wp_verify_nonce($_POST['pa_assigned_user_meta_nonce'], 'pa_save_assigned_user_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['pa_assigned_user_id'])) {
        $assigned_user_id = sanitize_text_field($_POST['pa_assigned_user_id']);
        update_post_meta($post_id, '_pa_assigned_user_id', $assigned_user_id);
    } else {
        delete_post_meta($post_id, '_pa_assigned_user_id');
    }
}

add_action('save_post_post_mailer', 'pa_save_assigned_user_meta');

function pa_send_notification_email($post_id)
{
    $post_status = get_post_status($post_id);
    if ($post_status != 'publish' && $post_status != 'future' && $post_status != 'private') {
        return;
    }

    $assigned_user_id = get_post_meta($post_id, '_pa_assigned_user_id', true);
    $assigned_user = get_userdata($assigned_user_id);
    if($assigned_user) {
    $user_email = $assigned_user->user_email;
    $user_name = $assigned_user->display_name;

    $post_password = get_post_field('post_password', $post_id);
    $post_title = get_the_title($post_id);
    $post_link = get_permalink($post_id);

    //TODO: E-mail aanpasbaar maken met editor
    $subject = 'Er staan nieuwe foto\'s voor u klaar!';
    $message = "Hallo $user_name,\n\n";
    $message .= "Er staan nieuwe foto\'s voor u klaar in album $post_title!\n";
    $message .= "Bekijk ze direct ";
    if (!empty($post_password)) {
        $message .= "met wachtwoord $post_password ";
    }
    $message .= "<a href='$post_link'>door hier te klikken</a> of ga naar <a href='$post_link'>$post_link</a>.\n\n";
    $message .= 'Met vriendelijke groet,\n';
    $message .= get_bloginfo('name');

    $message .= $user_email . $assigned_user->display_name;
    wp_mail($user_email, $subject, $message);
    } else {
        error_log("unknown user was defined as e-mail recipient in post-mailer.php");
    }
}
add_action('save_post_post_mailer', 'pa_send_notification_email');

