<?php

namespace Drupal\easyjob_api\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\easyjob_api\Service\EasyjobApiServiceInterface;
use Drupal\http_client_manager\HttpClientManagerFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class EasyjobApiService
 *
 * @package Drupal\Easyjob_Api
 */
class EasyjobApiService implements EasyjobApiServiceInterface {

  const TOKEN_ENDPOINT = '/token';

  const WEBSETTINGS_ENDPOINT = '/api.json/Common/GetWebSettings/';

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
   * An Easyjob Services - Contents HTTP Client.
   *
   * @var \Drupal\http_client_manager\HttpClientInterface
   */
  protected $httpClient;

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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, RequestStack $request_stack, HttpClientManagerFactoryInterface $http_client_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->config = $config_factory->get('easyjob_api.settings');
    $this->httpClient = $http_client_factory->fromOptions([
      'base_uri' => $this->config->get('base_url'),
    ]);
    if (empty($this->config->get('user')) || empty($this->config->get('password'))) {
      throw new \Exception("missing username or password for Easyjob API.");
    }
    $this->auth = [
      'user' => $this->config->get('user'),
      'password' => $this->config->get('password'),
    ];

    $this->token =  $this->generateToken();
    $this->httpClient = $http_client_factory->fromOptions([
      'base_uri' => $this->config->get('base_url'),
      'headers' => [
        'Authorization' => 'Bearer ' . $this->token,
        'Accept'        => 'application/json',
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCredentials() {
    return $this->auth;
  }


  public function generateToken() {
    if (empty($this->auth)) {
      throw new \Exception("Easyjob API not authorized.");
    }
    try {
      $response = \Drupal::httpClient()->post(self::TOKEN_ENDPOINT, [
        'headers' => [
          'Content-type' => 'Content-Type: application/x-www-form-urlencoded',
        ],
        'form_params' => [
          'grant_type' => 'password',
          'username' => $this->getCredentials()['user'],
          'password' => $this->getCredentials()['password'],
        ],
      ]);

      if ($response->getStatusCode() == '200' ) {
        //get token, hydrate service
        $msg = 'token retrieved from easyjob, connecting...';
        \Drupal::logger('easyjob_api')->notice($msg);
        $data = json_decode($response->getBody(), true);
        return $data['access_token'];
      }
    } catch (GuzzleException $error) {
      // Get the original response
      $response = $error->getResponse();
      // Get the info returned from the remote server.
      $response_info = $response->getBody()->getContents();
      // Using FormattableMarkup allows for the use of <pre/> tags, giving a more readable log item.
      $message = new FormattableMarkup('API connection error. Error details are as follows:<pre>@response</pre>', ['@response' => print_r(json_decode($response_info), TRUE)]);
      // Log the error
      watchdog_exception('Remote API Connection', $error, $message);
    }
    // A non-Guzzle error occurred. The type of exception is unknown, so a generic log item is created.
    catch (\Exception $error) {
      // Log the error.
      watchdog_exception('Remote API Connection', $error, t('An unknown error occurred while trying to connect to the remote API. This is not a Guzzle error, nor an error in the remote API, rather a generic local error ocurred. The reported error was @error', ['@error' => $error->getMessage()]));
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getWebSettings() {
    if (empty($this->token)) {
      throw new \Exception("Easyjob API not authorized.");
    }

    $response = \Drupal::httpClient()->get(self::WEBSETTINGS_ENDPOINT);

    if ($response->getStatusCode() == '200' ) {
      $msg = 'Successfully loaded user settings';
      \Drupal::logger('easyjob_api')->notice($msg);
      $data = json_decode($response->getBody(), true);
      return $data;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllProductsMatchingParameters($params = []) {
    if (empty($this->token)) {
      throw new \Exception("Easyjob API not authorized.");
    }

    $request_url = $this->getRequestUrl('getProducts', $params);
    // Send the http request to the easyjob endpoint.
    $response = \Drupal::httpClient()->get(self::PRODUCTS_ENDPOINT, [
      'headers' => $this->headers,
    ]);

    if ($response->getStatusCode() == '200' ) {
      $msg = 'Succesfully retrieved products';
      \Drupal::logger('easyjob_api')->notice($msg);
      $data = json_decode($response->getBody(), true);
      $result = json_decode($data, true);
      return $result;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductDetail($product_id) {
  }

  /**
   * {@inheritdoc}
   */
  public function getProductAvailabilityForPeriod($product_id, $start, $end) {
  }

  /**
   * {@inheritdoc}
   */
  public function createCustomer($data) {
  }

  /**
   * {@inheritdoc}
   */
  public function createOrder($data) {
  }
}
