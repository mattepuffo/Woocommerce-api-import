<?php

define("IMG_SMALL", "https://web.compagniaitaliana.it/IMMAGINI/cronos/small/");

require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;

/**
 * Crea una connessione con Woocommerce
 * Dovete prima abilitare le API su Woocommerce, e salvare le key
 * Poi dovete installare la libreria tramite Composer => https://packagist.org/packages/automattic/woocommerce
 *
 * @return Client
 */
function getWoocommerceConfig() {
    $woocommerce = new Client(
        'https://www.mattepuffo.com/wp',
        'ck_4e19c3e3fcc1234d7711f2e616c99ebb1eb6354e',
        'cs_1d2c98ef7b26f9b4810795b28d6c9dcc936e5da0',
        [
            'wp_api' => true,
            'version' => 'wc/v3',
            'query_string_auth' => true,
        ]
    );

    return $woocommerce;
}

/**
 * Legge il file JSON
 *
 * @param string $file
 * @return mixed
 */
function getJsonFromFile($file = 'prodotti.json') {
    $json = json_decode(file_get_contents($file), true);
    return $json;
}

/**
 * Crea i prodotti controllando se già esistono
 * Poi crea le variations
 * ATTENZIONE: GLI ATTRIBUTI GLOBALI DEVONO ESSERE GIÀ CREATI
 */
function createProducts() {
    $woocommerce = getWoocommerceConfig();
    $products = getJsonFromFile();
    $imgCounter = 0;

    foreach ($products as $product) {
        $id = null;
        $imagesFormated = array();

        $productExist = checkProductBySku($product['sku']);

        foreach ($product['categories'] as $category) {
            $categoriesIds[] = ['id' => getCategoryIdByName($category)];
        }

        $images = $product['imgs'];
        foreach ($images as $image) {
            $imagesFormated[] = [
                'src' => IMG_SMALL . $image,
                'position' => 0
            ];
            $imgCounter++;
        }

        $data = [
            'name' => $product['name'],
            'sku' => $product['sku'],
            'description' => $product['description'],
            'type' => $product['type'],
            'sold_individually' => $product['sold_individually'],
            'attributes' => $product['attributes'],
            'categories' => $categoriesIds,
            'images' => $imagesFormated,
        ];

        $variations = $product['variations'];

        if (!$productExist['exist']) {
            $product = $woocommerce->post('products', $data);
            $id = $product->id;
        } else {
            $product = $woocommerce->put('products/' . $productExist['idProduct'], $data);
            $id = $productExist['idProduct'];
        }

//        echo json_encode($product);
        createVariationsById($id, $variations);
    }
}

/**
 * Controlla se un prodotto già esiste dallo SKU
 *
 * @param $skuCode
 * @return array
 */
function checkProductBySku($skuCode) {
    $woocommerce = getWoocommerceConfig();
    $products = $woocommerce->get('products');

    foreach ($products as $product) {
        $currentSku = strtolower($product->sku);
        $skuCode = strtolower($skuCode);

        if ($currentSku === $skuCode) {
            return ['exist' => true, 'idProduct' => $product->id];
        }
    }

    return ['exist' => false, 'idProduct' => null];
}

/**
 * Crea variazioni del prodotto
 *
 * @param $id
 * @param $variations
 */
function createVariationsById($id, $variations) {
    $woocommerce = getWoocommerceConfig();

    foreach ($variations as $k => $v) {
        $varExist = checkVariationsByProductIdSku($id, $v['sku']);

        if ($varExist['exist']) {
            $res = $woocommerce->put('products/' . $id . '/variations/' . $varExist['idVariation'], $v);
        } else {
            $res = $woocommerce->post('products/' . $id . '/variations', $v);
        }

//        echo json_encode($res);
    }
}

/**
 * Cerca le variazioni per prodotto e SKU variations
 *
 * @param $id
 * @param $sku
 * @return array
 */
function checkVariationsByProductIdSku($id, $sku) {
    $woocommerce = getWoocommerceConfig();
    $variations = $woocommerce->get('products/' . $id . '/variations');

    foreach ($variations as $variation) {
        $currentSku = strtolower($variation->sku);
        $skuCode = strtolower($sku);

        if ($currentSku === $skuCode) {
            return ['exist' => true, 'idVariation' => $variation->id];
        }
    }

    return ['exist' => false, 'idVariation' => null];
}

/**
 * Cerca le categorie per nome e ritorna l'id
 *
 * @param $categoryName
 * @return mixed
 */
function getCategoryIdByName($categoryName) {
    $woocommerce = getWoocommerceConfig();
    $categories = $woocommerce->get('products/categories');
    foreach ($categories as $category) {
        if ($category->name == $categoryName) {
            return $category->id;
        }
    }
}

/**
 * Crea le categorie, controllando se già esisistono
 */
function createCategories() {
    $woocommerce = getWoocommerceConfig();
    $products = getJsonFromFile();
    $categories = array_column($products, 'categories');

    foreach ($categories as $c) {
        foreach ($c as $a) {
            $check = checkCategoryByName($a);

            if (!$check) {
                $data = [
                    "name" => $a
                ];

                $res = $woocommerce->post('products/categories', $data);
//                echo json_encode($res);
            }
        }
    }
}

/**
 * Controlla se una categoria esiste
 *
 * @param $categoryName
 * @return bool
 */
function checkCategoryByName($categoryName) {
    $woocommerce = getWoocommerceConfig();
    $categories = $woocommerce->get('products/categories');

    foreach ($categories as $category) {
        if ($category->name === $categoryName) {
            return true;
        }
    }
    return false;
}

/**
 * Crea i termini degli attributi
 * ATTENZIONE: GLI ATTRIBUTI GLOBALI DEVONO ESSERE GIÀ CREATI
 *
 * @param $id
 * @param $file
 */
function createAttributesTerms($id, $file) {
    // Colore = 13
    // Taglia = 14

    $woocommerce = getWoocommerceConfig();
    $data = getJsonFromFile($file);

    $res = $woocommerce->post('products/attributes/' . $id . '/terms/batch', $data);
//    echo json_encode($res);
}

function init() {
    echo 'Loading...<br>';
    createCategories();
//    createAttributesTerms(13, 'colori.json');
//    createAttributesTerms(14, 'taglie.json');
    createProducts();
    echo '<br>End';
}

init();
