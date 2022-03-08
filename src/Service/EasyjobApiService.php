<?php

namespace Drupal\easyjob_api\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\easyjob_api\Service\EasyjobApiServiceInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class EasyjobApiService
 *
 * @package Drupal\Easyjob_Api
 */
class EasyjobApiService implements EasyjobApiServiceInterface {

  const TOKEN_ENDPOINT = '/token';

  const WEBSETTINGS_ENDPOINT = '/api.json/Common/GetWebSettings/';

  const EDITED_SINCE_ENDPOINT = '/api.json/custom/itemlist/?editedsince=';

  const AVAILABILITY_ENDPOINT = '/api.json/Items/Avail/';

  const SINGLE_PRODUCT_ENDPOINT = '/api.json/custom/itemdetails/';

  const CREATE_PROJECT_ENDPOINT = '/api.json/custom/CreateProject/';

  const PARENT_CATEGORY_PARAM = 'IdCategoryParent';

  const CATEGORY_PARAM = 'IdCategory';

  const STARTDATE_PARAM = 'startdate';

  const FINISHDATE_PARAM = 'enddate';

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
   * @var \Drupal\Core\Http\ClientFactory
   *   The HTTP Client Factory.
   */
  protected $http_client_factory;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * A Guzzle HTTP Client.
   *
   * @var \Guzzle\Client
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
   * @param ClientFactory $client_factory
   *   The http client factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, RequestStack $request_stack, ClientFactory $http_client_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->config = $config_factory->get('easyjob_api.settings');
    $this->http_client_factory = $http_client_factory;
    $this->httpClient = $http_client_factory->fromOptions([
      'base_uri' => $this->config->get('base_url'),
    ]);
    if (empty($this->config->get('username')) || empty($this->config->get('password'))) {
      throw new \Exception("missing username or password for Easyjob API.");
    }
    $this->auth = [
      'username' => $this->config->get('username'),
      'password' => $this->config->get('password'),
    ];
    
  }

  /**
   * Get Authentication Token if it exists or create it.
   */
  public function getToken() {
    return ($this->token) ? $this->token : $this->generateToken();
  }

  /**
   * Retrieve Authentication Token and hydrate
   * all further request headers.
   */
  public function generateToken() {
    if (empty($this->auth)) {
      throw new \Exception("Easyjob API not authorized.");
    }
    $args = [
      'headers' => [
        'Content-type' => 'Content-Type: application/x-www-form-urlencoded',
      ],
      'form_params' => [
        'grant_type' => 'password',
        'username' => $this->getCredentials()['username'],
        'password' => $this->getCredentials()['password'],
      ],
    ];
    $response = $this->sendRequest('POST', self::TOKEN_ENDPOINT, $args);

    if ($response->getStatusCode() == '200' ) {
      //get token, hydrate service
      $msg = 'token retrieved from easyjob, connecting...';
      \Drupal::logger('easyjob_api')->notice($msg);
      $data = json_decode($response->getBody(), TRUE);
      
      $this->token = $data['access_token'];
      $this->httpClient = $this->http_client_factory->fromOptions([
        'base_uri' => $this->config->get('base_url'),
        'headers' => [
          'Authorization' => 'Bearer ' . $this->token,
          'Accept'        => 'application/json',
        ],
      ]);

      return $data['access_token'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getWebSettings() {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }
    $response = $this->sendRequest('GET', self::WEBSETTINGS_ENDPOINT);

    if ($response->getStatusCode() == '200' ) {
      $msg = 'Successfully loaded user settings';
      \Drupal::logger('easyjob_api')->notice($msg);
      $data = json_decode($response->getBody(), TRUE);
      return $data;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductsEditedSince($date = NULL) {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }

    $response = $this->sendRequest('GET', self::EDITED_SINCE_ENDPOINT . $date);

    if ($response->getStatusCode() == '200' ) {
      $msg = 'Succesfully retrieved products ids';
      \Drupal::logger('easyjob_api')->notice($msg);
      $data = json_decode($response->getBody(), TRUE);
      return $data;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductsDetail($product_ids) {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }

    $products = [];

    foreach ($product_ids as $key => $row) {
      $response = $this->sendRequest('GET', self::SINGLE_PRODUCT_ENDPOINT . $row['ID']);
      if ($response->getStatusCode() == '200') {
        $data = json_decode($response->getBody(), TRUE);
        $products[] = $data;
      }
    }

    return $products;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductAvailabilityForPeriod($product_id, $start, $end) {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }
    if (empty($start) || empty($end)) {
      throw new \Exception("Start and end dates are mandatory.");
    }
    $response = $this->sendRequest('GET', 
      self::AVAILABILITY_ENDPOINT . $product_id . '?' . 
      self::STARTDATE_PARAM . '=' . $start . '&' . 
      self::FINISHDATE_PARAM . '=' . $end
    );
    if ($response->getStatusCode() == '200') {
      $data = json_decode($response->getBody(), TRUE);
      return $data;
    }
    
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createProject($data) {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }
    if (empty($data)) {
      throw new \Exception("No project data.");
    }
    $response = $this->sendRequest('POST', 
      self::CREATE_PROJECT_ENDPOINT,
      $data
    );
    if ($response->getStatusCode() == '200') {
      $data = json_decode($response->getBody(), TRUE);
      return $data;
    }
    
    return FALSE;
  }

  /**
   * Helper function to encapsulate send request and catch error
   * @param string $method GET|POST
   * @param string $url
   * @param array $args custom headers, form_params, data
   */
  protected function sendRequest($method, $url, $args = []){
    try {
      $response = $this->httpClient->request($method, $url, $args);
      return $response;
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
  protected function getCredentials() {
    return $this->auth;
  }

}
