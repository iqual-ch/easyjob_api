<?php

namespace Drupal\easyjob_api\Service;

/**
 * Interface EasyjobApiServiceInterface
 *
 * @package Drupal\easyjob_api
 */
interface EasyjobApiServiceInterface {

  /**
   * @param string $date
   * The edited since date (ISO Format)
   * @return array of product ids matching the given parameters
   */
  public function getProductsEditedSince($date = null);

  /**
   * Retrieve a single product data from easyjob.
   * @param int $product_id
   * @return array
   */
  public function getSingleProductDetail($product_id);

  /**
   * Retrieve multiple products data from easyjob
   * @param array $product_ids
   * @return array
   */
  public function getProductsDetail($product_ids);

  /**
   * Retrieves Product Availability from given arguments
   * @param array $args
   *   An array containing the product_id, the quantity required,
   *   the start and end date.
   * 
   * @return array
   *   An array containing the quantity available and the calculated price
   */
  public function getProductAvailabilityForPeriod($args);

  /**
   *
   * @param array $data
   *
   * @return mixed
   */
  public function createProject($data);

}
