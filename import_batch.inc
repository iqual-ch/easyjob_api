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
    //TEST ONLY TO REMOVE
    if ($i > 1) {
        continue;
    }
    try {
        $product = processProduct($products[$i], $context);
        \Drupal::entityTypeManager()->getStorage('node')->resetCache([$product->id()]);
    } catch (\Exception $e) {
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
function processUser($product_data, &$context){
    //Create if new product, update if existing
    $easyjob_id = $product_data['ID'];
    $database = \Drupal::database();
    $query = $database->select('node__field_te_item_number_easyjob', 'u')
    ->condition('u.field_te_item_number_easyjob_value', $easyjob_id, '=')
    ->fields('u', ['entity_id'])
    ->range(0, 1)
    ->execute();
    $result = $query->fetchObject();

    if ($result){
        $product = Product::load($result->entity_id);
        $context['results']['updated']['products']++;
    }
    else {
      $product = Product::create();
      $product->set('field_te_item_number_easyjob', $easyjob_id);
      $context['results']['imported']['products']++;
    }


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