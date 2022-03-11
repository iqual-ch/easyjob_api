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
  \Drupal::logger('easyjob_api_import')->notice('=== Start import ===');

  if (!isset($context['sandbox']['progress']['products'])) {
    $context['sandbox']['progress']['products'] = 0;
    $context['sandbox']['current_index']['products'] = 0;
    $context['sandbox']['current_max']['products'] = min(100, count($products));
    $context['sandbox']['max']['products'] = $total - 1;
    $context['sandbox']['start']['products'] = microtime(TRUE);

  }
  if (!isset($context['results']['products']) || empty($context['results']['products'])) {
    $context['results']['products'] = $products;
    $context['results']['progress']['products'] = 0;
    $context['results']['imported']['products'] = 0;
    $context['results']['updated']['products'] = 0;
    $context['results']['error']['products'] = 0;
  }

  $indexes = range($context['sandbox']['progress']['products'], min($context['sandbox']['progress']['products'] + 99, $context['sandbox']['max']['products']));
  foreach ($indexes as $i) {
    $context['sandbox']['progress']['products']++;
    $context['sandbox']['current_index']['products'] = $i;
    if ($products[$i] == NULL) {
      continue;
    }
    try {
        $product = process_product($products[$i]['ID'], $context);
        if ($product) {
          \Drupal::entityTypeManager()->getStorage('commerce_product')->resetCache([$product->id()]);
        }
        else {
          $context['results']['error']['products']++;
        }
    } catch (\Exception $e) {
      \Drupal::logger('easyjob_api')->notice('error: ' . $e->getMessage());
      $context['results']['error']['products']++;
    }
  }

  gc_collect_cycles();

  $context['results']['progress']['products'] += $context['sandbox']['current_max']['products'];
  $processed_rel = $context['results']['progress']['products'] / ($context['sandbox']['max']['products'] + 1) * 100;
  $context['message'] = 'Processed ' . $context['results']['progress']['products'] . ' of ' . ($context['sandbox']['max']['products'] + 1) . ' records (' . round($processed_rel) . ' %).';
}

/**
 *
 */
function _easyjob_api_import_product_relationships($products, $total, &$context) {
  if (count($products) < 1) {
    \Drupal::logger('easyjob_api_import')->notice('nothing to import');
    return;
  }

  if (!isset($context['sandbox']['progress']['relationships'])) {
    $context['sandbox']['progress']['relationships'] = 0;
    $context['sandbox']['current_index']['relationships'] = 0;
    $context['sandbox']['current_max']['relationships'] = min(100, count($products));
    $context['sandbox']['max']['relationships'] = $total - 1;
    $context['sandbox']['start']['relationships'] = microtime(TRUE);

  }
  if (!isset($context['results']['relationships']) || empty($context['results']['relationships'])) {
    $context['results']['relationships'] = $products;
    $context['results']['progress']['relationships'] = 0;
    $context['results']['updated']['relationships'] = 0;
    $context['results']['error']['relationships'] = 0;
  }

  $indexes = range($context['sandbox']['progress']['relationships'], min($context['sandbox']['progress']['relationships'] + 99, $context['sandbox']['max']['relationships']));
  foreach ($indexes as $i) {
    $context['sandbox']['progress']['relationships']++;
    $context['sandbox']['current_index']['relationships'] = $i;
    if ($products[$i] == NULL) {
      continue;
    }
    try {
      $product = process_product_relationships($products[$i]['ID'], $context);
      if ($product) {
        \Drupal::entityTypeManager()->getStorage('node')->resetCache([$product->id()]);
        $context['results']['updated']['relationships']++;
      }
      else {
        $context['results']['error']['relationships']++;
      }
    } catch (\Exception $e) {
      \Drupal::logger('easyjob_api')->notice('error: ' . $e->getMessage());
      $context['results']['error']['relationships']++;
    }
  }

  gc_collect_cycles();

  $context['results']['progress']['relationships'] += $context['sandbox']['current_max']['relationships'];
  $processed_rel = $context['results']['progress']['relationships'] / ($context['sandbox']['max']['relationships'] + 1) * 100;
  $context['message'] = 'Relationships updated for ' . $context['results']['progress']['relationships'] . ' of ' . ($context['sandbox']['max']['relationships'] + 1) . ' records (' . round($processed_rel) . ' %).';
}

/**
 *
 */
function _easyjob_api_finished_import($success, $results, $operations) {
  $msg = "\n" . '=== Import finished ===';
  if (!empty($results['products'])) {
    $msg .= "\n" . 'Imported ' . $results['imported']['products'] . ' products and updated ' . $results['updated']['products'];
    $msg .= "\n" . 'Errors: ' . $results['error']['products'];
  }
  if (!empty($results['relationships'])) {
    $msg .= "\n" . 'Missing relationships: ' . $results['error']['relationships'];
  }
  $msg .= "\n" . '======';
  \Drupal::logger('easyjob_api_import')->notice($msg);
  $config = \Drupal::getContainer()->get('config.factory')->getEditable('easyjob_api.settings');
  $config->set('timestamp', time());
  $config->save();

  return 0;
}

/**
 *
 */
function process_product($product_id, &$context) {

  $product_data = \Drupal::service('easyjob_api.client')->getSingleProductDetail($product_id);
  if ($product_data) {
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

    updateVariationAttributes($variation, $product_data);

    $product->save();
    $variation->save();

    //Log
    /* if (!empty($product_data['Bild'])) {
      \Drupal::logger('easyjob_api_import')->notice('product with ID ' . $product_data['ID'] . ' has an image');
    }
    if (!empty($product_data['Anhaenge'])) {
      \Drupal::logger('easyjob_api_import')->notice('product with ID ' . $product_data['ID'] . ' has an attachment');
    } */
   /*  if (!empty($product_data['Farbvarianten'])) {
      \Drupal::logger('easyjob_api_import')->notice('product with ID ' . $product_data['ID'] . ' has a variant');
    } */
    /* if (!empty($product_data['ReferenzenNormal']) && count(explode(',', $product_data['ReferenzenNormal'])) > 1) {
      \Drupal::logger('easyjob_api_import')->notice('product with ID ' . $product_data['ID'] . ' has more than one references');
    } */

    return $product;
  }
  return FALSE;
}

