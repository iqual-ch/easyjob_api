<?php

namespace Drupal\easyjob_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\easyjob_api\Service\EasyjobApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Test Controller to Test Easyjob API.
 */
class TestController extends ControllerBase {

  /**
   * Easyjob Service.
   *
   * @var \Drupal\easyjob_api\Service\EasyjobApiService
   */
  protected $easyjob = NULL;

  /**
   * Request Stack Service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack = NULL;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager = NULL;

  /**
   * Construct a new TestController object.
   *
   * @param \Drupal\easyjob_api\Service\EasyjobApiService $easyjob
   *   The Easyjob service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Symfony Request Stack.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The Entity Manager Service.
   */
  public function __construct(EasyjobApiService $easyjob, RequestStack $requestStack, EntityTypeManager $entityTypeManager) {
    $this->easyjob = $easyjob;
    $this->requestStack = $requestStack;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Instanciate a TestController object with dependencies.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Dependency Injection Container.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('easyjob_api.client'),
      $container->get('request_stack'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get the Websettings from Eaysjob Tool.
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
   * Get all products edited since provided timestamp.
   */
  public function getProductsEditedSince() {
    $timestamp = $this->requestStack->getCurrentRequest()->query->get('timestamp');
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
   * Get the availability for a given product on a given period of time.
   */
  public function getProductAvailability() {
    $product_id = $this->requestStack->getCurrentRequest()->query->get('product_id');
    $start = $this->requestStack->getCurrentRequest()->query->get('start');
    $end = $this->requestStack->getCurrentRequest()->query->get('end');
    $quantity = $this->requestStack->getCurrentRequest()->query->get('quantity');
    $stock = $this->easyjob->getProductAvailabilityForPeriod(
      [
        'product_id' => $product_id,
        'start' => $start,
        'end' => $end,
        'quantity' => $quantity,
      ]
    );
    return new JsonResponse(
      [
        'status' => 'OK',
        'stock' => $stock,
      ],
    );
  }

  /**
   * Create an order (project) in the Easyjob Tool.
   */
  public function createProject() {
    $startDate = date('Y-m-d\TH:i:s', strtotime('+1 year'));
    $endDate = date('Y-m-d\TH:i:s', strtotime('+1 year +1 day'));
    $order_data = [
      'ID' => '100000',
      'ProjectName' => 'TEST - Projektname',
      'StartDate' => $startDate,
      'EndDate' => $endDate,
      'CustomerComment' => 'TEST BESTELLUNG',
      'PaymentAmount' => 123.45,
      'PaymentMethod' => 'Kreditkarte',
      'ProjectState' => '0',
      'Service' => '0',
    ];

    $customer_address = [
      'Company' => 'TEST Firmenname',
      'Name2' => 'TEST Firma Zusatz',
      'Street' => 'TEST Straße',
      'Street2' => 'TEST Adresse Zusatz',
      'Zip' => 'TEST Zip',
      'City' => 'TEST City',
      'Fax' => '',
      'Phone' => 'TEST Telefon',
      'EMail' => 'TEST E-Mail',
      'WWWAdress' => '',
      'Country' => [
        'Caption' => 'ch',
      ],
      'PrimaryContact' => [
        'FirstName' => 'TEST Vorame',
        'Surname' => 'TEST Name',
      ],
    ];
    $order_data['CustomerAddress'] = $customer_address;
    $order_data['DeliveryAddress'] = $customer_address;
    $order_data['InvoiceAddress'] = $customer_address;
    $order_data['Items'] = [
      [
        'ID' => '23940',
        'Quantity' => 5,
        'Price' => 23.5,
      ],
      [
        'ID' => '50000',
        'Quantity' => 5,
        'Price' => 0,
      ],
    ];

    $project_ids = $this->easyjob->createProject($order_data);
    return new JsonResponse(
      [
        'status' => 'OK',
        'results' => $project_ids,
      ],
    );
  }

  /**
   * Retrieve product categories.
   */
  public function checkCategories() {
    $array = $this->easyjob->getShortListProducts();
    $categories = [];
    foreach ($array as $product) {
      if (!isset($categories[$product['CategoryParent']])) {
        $categories[$product['CategoryParent']] = [];
      }
      if (!in_array($product['Category'], $categories[$product['CategoryParent']])) {
        $categories[$product['CategoryParent']][] = $product['Category'];
      }
    }
    return new JsonResponse(
      [
        'status' => 'OK',
        'categories' => $categories,
      ],
    );
  }

}
