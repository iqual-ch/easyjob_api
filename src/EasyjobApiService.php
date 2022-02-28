<?php

namespace Drupal\easyjob_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class EasyjobApiService
 * @package Drupal\easyjob_api
 */
class EasyjobApiService implements EasyjobApiServiceInterface {

  const PRODUCTS_ENDPOINT = '/Items/List/';

  const AVAILABILITY_ENDPOINT = '/Items/Avail/';

  const SINGLE_PRODUCT_ENDPOINT = '/Items/Details/';

  const PARENT_CATEGORY_PARAM = 'IdCategoryParent';

  const CATEGORY_PARAM = 'IdCategory';

  const EDITED_SINCE_PARAM = 'editedsince';

  /**
   * @var EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   *   The immutable entity clone settings configuration entity.
   */
  protected $config;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * @var array
   *   Array with credentials.
   */
  protected $auth;

  /**
   * @var string
   *   API Token
   */
  protected $token;

  /**
   * bw2ApiService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param RequestStack $request_stack
   *   The current request stack.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->config = $config_factory->get('easyjob_api.settings');

    $this->auth = [
      'baseUrl' => $this->config->get('base_url'),
      'user' => $this->config->get('user'),
      'password' => $this->config->get('password'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCredentials() {
    return $this->auth;
  }

  /**
   * {@inheritdoc}
   */
  public function getToken() {
    return ($this->token) ? $this->token : $this->generateToken();
  }

  public function generateToken() {
    if (empty($this->auth)) {
      throw new \Exception("Easyjob API not authorized.");
    }
    $request_url = $this->getRequestUrl('getToken');
    $response = \Drupal::httpClient()->post($request_url, [
      'headers' => [
        'Content-type' => 'application/json',
        'requesttype' => 'string',
      ],
      'body' => [
        'grant_type' => 'password',
        'username' => $this->getCredentials()['user'],
        'password' => $this->getCredentials()['password'],
      ],
    ]);

    if ($response->getStatusCode() == '200' ) {
      //get token, hydrate service

      /* $msg = 'token retrieved from easyjob, connecting...';
      \Drupal::logger('easyjob_api')->notice($msg);
      $data = json_decode($response->getBody(), true);
      $result = json_decode($data, true);
      $this->token = $result->token;
      return $result; */
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllProductsMatchingParameters($params = []) {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }

    $request_url = $this->getRequestUrl('getProducts', $params);
    // Send the http request to the easyjob endpoint.
    $response = \Drupal::httpClient()->get($request_url, [
      'headers' => [
        'Content-type' => 'application/json',
        'requesttype' => 'string',
      ],
    ]);

    if ($response->getStatusCode() == '200' ) {
      $msg = '';
      \Drupal::logger('easyjob_api')->notice($msg);
      $data = json_decode($response->getBody(), true);
      $result = json_decode($data, true);
      return $result;
    }
    return FALSE;
  }

  /**
   * Helper function to construct the url request.
   */
  protected function getRequestUrl($requestOperation, $extra_param = false) {
    $url = $this->auth['baseUrl'];
    if ($requestOperation == 'getToken') {
      $url .= '/token/';
    }
    if ($requestOperation == 'getProducts') {
    }
    
    return $url;
  }

}