/**
 *
 */
function process_product_relationships($product_id, &$context) {
  
  $product_data = \Drupal::service('easyjob_api.client')->getSingleProductDetail($product_id);
  if ($product_data) {
    $easyjob_id = $product_data['ID'];
    $product = fetchProduct($easyjob_id);
    updateProductRelationships($product, $product_data, $context);
    if (!empty($product_data['Farbvarianten'])) {
      updateProductVariations($product, $product_data, $context);
    }
    $product->save();
    return $product;
  }
  return FALSE;
}

/**
 * Fetch matching variation or create a new one
 * @param int $id the easyjob id
 * @param boolean $create wheter to create one if it does not exist
 * @return ProductVariation|null $variation
 */
function fetchVariation($id, $create = TRUE) {
    $results = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->loadByProperties([
      'type' => 'te_product',
      'field_te_main_number_easyjob' => $id,
    ]);

    if (!empty($results)){
      $variation = reset($results);
    }
    else {
      if ($create) {
        $variation = ProductVariation::create(['type' => 'te_product']);
        $variation->set('field_te_main_number_easyjob', $id);
        $variation->save();
      }
      else {
        $variation = NULL;
      }
    }
  return $variation;
}

/**
 * Fetch matching variation or create a new one
 * @param int $id the easyjob id
 * @param array $context the import context array
 */
function fetchProduct($id, &$context = []) {
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

    // Missing fields -- See with Protonics
    // youtube link
    // Reference product amount
    // quantity
}

/**
 * Update variation fields with easyjob values
 * @param ProductVariation $variation the drupal product variation
 * @param array $data the easyjob data
 */
function updateVariationFields($variation, $data) {
    $variation->set('field_st_variation_title', $data['Titel']);
    $variation->set('field_te_width', $data['Breite']);
    $variation->set('field_te_height', $data['Hoehe']);
    $variation->set('field_te_depth', $data['Tiefe']);
    $variation->set('field_te_volume', $data['Volumen']);
    $variation->set('field_te_diameter', $data['Durchmesser']);
    $price = ($data['Verbrauchsartikel']) ? $data['Vermietartikel'] :  $data['Verkaufspreis'];
    $variation->set('field_te_price_per_item', $price);
    $variation->set('field_te_voltage_v', $data['Scheinleistung']);
    $variation->set('field_te_effective_power_w', $data['Wirkleistung']);

    // Missing fields -- See with Protonics
    // weight
    // quantity
    // connector
    // Price per single item
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

}

/**
 * Update variation attribute with easyjob values
 * @param ProductVariation $variation the drupal product variation
 * @param array $data the easyjob data
 */
function updateVariationAttributes($variation, $data) {
  if (!empty($data['Farbe'])) {
    $color_attribute_value = fetchAttributeValue('te_color', 'Color', $data['Farbe']);
    $variation->set('attribute_te_color', $color_attribute_value);
  }
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
      'easyjob_value' => $data['Alternativen'],
    ],
    [
      'field_name' => 'field_te_similar_line_products',
      'easyjob_value' => $data['Modellreihe'],
    ],
    [
      'field_name' => 'field_te_reference_product',
      'easyjob_value' => $data['ReferenzenGebunden'],
    ],
  ];
  foreach ($mapping as $key => $item) {
    updateEntityRefenceProductField($product, $item['field_name'], $item['easyjob_value'], $context);
  }

  //field_te_cleaning_price => ReferenzenNormal
}

/**
 * Add variations to product.
 * @param Product $product the drupal product
 * @param array $data the easyjob data
 * @param array $context the batch context
 */
function updateProductVariations($product, $data, &$context) {
  $variations = explode(',', $data['Farbvarianten']);
  foreach ($variations as $key => $variation_id) {
    $variation = fetchVariation($variation_id, FALSE);
    if ($variation) {
      $product->addVariation($variation);
    }
    else {
      $context['results']['error']['relationships']++;
    }
  }
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
        $context['results']['error']['relationships']++;
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

/**
 * Fetch matching term or create a new one
 * @param string $attribute the product attribute id
 * @param string $label the product attribute label
 * @param string $value the product attribute value
 * @return \Drupal\commerce_product\Entity\ProductAttributeValue $attribute_value
 */
function fetchAttributeValue($attribute_id, $label, $value) {
  $attribute = \Drupal\commerce_product\Entity\ProductAttribute::load($attribute_id);
  if (!$attribute) {
    $attribute = \Drupal\commerce_product\Entity\ProductAttribute::create([
      'id' => $attribute_id,
      'label' => $label,
    ]);
  }
  $storage = \Drupal::entityTypeManager()->getStorage('commerce_product_attribute_value');
  $attribute_values = $storage->loadByProperties([
    'attribute' => $attribute_id,
    'name' => $value,
  ]);

  $attribute_value = (!empty($attribute_values)) ? reset($attribute_values) : NULL;
  if (!$attribute_value) {
    $attribute_value = \Drupal\commerce_product\Entity\ProductAttributeValue::create([
      'attribute' => $attribute_id,
      'name' => $value,
    ]);
    $attribute_value->save();
  }

  return $attribute_value;
}