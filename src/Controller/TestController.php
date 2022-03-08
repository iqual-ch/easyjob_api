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
    $stock = $this->easyjob->getProductAvailabilityForPeriod($product_id, $start, $end);
    return new JsonResponse(
         [
           'status' => 'OK',
           'stock' => $stock,
         ]
     );
  }

  /**
   *
   */
  public function createProject() {
    $data = '{"ID": "100000","ProjectName": "TEST - Projektname","StartDate": "2023-10-09T00:00:00","EndDate": "2023-10-11T00:00:00","CustomerComment": "TEST BESTELLUNG","PaymentAmount": 123.45,"PaymentMethod": "Kreditkarte","ProjectState": "0","Service": "0","CustomerAddress": {"Company": "TEST Firmenname","Name2": "TEST Firma Zusatz","Street": "TEST Straße","Street2": "TEST Adresszusatz","Zip": "TEST Postleitzahl","City": "TEST Ort","Fax": "TEST Faxnummer","Phone": "TEST Telefonnummer","EMail": "TEST E-Mail","WWWAdress": "TEST Webseite","Country": {"Caption": "TEST Land"},"PrimaryContact": {"FirstName": "TEST Vorname","Surname": "TEST Nachname"}},"DeliveryAddress": {"Company": "TEST Firmenname","Name2": "TEST Firma Zusatz","Street": "TEST Straße","Street2": "TEST Adresszusatz","Zip": "TEST Postleitzahl","City": "TEST Ort","Fax": "TEST Faxnummer","Phone": "TEST Telefonnummer","EMail": "TEST E-Mail","WWWAdress": "TEST Webseite","Country": {"Caption": "TEST Land"},"PrimaryContact": {"FirstName": "TEST Vorname","Surname": "TEST Nachname"}},"InvoiceAddress": {"Company": "TEST Firmenname","Name2": "TEST Firma Zusatz","Street": "TEST Straße","Street2": "TEST Adresszusatz","Zip": "TEST Postleitzahl","City": "TEST Ort","Fax": "TEST Faxnummer","Phone": "TEST Telefonnummer","EMail": "TEST E-Mail","WWWAdress": "TEST Webseite","Country": {"Caption": "TEST Land"},"PrimaryContact": {"FirstName": "TEST Vorname","Surname": "TEST Nachname"}},"Items": [{"ID": "23940","Quantity": 5,"Price": 23.5},{"ID": "50000","Quantity": 5,"Price": 0}]}';
    $array = json_decode($data, TRUE);
    $project = $this->easyjob->createProject($array);
    return new JsonResponse(
      [
        'status' => 'OK',
        'project' => $project,
      ]
    );
  }

}