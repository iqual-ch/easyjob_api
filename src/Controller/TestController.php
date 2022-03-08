<?php

namespace Drupal\easyjob_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\easyjob_api\Service\EasyjobApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Test Controller to Test Easyjob API
 */
class TestController extends ControllerBase {

  /**
   * Easyjob Service.
   *
   * @var \Drupal\easyjob_api\Service\EasyjobApiService
   */
  protected $easyjob = NULL;

  /**
   * Undocumented function.
   *
   * @param EasyjobApiService $easyjob
   */
  public function __construct(EasyjobApiService $easyjob) {
    $this->easyjob = $easyjob;
  }

  /**
   * Undocumented function.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return void
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('easyjob_api.client')
    );
  }

  /**
   *
   */
  public function getWebsettings() {
   $settings = $this->easyjob->getWebSettings();
    return new JsonResponse(
        [
          'status' => 'OK',
          'settings' => $settings,
        ]
    );
  }

  /**
   *
   */
  public function getProductsEditedSince() {
    $timestamp = \Drupal::request()->query->get('timestamp');
    $date = date('Y-m-d\TH:i:s', $timestamp);
    $products_ids = $this->easyjob->getProductsEditedSince($date);
    $products = $this->easyjob->getProductsDetail($products_ids);
    return new JsonResponse(
         [
           'status' => 'OK',
           'products' => $products,
         ]
     );
  }

  /**
   *
   */
  public function getProductAvailability() {
    $product_id = \Drupal::request()->query->get('product_id');
    $start = \Drupal::request()->query->get('start');
    $end = \Drupal::request()->query->get('end');
    $stock = $this->easyjob->getProductAvailabilityForPeriod($products_id, $start, $end);
    return new JsonResponse(
         [
           'status' => 'OK',
           'products' => $stock,
         ]
     );
  }

}