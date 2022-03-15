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
      ],
    );
  }

  /**
   *
   */
  public function createProject() {
    $order = \Drupal\commerce_order\Entity\Order::load(12);
    $customer = $order->getCustomer();
    $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
    $customer_profile = $profile_storage->loadByUser($customer, 'customer');
    $address = $customer_profile->get('address')->getValue()[0];
    $service = ($order->get('field_te_shipping_method')->target_id == 1) ? "2" : "0";
    $payment_method = ($order->getData('offer')) ? NULL : 'Kreditkarte';
    $state = ($order->getData('offer')) ? "0" : "1";
    $order_data = [
      'ID' => $order->getOrderNumber(),
      'ProjectName' => $order->field_te_event_name->value,
      'StartDate' => $order->get('field_te_rent_dates')->getValue()[0]['value'],
      'EndDate' => $order->get('field_te_rent_dates')->getValue()[0]['end_value'],
      'CustomerComment' => $order->field_iq_commerce_comment->value,
      'PaymentAmount' => floatval(number_format($order->getTotalPrice()->getNumber(), 2)),
      'PaymentMethod' => $payment_method,
      'ProjectState' => $state,
      'Service' => $service,
    ];

    $customer_address = [
      'Company' => $address['organization'],
      'Name2' => '',
      'Street' => $address['address_line1'],
      'Street2' => $address['address_line2'],
      'Zip' => $address['postal_code'],
      'City' => $address['locality'],
      'Fax' => '',
      'Phone' => $customer->field_te_telefonnummer->value,
      'EMail' => $customer->mail->value,
      'WWWAdress' => '',
      'Country' => [
        'Caption' => $address['country_code'],
      ],
      'PrimaryContact' => [
        'FirstName' => $address['given_name'],
        'Surname' => $address['family_name'],
      ],
    ];
    $order_data['CustomerAddress'] = $customer_address;
    $order_data['DeliveryAddress'] = $customer_address;
    $order_data['InvoiceAddress'] = $customer_address;

    $order_items = [];
    foreach ($order->getItems() as $key => $item) {
      $order_items[] = [
        'ID' => $item->getPurchasedEntity()->field_te_main_number_easyjob->value,
        'Quantity' => intval($item->getQuantity()),
        'Price' => floatval(number_format($item->getUnitPrice()->getNumber(), 1)),
      ];
    }
    $order_data['Items'] = $order_items;

    //$data = '{"ID": "100000","ProjectName": "TEST - Projektname","StartDate": "2023-10-09T00:00:00","EndDate": "2023-10-11T00:00:00","CustomerComment": "TEST BESTELLUNG","PaymentAmount": 123.45,"PaymentMethod": "Kreditkarte","ProjectState": "0","Service": "0","CustomerAddress": {"Company": "TEST Firmenname","Name2": "TEST Firma Zusatz","Street": "TEST Straße","Street2": "TEST Adresszusatz","Zip": "TEST Postleitzahl","City": "TEST Ort","Fax": "TEST Faxnummer","Phone": "TEST Telefonnummer","EMail": "TEST E-Mail","WWWAdress": "TEST Webseite","Country": {"Caption": "TEST Land"},"PrimaryContact": {"FirstName": "TEST Vorname","Surname": "TEST Nachname"}},"DeliveryAddress": {"Company": "TEST Firmenname","Name2": "TEST Firma Zusatz","Street": "TEST Straße","Street2": "TEST Adresszusatz","Zip": "TEST Postleitzahl","City": "TEST Ort","Fax": "TEST Faxnummer","Phone": "TEST Telefonnummer","EMail": "TEST E-Mail","WWWAdress": "TEST Webseite","Country": {"Caption": "TEST Land"},"PrimaryContact": {"FirstName": "TEST Vorname","Surname": "TEST Nachname"}},"InvoiceAddress": {"Company": "TEST Firmenname","Name2": "TEST Firma Zusatz","Street": "TEST Straße","Street2": "TEST Adresszusatz","Zip": "TEST Postleitzahl","City": "TEST Ort","Fax": "TEST Faxnummer","Phone": "TEST Telefonnummer","EMail": "TEST E-Mail","WWWAdress": "TEST Webseite","Country": {"Caption": "TEST Land"},"PrimaryContact": {"FirstName": "TEST Vorname","Surname": "TEST Nachname"}},"Items": [{"ID": "23940","Quantity": 5,"Price": 23.5},{"ID": "50000","Quantity": 5,"Price": 0}]}';
    //$array = json_decode($data, TRUE);
    //$project = $this->easyjob->createProject($array);

    $project_ids = $this->easyjob->createProject($order_data);
    return new JsonResponse(
      [
        'status' => 'OK',
        'results' => $project_ids,
      ],
    );
  }


  public function checkCategories() {
    $array = $this->easyjob->getShortListProducts();
    $categories = [];
    foreach($array as $key => $product) {
      $categories[$product['Mutterwarengruppe']][] = $product['Warengruppe'];
    }
    print_r($categories);
  }

}