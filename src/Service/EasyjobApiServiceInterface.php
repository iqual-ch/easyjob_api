<?php

namespace Drupal\easyjob_api\Service;

/**
 * Define the available methods for the EasyjobApiService.
 *
 * @package Drupal\easyjob_api
 */
interface EasyjobApiServiceInterface {

  /**
   * Get all products edited since provided date.
   *
   * @param string $date
   *   The edited since date (ISO Format)
   *
   * @return array
   *   An array of product ids matching the given parameters
   */
  public function getProductsEditedSince($date = NULL);

  /**
   * Retrieve a single product data from easyjob.
   *
   * @param int $product_id
   *   The easyjob product id.
   *
   * @return array
   *   An array containing the product data.
   */
  public function getSingleProductDetail($product_id);

  /**
   * Retrieve a single file data from easyjob.
   *
   * @param int $file_id
   *   The easyjob file id.
   *
   * @return array
   *   An array containing the file information.
   */
  public function getSingleFileDetail($file_id);

  /**
   * Retrieve multiple products data from easyjob.
   *
   * @param array $product_ids
   *   An array of easyjob product ids.
   *
   * @return array
   *   An array of products data array.
   */
  public function getProductsDetail(array $product_ids);

  /**
   * Retrieves Product Availability from given arguments.
   *
   * @param array $args
   *   An array containing the product_id, the quantity required,
   *   the start and end date.
   *
   * @return array
   *   An array containing the quantity available and the calculated price
   */
  public function getProductAvailabilityForPeriod(array $args);

  /**
   * Creates a Project in easyjob via POST request.
   *
   * @param array $data
   *   The order / project data from Drupal.
   *
   * @return array
   *   An array containing JobID, User Address ID and Delivery Address ID.
   */
  public function createProject(array $data);

  /**
   * Retrieves Project Data.
   *
   * @param int $id
   *   The easyjob project id.
   *
   * @return mixed
   *   An array containing the project data or null.
   */
  public function getProject($id);

  /**
   * Retrieves Job Data.
   *
   * @param int $id
   *   The easyjob job id.
   *
   * @return mixed
   *   An array containing the job data or null.
   */
  public function getJob($id);

}
