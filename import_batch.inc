<?php

/**
 * Batch import processing Easyjob products.
 * @file
 */

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\Product;
use Drupal\Core\File\FileSystemInterface;

/**
 *
 */
function _easyjob_api_import_products($products, $total, &$context) {
  if (count($products) < 1) {
    \Drupal::logger('easyjob_api_import')->notice('nothing to import');
    return;
  }

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
    $context['results']['missing']['categories'] = ['total' => 0];
    $context['results']['error']['files'] = ['total' => 0];
    $context['results']['error']['images'] = ['total' => 0];
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
    $context['results']['error']['relationships'] = ['total' => 0];
    $context['results']['error']['cleaning_products'] = ['total' => 0];
    $context['results']['error']['variations'] = ['total' => 0];
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
        \Drupal::entityTypeManager()->getStorage('commerce_product')->resetCache([$product->id()]);
        $context['results']['updated']['relationships']++;
      }
      else {
        $context['results']['error']['relationships']['total']++;
      }
    } catch (\Exception $e) {
      \Drupal::logger('easyjob_api')->notice('error: ' . $e->getMessage());
      $context['results']['error']['relationships']['total']++;
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
function _easyjob_api_delete_obsolete_products($product_ids, &$context) {
  $storage_handler = \Drupal::entityTypeManager()->getStorage('commerce_product');
  $products = $storage_handler->loadMultiple($product_ids);
  //to do: delete cleaning variations
  $storage_handler->delete($products);
  $context['results']['deleted']['products']['total'] = count($product_ids);
  $context['results']['deleted']['products']['ids'] = $product_ids;
}

/**
 *
 */
function _easyjob_api_delete_obsolete_terms(&$context) {
  $context['results']['deleted']['terms']['total'] = 0;
  $terms_to_delete = [];
  $mapping = [
    [
      'vid' => 'connector',
      'field_name' => 'field_te_connector',
    ],
    [
      'vid' => 'form',
      'field_name' => 'field_te_form',
    ],
    [
      'vid' => 'line',
      'field_name' => 'field_te_line',
    ],
    [
      'vid' => 'material',
      'field_name' => 'field_te_material',
    ],
    [
      'vid' => 'source_of_energy',
      'field_name' => 'field_te_source_of_energy',
    ],
    [
      'vid' => 'product_status',
      'field_name' => 'field_te_product_status',
    ],
    [
      'vid' => 'style',
      'field_name' => 'field_te_style',
    ],
    [
      'vid' => 'season',
      'field_name' => 'field_te_season',
    ],
    [
      'vid' => 'occasion',
      'field_name' => 'field_te_occasion',
    ],
    [
      'vid' => 'motto',
      'field_name' => 'field_te_motto',
    ],
    [
      'vid' => 'furniture_attributes',
      'field_name' => 'field_te_furniture_attributes',
    ],
    [
      'vid' => 'gastro_attributes',
      'field_name' => 'field_te_gastro_attributes',
    ],
    [
      'vid' => 'purpose',
      'field_name' => 'field_te_purpose',
    ],
  ];
  foreach ($mapping as $item) {
    $terms = Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $item['vid'])
    ->execute();
    foreach ($terms as $term) {
      $product_assignments = Drupal::entityQuery('commerce_product_variation')
      ->condition($item['field_name'], $term)
      ->count()
      ->execute();
      if (entity_type_has_field('node', $item['field_name'])) {
        $node_assignments = Drupal::entityQuery('node')
        ->condition($item['field_name'], $term)
        ->count()
        ->execute();
      }
      else {
        $node_assignments = 0;
      }

      if (($node_assignments + (int) $product_assignments) == 0) {
        $terms_to_delete[] = $term;
        $context['results']['deleted']['terms']['total']++;
        $context['results']['deleted']['terms'][$item['vid']][] = $term;
      }
    }
  }

  $storage_handler = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $terms = $storage_handler->loadMultiple($terms_to_delete);
  $storage_handler->delete($terms);
}

/**
 *
 */
