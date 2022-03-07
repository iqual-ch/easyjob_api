<?php

namespace Drupal\easyjob_api\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\easyjob_api\Service\EasyjobApiServiceInterface;
/**
 *
 */
class EasyjobProductImportService {

  /**
   * Importer service constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param EasyjobApiServiceInterface $easyjob
   *   The Easyjob Api service
   */
  public function __construct(ConfigFactoryInterface $config_factory, EasyjobApiServiceInterface $easyjob) {
    $this->config = $config_factory->get('easyjob_api.settings');
    $this->easyjob = $easyjob;
  }

  public function getOperations(){
    $products = $this->getProductsToImport();
    $total = count($products);
    $operations = [];

    if ($total > 0) {
      foreach (array_chunk($products, 100) as $batchId => $batch_products) {
        $operations[] = ['_easyjob_api_import_products', [$batch_products, $total]];
      }
      foreach (array_chunk($products, 100) as $batchId => $batch_products) {
        $operations[] = ['_easyjob_api_import_product_relationships', [$batch_products, $total]];
      }
    }
    return $operations;
  }
  

  /**
   *
   */
  public function doImport($operations) {
    \Drupal::logger('easyjob_api')->notice('starting import');

    $batch = [
      'title' => t('Import'),
      'operations' => $operations,
      'finished' => '_easyjob_api_finished_import',
      'file' => drupal_get_path('module', 'easyjob_api') . '/import_batch.inc',
      'init_message' => t('Starting import, this may take a while.'),
      'progress_message' => t('Processed @current out of @total.'),
      'error_message' => t('An error occurred during processing'),
    ];
    batch_set($batch);
  }

  protected function getProductsToImport() {
    $timestamp = $this->config->get('timestamp');
    $date = date('Y-m-d\TH:i:s', $timestamp);
    $products_ids = $this->easyjob->getProductsEditedSince($date);
    $products = $this->easyjob->getProductsDetail($products_ids);
    return $products;
  }

  public function doDelete(){
    $product_ids_to_delete = $this->getProductsToDelete();
    $storage_handler = \Drupal::entityTypeManager()->getStorage('commerce_product');
    $products = $storage_handler->loadMultiple($product_ids_to_delete);
    $storage_handler->delete($entities);
  }

  protected function getProductsToDelete() {
    $products_ids = array_column($this->easyjob->getProductsEditedSince(0), 'ID');
    $query = \Drupal::entityQuery('commerce_product')
    ->condition('type', 'te_product')
    ->condition('field_te_item_number_easyjob', $products_ids, 'NOT IN');
    $results = $query->execute();
    return $results;
  }
}