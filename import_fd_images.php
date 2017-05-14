<?php

require __DIR__ . '/vendor/autoload.php';
/*
Plugin Name: Import FD images
Description: import images from fashion dropshippers
Author: phpdaddy
*/

add_action('admin_menu', 'plugin_setup_menu');

function plugin_setup_menu()
{
    add_menu_page('Import FD images Page', 'Import FD images', 'manage_options', 'import-fd-images', 'submit_button_admin_page');
}

function submit_button_admin_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient pilchards to access this page.'));
    }


    echo '<div class="wrap">';

    echo '<h2>Import FD iamges</h2>';

    if (isset($_POST['submit_button']) && check_admin_referer('submit_button_clicked')) {
        submit_button_action();
    }

    echo '<form action="admin.php?page=import-fd-images" method="post">';
    echo 'CSV url: ';
    echo '<input type="text" value="' . $_POST['csv_url'] . '" name="csv_url" />';
    wp_nonce_field('submit_button_clicked');
    echo '<input type="hidden" value="true" name="submit_button" />';
    submit_button('Import images');
    echo '</form>';

    echo '</div>';

}

function submit_button_action()
{
    echo '<div id="message" class="updated fade"><p>'
        . 'Images were imported.' . '</p></div>';

    $data = @file_get_contents($_POST['csv_url']);

    $csv = new parseCSV($data);
    $csv->auto();
    $i = 0;
    $ids = [];
    foreach ($csv->data as $row) {
        $ids[] = $row['id'];
    }
    $ids = array_unique($ids);

    $imagesFolder = WP_CONTENT_DIR . '/images/';
    rmdir($imagesFolder);
    foreach ($ids as $id) {
        try {
            $zipLocalFile = download_zip($id, $imagesFolder);

            $path = pathinfo(realpath($zipLocalFile), PATHINFO_DIRNAME);
            $productImagesFolder = extract_zip($zipLocalFile, $path);

            unlink($zipLocalFile);
            rename_images($productImagesFolder);

            echo '<div class="notice notice-success">Successfully imported images for ' . $id . '</div>';
            $i++;
            if ($i == 6) {
                break;
            }
        } catch (RuntimeException $ex) {
            echo '<div class="notice notice-error">' . $ex->getMessage() . '</div>';
        }
    }
}

function download_zip($id, $folder)
{
    $zipUrl = 'http://fashiondropshippers.com/media/product-images/' . $id . '.zip';
    $zip = @file_get_contents($zipUrl);

    if (empty($zip)) {
        throw new RuntimeException('File ' . $zipUrl . ' does not exist');
    }
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }
    $zipLocalFile = $folder . '/' . $id . '.zip';
    file_put_contents($zipLocalFile, $zip);
    return $zipLocalFile;
}

function extract_zip($zipLocalFile, $folder)
{
    $zip = new ZipArchive;
    $res = $zip->open($zipLocalFile);
    if ($res === true) {
        $zip->extractTo($folder);
        $zip->close();
    } else {
        throw new RuntimeException("Doh! I couldn't open" . $zipLocalFile);
    }
    return $folder . '/' . basename($zipLocalFile, '.zip');
}

function rename_images($imagesFolder)
{
    $counter = 0;
    if ($handle = opendir($imagesFolder)) {
        while (false !== ($fileName = readdir($handle))) {
            //$newName = str_replace("SKU#", "", $fileName);
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            if ($ext === 'jpg') {
                rename($imagesFolder . '/' . $fileName, $imagesFolder . '/' . 'image_' . $counter . '.jpg');
                $counter++;
            }
        }
        closedir($handle);
    }
}

?>