function _easyjob_api_finished_import($success, $results, $operations) {
  $msg = "\n" . '=== Import finished ===';
  if (!empty($results['products'])) {
    $msg .= "\n" . 'Imported ' . $results['imported']['products'] . ' products, updated ' . $results['updated']['products'] . ' and deleted ' . $results['deleted']['products']['total'] ;
    $msg .= "\n" . 'Errors: ' . $results['error']['products'];
  }
  if ($results['error']['relationships']['total'] > 0) {
    $msg .= "\n" . 'Missing relationships: ' . $results['error']['relationships']['total'];
    foreach ($results['error']['relationships'] as $easyjob_id => $relationships) {
      if ($easyjob_id != "total") {
        $msg .= "\n" . '--- Product : ' . $easyjob_id . '---';
        foreach ($relationships as $product) {
          $msg .= "\n" . $product;
        }
      }
    }
  }
  if ($results['error']['cleaning_products']['total'] > 0) {
    $msg .= "\n" . 'Missing cleaning products: ' . $results['error']['cleaning_products']['total'];
    foreach ($results['error']['cleaning_products'] as $easyjob_id => $cleaning_products) {
      if ($easyjob_id != "total") {
        $msg .= "\n" . '--- Product : ' . $easyjob_id . '---';
        foreach ($cleaning_products as $product) {
          $msg .= "\n" . $product;
        }
      }
    }
  }
  if ($results['error']['variations']['total'] > 0) {
    $msg .= "\n" . 'Missing variations: ' . $results['error']['variations']['total'];
    foreach ($results['error']['variations'] as $easyjob_id => $variations) {
      if ($easyjob_id != "total") {
        $msg .= "\n" . '--- Product : ' . $easyjob_id . '---';
        foreach ($variations as $variations) {
          $msg .= "\n" . $variations;
        }
      }
    }
  }
  if ($results['error']['files']['total'] > 0) {
    $msg .= "\n" . 'Missing files: ' . $results['error']['files']['total'];
    foreach ($results['error']['files'] as $easyjob_id => $files) {
      if ($easyjob_id != "total") {
        $msg .= "\n" . '--- Product : ' . $easyjob_id . '---';
        foreach ($files as $file) {
          $msg .= "\n" . $file;
        }
      }
    }
  }
  if ($results['error']['images']['total'] > 0) {
    $msg .= "\n" . 'Missing images: ' . $results['error']['images']['total'];
    foreach ($results['error']['images'] as $easyjob_id => $images) {
      if ($easyjob_id != "total") {
        $msg .= "\n" . '--- Product : ' . $easyjob_id . '---';
        foreach ($images as $image) {
          $msg .= "\n" . $image;
        }
      }
    }
  }
  if ($results['missing']['categories']['total'] > 0) {
    $msg .= "\n" . 'Missing categories: ' . $results['missing']['categories']['total'];
    foreach ($results['missing']['categories'] as $parent => $categories) {
      if ($parent != "total") {
        $msg .= "\n" . '--- Parent category: ' . $parent . '---';
        foreach ($categories as $cat) {
          $msg .= "\n" . $cat;
        }
      }
    }
  }
  if ($results['deleted']['products']['total'] > 0) {
    $msg .= "\n" . 'Deleted products: ' . $results['deleted']['products']['total'];
    foreach ($results['deleted']['products']['ids'] as $key => $id) {
      $msg .= "\n" . $id;
    }
  }
  if ($results['deleted']['terms']['total'] > 0) {
    $msg .= "\n" . 'Deleted terms: ' . $results['deleted']['terms']['total'];
    foreach ($results['deleted']['terms'] as $vid => $terms) {
      if ($vid != "total") {
        $msg .= "\n" . '--- Sector: ' . $vid . '---';
        foreach ($terms as $term) {
          $msg .= "\n" . $term;
        }
      }
    }
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
    $type = ($product_data['Warengruppe'] == 'Reinigung') ? 'te_cleaning_product' : 'te_product';
    $easyjob_id = $product_data['ID'];
    $variation = fetch_variation($easyjob_id, $type);
    $product = ($type == 'te_cleaning_product') ? fetch_product(9999, $type, $context) : fetch_product($easyjob_id, $type, $context);
    $product->addVariation($variation);
    if (empty($variation->sku->value)) {
      $variation->set('sku', $product->id() . '-' . $variation->id());
    }
    if ($type == 'te_cleaning_product') {
      update_cleaning_product_fields($variation, $product_data);
    }
    else {
      update_product_fields($product, $product_data);
      update_variation_fields($variation, $product_data);
      update_variation_terms($variation, $product_data);
      update_product_terms($product, $product_data, $context);
      update_variation_attributes($variation, $product_data);
      clean_variation_images($variation);
      clean_product_files_field($product, 'field_te_pdf_instructions');
      clean_product_files_field($product, 'field_te_pdf_safety_info');
      if ($product_data['Bild']) {
        update_variation_images($variation, $product_data, $context);
      }
      if ($product_data['Anhaenge']) {
        update_product_files($product, $product_data, $context);
      }
    }

    $product->save();
    $variation->save();

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
    $type = ($product_data['EigeneNummer'] == '9999') ? 'te_cleaning_product' : 'te_product';
    if ($type == 'te_product') {
      $easyjob_id = $product_data['ID'];
      $product = fetch_product($easyjob_id, 'te_product');
      update_product_relationships($product, $product_data, $context);
      if (!empty($product_data['Farbvarianten'])) {
        update_product_variations($product, $product_data, $context);
      }
      if (!empty($product_data['ReferenzenNormal'])) {
        $variation = fetch_variation($easyjob_id, 'te_product', FALSE);
        if ($variation) {
          update_cleaning_product($variation, $product_data, $context);
        }
      }
      $product->save();
      return $product;
    }
  }
  return FALSE;
}


