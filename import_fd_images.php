<?php

use Eventviva\ImageResize;
use Gregwar\Image\Image;

require __DIR__ . '/vendor/autoload.php';
/*
Plugin Name: Import FD images
Description: Import images from fashion dropshippers
Author: phpdaddy
*/
ini_set('max_execution_time', 300);
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

    $url = 'http://fashiondropshippers.com/media/feed/fashiondropshippers-feeds.csv';
    $data = @file_get_contents($url);

    $csv = new parseCSV($data);
    $csv->auto();

    if (isset($_POST['submit_button']) && check_admin_referer('submit_button_clicked')) {
        submit_button_action($csv);
        return;
    }

    echo '<div class="wrap">';

    echo '<h2>Import FD images</h2>';

    $list = get_product_types_list($csv);

    echo '<form action="admin.php?page=import-fd-images" method="post">';
    echo 'CSV url: <br>';

    foreach ($list as $item) {
        echo '<input type="checkbox" name="product_types[]" value="' . $item . '">' . $item . '</input><br>';
    }

    echo '<input type="checkbox" name="crop" >Crop</input><br>';
    echo '<input type="checkbox" name="resize" >Resize</input><br>';

    wp_nonce_field('submit_button_clicked');
    echo '<input type="hidden" value="true" name="submit_button" />';
    submit_button('Import images');
    echo '</form>';

    echo '</div>';

}

function get_product_types_list($csv)
{
    $product_types = [];
    foreach ($csv->data as $row) {
        $product_types[] = $row['product_type'];
    }
    $product_types = array_unique($product_types);
    sort($product_types);
    return $product_types;
}

function submit_button_action($csv)
{
    echo '<div id="message" class="updated fade"><p>'
        . 'Images were imported.' . '</p></div>';

    $product_types = $_POST['product_types'];
    $crop = $_POST['crop'];
    $resize = $_POST['resize'];

    $ids = [];
    foreach ($csv->data as $row) {
        if (in_array($row['product_type'], $product_types)) {
            $ids[] = $row['id'];
        }
    }
    $ids = array_unique($ids);

    $imagesFolder = WP_CONTENT_DIR . '/images/';

    foreach ($ids as $id) {
        try {
            $zipLocalFile = download_zip($id, $imagesFolder);

            $path = pathinfo(realpath($zipLocalFile), PATHINFO_DIRNAME);

            $productImagesFolder = $imagesFolder . '/' . basename($zipLocalFile, '.zip');
            if (file_exists($productImagesFolder)) {
                rrmdir($productImagesFolder);
            }

            $productImagesFolder = extract_zip($zipLocalFile, $path);

            unlink($zipLocalFile);
            rename_images($productImagesFolder);
            if ($crop) {
                crop_images($productImagesFolder);
            }
            if ($resize) {
                enlarge_images($productImagesFolder);
            }

            echo '<div class="notice notice-success">Successfully imported images for ' . $id . '</div>';
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
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            if ($ext === 'jpg') {
                rename($imagesFolder . '/' . $fileName, $imagesFolder . '/' . basename($imagesFolder, '.zip') . '_' . $counter . '.jpg');
                $counter++;
            }
        }
        closedir($handle);
    }
}

function crop_images($imagesFolder)
{
    if ($handle = opendir($imagesFolder)) {
        while (false !== ($fileName = readdir($handle))) {
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            if ($ext === 'jpg') {
                $image = Image::open($imagesFolder . '/' . $fileName);
                $image->zoomCrop($image->width(), $image->width(), null, 0, $image->height())->save($imagesFolder . '/' . $fileName);
            }
        }
        closedir($handle);
    }
}

function enlarge_images($imagesFolder)
{
    if ($handle = opendir($imagesFolder)) {
        while (false !== ($fileName = readdir($handle))) {
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            if ($ext === 'jpg') {
                $image = Image::open($imagesFolder . '/' . $fileName);
                $image->resize($image->width(), $image->height() * 1.3)->save($imagesFolder . '/' . $fileName);
            }
        }
        closedir($handle);
    }
}

/**
 * Recursively removes a folder along with all its files and directories
 *
 * @param String $path
 */
function rrmdir($path)
{
    // Open the source directory to read in files
    $i = new DirectoryIterator($path);
    foreach ($i as $f) {
        if ($f->isFile()) {
            unlink($f->getRealPath());
        } else if (!$f->isDot() && $f->isDir()) {
            rrmdir($f->getRealPath());
        }
    }
    rmdir($path);
}

?>