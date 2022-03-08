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
        $product = process_product($products[$i], $context);
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
function _easyjob_api_import_product_relationships($products, $total, &$context) {
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
      $product = process_product_relationships($products[$i], $context);
      \Drupal::entityTypeManager()->getStorage('node')->resetCache([$product->id()]);
      $context['results']['imported']['products']++;
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
function process_product($product_data, &$context) {
  \Drupal::logger('easyjob_api_import')->notice('processing product '. $product_data['ID']);

  $easyjob_id = $product_data['ID'];
  $variation = fetchVariation($easyjob_id);
  $product = fetchProduct($easyjob_id, $context);
  $product->addVariation($variation);

  if (empty($variation->sku->value)) {
    $variation->set('sku', $product->id() . '-' . $variation->id());
  }

  updateProductFields($product, $product_data);
  updateVariationFields($variation, $product_data);

  updateVariationTerms($variation, $product_data);
  updateProductTerms($product, $product_data);

  $product->save();
  $variation->save();

  return $product;
}


/**
 *
 */
function process_product_relationships($product_data, &$context) {
  \Drupal::logger('easyjob_api_import')->notice('importing relationships for product ' . $product_data['ID']);

  $easyjob_id = $product_data['ID'];
  $product = fetchProduct($easyjob_id, $context);

  updateProductRelationships($product, $product_data, $context);

  $product->save();

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
 * @param array $context the import context array
 */
function fetchProduct($id, &$context) {
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
      $context['results']['imported']['products']++;
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

    $price = ($data['Verbrauchsartikel']) ? $data['Vermietartikel'] :  $data['Verkaufspreis'];
    $variation->set('field_te_price_per_item', $price);
    $variation->set('field_te_voltage_v', $data['Scheinleistung']);
    $variation->set('field_te_effective_power_w', $data['Wirkleistung']);

    //$variation->set('field_te_form', $data['Verwendungszweck']);

}

/**
 * Update variation terms with easyjob values
 * @param ProductVariation $variation the drupal product variation
 * @param array $data the easyjob data
 */
function updateVariationTerms($variation, $data) {

  $mapping = [
    [
      'vid' => 'form',
      'field_name' => 'field_te_form',
      'easyjob_name' => 'Form',
      'multiple' => FALSE,
    ],
    [
      'vid' => 'line',
      'field_name' => 'field_te_line',
      'easyjob_name' => 'Linie',
      'multiple' => FALSE,
    ],
    [
      'vid' => 'material',
      'field_name' => 'field_te_material',
      'easyjob_name' => 'Material',
      'multiple' => FALSE,
    ],
    [
      'vid' => 'source_of_energy',
      'field_name' => 'field_te_source_of_energy',
      'easyjob_name' => 'Energiequelle',
      'multiple' => FALSE,
    ],
    [
      'vid' => 'product_status',
      'field_name' => 'field_te_product_status',
      'easyjob_name' => 'Produktstatus',
      'multiple' => FALSE,
    ],
    [
      'vid' => 'style',
      'field_name' => 'field_te_style',
      'easyjob_name' => 'Stil',
      'multiple' => FALSE,
    ],
    [
      'vid' => 'season',
      'field_name' => 'field_te_season',
      'easyjob_name' => 'Linie',
      'multiple' => FALSE,
    ],
    [
      'vid' => 'occasion',
      'field_name' => 'field_te_occasion',
      'easyjob_name' => 'Anlass',
      'multiple' => FALSE,
    ],
    [
      'vid' => 'motto',
      'field_name' => 'field_te_motto',
      'easyjob_name' => 'Motto',
      'multiple' => FALSE,
    ],
    [
      'vid' => 'furniture_attributes',
      'field_name' => 'field_te_furniture_attributes',
      'easyjob_name' => 'MobiliarEigenschaften',
      'multiple' => TRUE,
    ],
    [
      'vid' => 'gastro_attributes',
      'field_name' => 'field_te_gastro_attributes',
      'easyjob_name' => 'GastroEigenschaften',
      'multiple' => TRUE,
    ],
    [
      'vid' => 'purpose',
      'field_name' => 'field_te_purpose',
      'easyjob_name' => 'Verwendungszweck',
      'multiple' => FALSE,
    ],
  ];

  foreach ($mapping as $key => $item) {
    updateEntityRefenceTermField($variation, $item, $data);
  }

  //$variation->set('attribute_te_color', $data['Farbe']);

}

/**
 * Update product terms with easyjob values
 * @param Product $product the drupal product
 * @param array $data the easyjob data
 */
function updateProductTerms($product, $data) {

  if (!empty($data['Mutterwarengruppe'])) {
    $product_haupt_category_id = fetchTerm('product_category', $data['Mutterwarengruppe']);
  }
  if (!empty($data['Warengruppe'])) {
    $product_main_category_id = fetchTerm('product_category', $data['Warengruppe']);
    $product_main_category = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($product_main_category_id);
    $product_main_category->set('parent', $product_haupt_category_id);
    $product_main_category->save();
  }
  if (!empty($data['Produktkategorie1'])) {
    $product_category_id_1 = fetchTerm('product_category', $data['Produktkategorie1']);
    $product_category_1 = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($product_category_id_1);
    $product_category_1->set('parent', $product_main_category_id);
    $product_category_1->save();
    $product->get('field_te_product_category')->appendItem(['target_id' => $product_category_id_1]);
  }
  if (!empty($data['Produktkategorie2'])) {
    $product_category_id_2 = fetchTerm('product_category', $data['Produktkategorie2']);
    $product_category_2 = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($product_category_id_2);
    $product_category_2->set('parent', $product_main_category_id);
    $product_category_2->save();
    $product->get('field_te_product_category')->appendItem(['target_id' => $product_category_id_2]);
  }
  if (!empty($data['Produktkategorie3'])) {
    $product_category_id_3 = fetchTerm('product_category', $data['Produktkategorie3']);
    $product_category_3 = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($product_category_id_3);
    $product_category_3->set('parent', $product_main_category_id);
    $product_category_3->save();
    $product->get('field_te_product_category')->appendItem(['target_id' => $product_category_id_3]);
  }

}

/**
 * Update product relationships with easyjob values
 * @param Product $product the drupal product
 * @param array $data the easyjob data
 * @param array $context the batch context
 */
function updateProductRelationships($product, $data, &$context) {
  $mapping = [
    [
      'field_name' => 'field_te_additional_products',
      'easyjob_value' => $data['DazuPassend'],
    ],
    [
      'field_name' => 'field_te_alternative_products',
      'easyjob_value' => $data['ReferenzenGebunden'],
    ],
    [
      'field_name' => 'field_te_similar_line_products',
      'easyjob_value' => $data['Modellreihe'],
    ],
  ];
  foreach ($mapping as $key => $item) {
    updateEntityRefenceProductField($product, $item['field_name'], $item['easyjob_value'], $context);
  }

  //field_te_cleaning_price => ReferenzenNormal
}

/**
 * Update a given referenceitem term field for a given entity with the easyjob value.
 * @param Product|ProductVariation $entity the drupal entity
 * @param array $mapping the mapping info for the given field
 * @param string $easyjob_data the easyjob data
 */
function updateEntityRefenceTermField($entity, $mapping, $easyjob_data) {
  if (!empty($easyjob_data[$mapping['easyjob_name']])) {
    if ($mapping['multiple'] === TRUE) {
      $entity->set($mapping['field_name'], []);
      $values = explode(',', $easyjob_data[$mapping['easyjob_name']]);
      foreach ($values as $key => $value) {
        $term_id = fetchTerm($mapping['vid'], $value);
        $entity->get($mapping['field_name'])->appendItem(['target_id' => $term_id]);
      }
    }
    else {
      $term_id = fetchTerm($mapping['vid'], $easyjob_data[$mapping['easyjob_name']]);
      $entity->set($mapping['field_name'], $term_id);
    }
  }
}

/**
 * Update a given referenceitem product field for a given product with the easyjob value.
 * @param Product $product the drupal product
 * @param string $field_name the relationship field_name
 * @param string $easyjob_data the easyjob data
 */
function updateEntityRefenceProductField($product, $field_name, $easyjob_data, &$context) {
  $product->set($field_name, []);
  if (!empty($easyjob_data)) {
    $values = explode(',', $easyjob_data);
    foreach ($values as $key => $value) {
      $results = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties([
        'type' => 'te_product',
        'field_te_item_number_easyjob' => $value,
      ]);

      if (!empty($results)){
        $reference = reset($results);
        $product->get($field_name)->appendItem(['target_id' => $reference->id()]);
      }
      else{
        $context['results']['error']['products']++;
      }

    }
  }
}

/**
 * Fetch matching term or create a new one
 * @param string $vid the vocabulary id
 * @param string $term_value the term value
 * @param string $term_field the term field (default: name)
 */
function fetchTerm($vid, $term_title, $term_field = 'name') {
  $results = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'vid' => $vid,
    $term_field => $term_title,
  ]);

  if (!empty($results)){
    $term = reset($results);
  }
  else {
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->create([
      'vid' => $vid,
      $term_field => $term_title,
      ],
    );
    $term->save();
  }
  return $term->id();
}