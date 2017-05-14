<?php

require __DIR__ . '/vendor/autoload.php';
/*
Plugin Name: Test plugin
Description: A test plugin to demonstrate wordpress functionality
Author: Simon Lissack
Version: 0.1
*/
add_action('admin_menu', 'test_plugin_setup_menu');

function test_plugin_setup_menu()
{
    add_menu_page('Import FD images Page', 'Import FD images', 'manage_options', 'import-fd-images', 'test_button_admin_page');
}

function test_button_admin_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient pilchards to access this page.'));
    }


    echo '<div class="wrap">';

    echo '<h2>Import FD iamges</h2>';

    if (isset($_POST['test_button']) && check_admin_referer('test_button_clicked')) {
        test_button_action();
    }

    echo '<form action="options-general.php?page=import-fd-images" method="post">';

    wp_nonce_field('test_button_clicked');
    echo '<input type="hidden" value="true" name="test_button" />';
    submit_button('Import images');
    echo '</form>';

    echo '</div>';

}

function test_button_action()
{
    echo '<div id="message" class="updated fade"><p>'
        . 'Images were imported.' . '</p></div>';

}

?>