/**
 * Fetch matching variation or create a new one
 * @param int $id the easyjob id
 * @param string $type the variation type
 * @param boolean $create wheter to create one if it does not exist
 * @return ProductVariation|null $variation
 */
function fetch_variation($id, $type, $create = TRUE) {
    $results = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->loadByProperties([
      'type' => $type,
      'field_te_main_number_easyjob' => $id,
    ]);

    if (!empty($results)){
      $variation = reset($results);
    }
    else {
      if ($create) {
        $variation = ProductVariation::create(['type' => $type]);
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
 * @param int $id the easyjob id$
 * @param string $type the product type
 * @param array $context the import context array
 */
function fetch_product($id, $type, &$context = []) {
    $results = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties([
      'type' => $type,
      'field_te_item_number_easyjob' => $id,
    ]);

    if (!empty($results)){
      $product = reset($results);
      $context['results']['updated']['products']++;
    }
    else {
      $product = Product::create([
          'type' => $type,
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
function update_product_fields($product, $data) {
    $product->set('title', $data['Titel']);
    ($data['Publiziert']) ? $product->set('status', 1) : $product->set('status', 0);
    $product->set('field_te_item_number_intern', $data['Nummer']);
    $product->set('field_te_seo_text', $data['Beschreibung']);
    $product->set('field_te_additional_information', $data['Zusatzinformationen']);
    $product->set('field_te_quantity', $data['Verpackungseinheit']);
    $price = ($data['Verbrauchsartikel']) ? $data['Vermietartikel'] :  $data['Verkaufspreis'];
    $product->set('field_te_price_per_single_item', $price);

    // Missing fields -- See with Protonics
    // youtube link
    // Reference product amount
}

/**
 * Update variation fields with easyjob values
 * @param ProductVariation $variation the drupal product variation
 * @param array $data the easyjob data
 */
function update_variation_fields($variation, $data) {
    $variation->set('title', $data['Titel']);
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
    $variation->set('field_te_quantity', $data['Verpackungseinheit']);
    $variation->set('field_te_unit', $data['Einheit']);
    $variation->set('field_te_weight', $data['Weight']);
}

/**
 * Update cleaning product fields with easyjob values
 * @param ProductVariation $variation the drupal cleaning product variation
 * @param array $data the easyjob data
 */
function update_cleaning_product_fields($variation, $data) {
  $variation->set('title', $data['Titel']);
  $variation->set('field_st_variation_title', $data['Titel']);
  $price = ($data['Verbrauchsartikel']) ? $data['Vermietartikel'] :  $data['Verkaufspreis'];
  $variation->set('field_te_price_per_item', $price);
  $variation->set('field_te_quantity', $data['Verpackungseinheit']);
  $variation->set('field_te_unit', $data['Einheit']);
}


/**
 * Update variation terms with easyjob values
 * @param ProductVariation $variation the drupal product variation
 * @param array $data the easyjob data
 */
function update_variation_terms($variation, $data) {

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
      'multiple' => TRUE,
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
      'multiple' => TRUE,
    ],
    [
      'vid' => 'connector',
      'field_name' => 'field_te_connector',
      'easyjob_name' => 'PowerConnection',
      'multiple' => FALSE,
    ],
  ];

  foreach ($mapping as $key => $item) {
    update_entity_reference_term_field($variation, $item, $data);
  }

}

/**
 * Update variation attribute with easyjob values
 * @param ProductVariation $variation the drupal product variation
 * @param array $data the easyjob data
 */
function update_variation_attributes($variation, $data) {
  if (!empty($data['Farbe'])) {
    $color_attribute_value = fetchAttributeValue('te_color', 'Color', $data['Farbe']);
    $variation->set('attribute_te_color', $color_attribute_value);
  }
}

/**
 * Update product terms with easyjob values
 * @param Product $product the drupal product
 * @param array $data the easyjob data
 * @param array $context the import context array
 */
function update_product_terms($product, $data, &$context) {
  $product->set('field_te_product_category', []);
  for ($i=1; $i<=3; $i++) {
    if (!empty($data['Produktkategorie' . $i])) {
      $product_category_id = fetch_term('product_category', $data['Produktkategorie' . $i], 'name', FALSE);
      if ($product_category_id) {
        $product_category = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($product_category_id);
        $product_category->save();
        $product->get('field_te_product_category')->appendItem(['target_id' => $product_category_id]);
      }
      else {
        // ADD TO LOG
        if (empty($context['results']['missing']['categories'][$data['Warengruppe']]) || !in_array($data['Produktkategorie' . $i], $context['results']['missing']['categories'][$data['Warengruppe']])) {
          $context['results']['missing']['categories'][$data['Warengruppe']][] = $data['Produktkategorie' . $i];
          $context['results']['missing']['categories']['total']++;
        }
      }
    }
  }
}

/**
 * Update product relationships with easyjob values
 * @param Product $product the drupal product
 * @param array $data the easyjob data
 * @param array $context the batch context
 */
function update_product_relationships($product, $data, &$context) {
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
    update_entity_reference_field($product, $item['field_name'], $item['easyjob_value'], $context);
  }
}

/**
 * Update product pdfs with easyjob values
 * @param Product $product the drupal product
 * @param array $data the easyjob data
 * @param array $context the batch context
 */
function update_product_files($product, $data, &$context) {
  $images_ids = explode(',', $data['Bild']);
  $files_ids = explode(',', $data['Anhaenge']);
  if (!empty($files_ids)) {
    foreach ($files_ids as $id) {
      if (!in_array($id, $images_ids)) {
        $path = \Drupal::service('easyjob_api.client')->getSingleFileDetail($id);
        if ($path) {
          $file = system_retrieve_file($path, NULL, TRUE, FileSystemInterface::FILE_EXISTS_REPLACE);
          if ($file) {
            $media = Media::create([
              'bundle' => 'document',
              'uid' => '0',
              'field_media_document' => [
                'target_id' => $file->id(),
              ],
            ]);
            $media->setPublished(TRUE)->save();
            // CHECK HOW TO DETERMINE WHICH FILE IT IS
            // $product->get('field_te_pdf_instructions')->appendItem(['target_id' => $media->id()]);
            // $product->get('field_te_pdf_safety_info')->appendItem(['target_id' => $media->id()]);
          }
          else {
            $context['results']['error']['files']['total']++;
            $context['results']['error']['files'][$data['ID']][] = $id;
          }
        }
        else {
          $context['results']['error']['files']['total']++;
          $context['results']['error']['files'][$data['ID']][] = $id;
        }
      }
    }
  }
}

/**
 * Update variation images with easyjob values
 * @param ProductVariation $variation the drupal product variation
 * @param array $data the easyjob data
 * @param array $context the batch context
 */
function update_variation_images($variation, $data, &$context) {
  $images_ids = explode(',', $data['Bild']);
  if (!empty($images_ids)) {
    foreach ($images_ids as $id) {
      $path = \Drupal::service('easyjob_api.client')->getSingleFileDetail($id);
      if ($path) {
        $file = system_retrieve_file($path, NULL, TRUE, FileSystemInterface::FILE_EXISTS_REPLACE);
        if ($file) {
          $media = Media::create([
            'bundle' => 'image',
            'uid' => '0',
            'field_media_image' => [
              'target_id' => $file->id(),
            ],
          ]);
          $media->setPublished(TRUE)->save();
          $variation->get('field_iq_commerce_images')->appendItem(['target_id' => $media->id()]);
        }
        else {
          $context['results']['error']['images']['total']++;
          $context['results']['error']['images'][$data['ID']][] = $id;
        }
      }
      else {
        $context['results']['error']['images']['total']++;
        $context['results']['error']['images'][$data['ID']][] = $id;
      }
    }
  }
}

/**
 * Add variations to product.
 * @param Product $product the drupal product
 * @param array $data the easyjob data
 * @param array $context the batch context
 */
function update_product_variations($product, $data, &$context) {
  $variations = explode(',', $data['Farbvarianten']);
  foreach ($variations as $key => $variation_id) {
    $variation = fetch_variation($variation_id, 'te_product', FALSE);
    if ($variation) {
      $product->addVariation($variation);
    }
    else {
      $context['results']['error']['variations']['total']++;
      $context['results']['error']['variations'][$data['ID']][] = $variation_id;
    }
  }
}

/**
 * Link cleaning product to the product
 * @param ProductVariation $variation the drupal product variation
 * @param array $data the easyjob data
 * @param array $context the batch context
 */
function update_cleaning_product($variation, $data, &$context) {
  // REMOVE NEXT LINE IF THE CUSTOMER DECIDE TO USER THIS FIELD FOR OTHER PRODUCTS
  $variation->set('field_iq_commerce_required', []);
  $referenced_product_ids = explode(',', $data['ReferenzenNormal']);
  foreach ($referenced_product_ids as $key => $easyjob_id) {
    $results = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->loadByProperties([
      'type' => 'te_cleaning_product',
      'field_te_main_number_easyjob' => $easyjob_id,
    ]);
    if (!empty($results)){
      $cleaning_product = reset($results);
      $variation->get('field_iq_commerce_required')->appendItem(['target_id' => $cleaning_product->id()]);
    }
    else {
      $context['results']['error']['cleaning_products']['total']++;
      $context['results']['error']['cleaning_products'][$data['ID']][] = $easyjob_id;
    }
  }
}


/**
 * Update a given referenceitem term field for a given entity with the easyjob value.
 * @param Product|ProductVariation $entity the drupal entity
 * @param array $mapping the mapping info for the given field
 * @param string $easyjob_data the easyjob data
 */
function update_entity_reference_term_field($entity, $mapping, $easyjob_data) {
  if ($mapping['multiple'] === TRUE) {
    $entity->set($mapping['field_name'], []);
  }
  else {
    $entity->set($mapping['field_name'], '');
  }
  if (!empty($easyjob_data[$mapping['easyjob_name']])) {
    if ($mapping['multiple'] === TRUE) {
      $values = explode(',', $easyjob_data[$mapping['easyjob_name']]);
      foreach ($values as $key => $value) {
        $term_id = fetch_term($mapping['vid'], $value);
        $entity->get($mapping['field_name'])->appendItem(['target_id' => $term_id]);
      }
    }
    else {
      $term_id = fetch_term($mapping['vid'], $easyjob_data[$mapping['easyjob_name']]);
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
function update_entity_reference_field($product, $field_name, $easyjob_data, &$context) {
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
      else {
        $context['results']['error']['relationships']['total']++;
        $context['results']['error']['relationships'][$product->field_te_item_number_easyjob->value][] = $value;
      }

    }
  }
}

/**
 * Delete all files attached to a product.
 * @param Product $product the drupal product
 * @param string $field_name the field to clean
 *
 * @return void
 */
function clean_product_files_field(Product $product, $field_name) {
  $storage_handler = \Drupal::entityTypeManager()->getStorage("media");
  $files_ids = $product->get($field_name)->getValue();
  $files = $storage_handler->loadMultiple($files_ids);
  $storage_handler->delete($files);
  $product->set($field_name, []);
}

/**
 * Delete all images attached to a product variation.
 * @param ProductVariation $variation the drupal product variation
 *
 * @return void
 */
function clean_variation_images(ProductVariation $variation) {
  $storage_handler = \Drupal::entityTypeManager()->getStorage("media");
  $files_ids = $variation->get('field_iq_commerce_images')->getValue();
  $files = $storage_handler->loadMultiple($files_ids);
  $storage_handler->delete($files);
  $variation->set('field_iq_commerce_images', []);
}

/**
 * Fetch matching term or create a new one
 * @param string $vid the vocabulary id
 * @param string $term_value the term value
 * @param string $term_field the term field (default: name)
 * @param boolean $create wheter to create a new one or not
 *
 * @return int|null
 *    the id of the term or null
 */
function fetch_term($vid, $term_title, $term_field = 'name', $create = TRUE) {
  $term = NULL;
  $results = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'vid' => $vid,
    $term_field => $term_title,
  ]);

  if (!empty($results)){
    $term = reset($results);
  }
  elseif ($create) {
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->create([
      'vid' => $vid,
      $term_field => $term_title,
      ],
    );
    $term->save();
  }
  return ($term) ? $term->id() : NULL;
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


/**
 * Check if an entity type has a field.
 *
 * @param string $entity_type
 *   The entity type.
 * @param string $field_name
 *   The field name.
 *
 * @return bool
 *   Returns a TRUE if the entity type has the field.
 */
function entity_type_has_field($entity_type = 'node', $field_name) {
  $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);

  foreach ($bundles as $bundle => $label) {
    $all_bundle_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
    if (isset($all_bundle_fields[$field_name])) {
      return TRUE;
    }
  }

  return FALSE;
}