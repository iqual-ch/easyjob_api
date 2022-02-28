<?php

namespace Drupal\easyjob_api;

/**
 * Interface EasyjobApiServiceInterface
 *
 * @package Drupal\easyjob_api
 */
interface EasyjobApiServiceInterface {

  /**
   * @param array $params
   * Available parameters:
   * - editedsince: timestamp
   * - searchtext: string
   * - idCategoryParent: int
   * - idCategory: int
   * @return array of product ids matching the given parameters
   */
  public function getAllProductsMatchingParameters($params = []);

  /**
   * @param int $product_id
   * @return array
   */
  public function getProductDetail($product_id);

  /**
   * @param int $product_id
   * @param string $start
   * @param string $end
   * @return array
   */
  public function getProductAvailabilityForPeriod($product_id, $start, $end);

  /**
   * 
   * @param array $data
   *
   * @return mixed
   */
  public function createOrder($data);
  
}
