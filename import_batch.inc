<?php

/**
 * @file
 */

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Database\Database;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\Product;

/**
 *
 */
function _easyjob_api_import_products($products, $total, &$context) {
  if (count($products) < 1) {
    \Drupal::logger('easyjob_api_import')->notice('nothing to import');
    return;
  }

  if (!isset($context['sandbox']['progress'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_index'] = 0;
    $context['sandbox']['current_max'] = max(100, count($products));
    $context['sandbox']['max'] = $total - 1;
    $context['sandbox']['start'] = microtime(TRUE);

  }
  if (!isset($context['results']['products']) || empty($context['results']['products'])) {
    $context['results']['products'] = $products;
    $context['results']['progress'] = 0;
    $context['results']['imported']['products'] = 0;
    $context['results']['updated']['products'] = 0;
    $context['results']['error']['products'] = 0;
  }

  $indexes = range($context['sandbox']['progress'], min($context['sandbox']['progress'] + 99, $context['sandbox']['max']));
  foreach ($indexes as $i) {
    $context['sandbox']['progress']++;
    $context['sandbox']['current_index'] = $i;
    if ($products[$i] == NULL) {
      continue;
    }
    try {
        $product = processProduct($products[$i], $context);
        \Drupal::entityTypeManager()->getStorage('node')->resetCache([$product->id()]);
    } catch (\Exception $e) {
      \Drupal::logger('easyjob_api')->notice('error: ' . $e->getMessage());
        $context['results']['error']['products']++;
    }
  }

  gc_collect_cycles();

  $context['results']['progress'] += $context['sandbox']['current_max'];
  $processed_rel = $context['results']['progress'] / ($context['sandbox']['max'] + 1) * 100;
  $context['message'] = 'Processed ' .  $context['results']['progress'] . ' of ' . ($context['sandbox']['max'] + 1) . ' records (' . round($processed_rel) . ' %).';
}


/**
 *
 */
function processProduct($product_data, &$context) {
  \Drupal::logger('easyjob_api_import')->notice('processing product '. $product_data['ID']);
    //Create if new product, update if existing
    $easyjob_id = $product_data['ID'];
    $variation = fetchVariation($easyjob_id);
    $product = fetchProduct($easyjob_id);
    $product->addVariation($variation);

    updateProductFields($product, $product_data);
    updateVariationFields($variation, $product_data);

    $product->save();
    $variation->save();

    return $product;
}

/**
 *
 */
function _easyjob_api_finished_import($success, $results, $operations) {
  $msg = 'Import finished';
  if (!empty($results['products'])) {
    $msg .= '<br />Imported ' . $results['imported']['products'] . ' products and updated ' . $results['updated']['products'];
    $msg .= '<br />Errors: ' . $results['error']['products'];
  }
  \Drupal::logger('easyjob_api_import')->notice($msg);
  $config = \Drupal::getContainer()->get('config.factory')->getEditable('easyjob_api.settings');
  $config->set('timestamp', time());
  $config->save();

  return 0;
}

/**
 * Fetch matching variation or create a new one
 * @param int $id the easyjob id
 */
function fetchVariation($id) {
    $results = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->loadByProperties([
      'type' => 'te_product',
      'field_te_main_number_easyjob' => $id,
    ]);

    if (!empty($results)){
      $variation = reset($results);
    }
    else {
      $variation = ProductVariation::create(['type' => 'te_product']);
      $variation->set('field_te_main_number_easyjob', $id);
      $variation->save();
    }
    return $variation;
}

/**
 * Fetch matching variation or create a new one
 * @param int $id the easyjob id
 */
function fetchProduct($id) {
    $results = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties([
      'type' => 'te_product',
      'field_te_item_number_easyjob' => $id,
    ]);

    if (!empty($results)){
      $product = reset($results);
      $context['results']['updated']['products']++;
    }
    else {
      $product = Product::create([
          'type' => 'te_product',
        ],
      );
      $product->set('field_te_item_number_easyjob', $id);
      $product->save();
    }
    return $product;
}

/**
 * Update product fields with easyjob values
 * @param Product $product the drupal product
 * @param array $data the easyjob data
 */
function updateProductFields($product, $data) {
    $product->set('title', $data['Titel']);
    ($data['Publiziert']) ? $product->set('status', 1) : $product->set('status', 0);
    $product->set('field_te_item_number_intern', $data['Nummer']);
    $product->set('field_te_seo_text', $data['Beschreibung']);
    $product->set('field_te_additional_information', $data['Zusatzinformationen']);
}


/**
 * Update variation fields with easyjob values
 * @param ProductVariation $variation the drupal product variation
 * @param array $data the easyjob data
 */
function updateVariationFields($variation, $data) {
    $variation->set('field_st_variation_title', $data['Titel']);
    //$variation->set('field_te_main_commodity_group', $data['Mutterwarengruppe']);
    //$variation->set('field_te_commodity_group', $data['Warengruppe']);
    $variation->set('field_te_width', $data['Breite']);
    $variation->set('field_te_height', $data['Hoehe']);
    $variation->set('field_te_depth', $data['Tiefe']);
    $variation->set('field_te_volume', $data['Volumen']);
    $variation->set('field_te_diameter', $data['Durchmesser']);

    $variation->set('attribute_te_color', $data['Farbe']);

    $price = ($data['Verbrauchsartikel']) ? $data['Vermietartikel'] :  $data['Verkaufspreis'];
    $variation->set('field_te_price_per_item', $price);
    $variation->set('field_te_voltage_v', $data['Scheinleistung']);
    $variation->set('field_te_effective_power_w', $data['Wirkleistung']);

    $variation->set('field_te_form', $data['Verwendungszweck']);

    //Taxonomie
   /* $variation->set('field_te_form', $data['Form']);
   $variation->set('field_te_line', $data['Linie']);
   $variation->set('field_te_material', $data['Material']);
   $variation->set('field_te_source_of_energy', $data['Energiequelle']);
   $variation->set('field_te_product_status', $data['Produktstatus']);
   $variation->set('field_te_style', $data['Stil']);
   $variation->set('field_te_season', $data['Saison']);
   $variation->set('field_te_occasion', $data['Anlass']);
   $variation->set('field_te_motto', $data['Motto']); */

}


/**
 * Update product relationships with easyjob values
 * @param Product $product the drupal product
 * @param array $data the easyjob data
 */
/* function updateProductRelationships($product, $data) {
    $product->set('')
    field_te_additional_products
    field_te_alternative_products
    field_te_cleaning_price
    field_te_item_number_intern
    field_te_item_number_easyjob
    field_meta_tags
    field_te_pdf_instructions
    field_te_pdf_safety_info
    field_te_price_per_single_item
    field_te_product_category
    field_iq_commerce_images
    field_te_quantity
    field_yoast_seo
    field_te_reference_product
    field_te_ref_product_amount
    field_te_seo_text
    field_te_similar_line_products
    field_te_youtube_link

    "ID": 11988,
    "Nummer": "1000977.00",
    "Titel": "Wein- und Fondue Stehtisch",
    "Bearbeitet": "2022-03-03T09:47:00",
    "Publiziert": true,
    "Mutterwarengruppe": "Verleihartikel",
    "Warengruppe": "Küche & Service\\Küchengeräte",
    "Produktkategorie1": "Fondue",
    "Produktkategorie2": null,
    "Produktkategorie3": null,
    "Beschreibung": null,
    "Zusatzinformationen": null,
    "Breite": 0.000000,
    "Hoehe": 1.050000,
    "Tiefe": 0.000000,
    "Volumen": 0.000000,
    "Durchmesser": 0.9200,
    "Fuellmenge": 0.0000,
    "Linie": null,
    "Energiequelle": "Feuer",
    "Farbe": null,
    "Form": "rund",
    "Preiskategorie": null,
    "Lagerstandort1": "2L1Z2F9",
    "Lagerstandort2": null,
    "Lagerstandort3": null,
    "Verkaufspreis": 1400.0000,
    "Vermietpreis": 95.0000,
    "Vermietartikel": true,
    "Verkaufsartikel": false,
    "Verbrauchsartikel": false,
    "Scheinleistung": 0.0000,
    "Wirkleistung": 0.0000,
    "MobiliarEigenschaften": null,
    "GastroEigenschaften": "Feuer",
    "Material": "Metall,Holz",
    "Verwendungszweck": "Käse,Indoor,Outdoor",
    "Produktstatus": null,
    "Stil": null,
    "Saison": null,
    "Anlass": null,
    "Motto": null,
    "ReferenzenGebunden": "14930,14934,14938,14939,12522",
    "ReferenzenNormal": "16904,14837",
    "DazuPassend": "12328,12181,14157",
    "Modellreihe": null,
    "Farbvarianten": null
} */