<?php

namespace Drupal\easyjob_api\Service;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * An interface with Easyjob allowing GET and POST operations.
 *
 * @package Drupal\Easyjob_Api
 */
class EasyjobApiService implements EasyjobApiServiceInterface {

  use StringTranslationTrait;

  final public const TOKEN_ENDPOINT = '/token';

  final public const WEBSETTINGS_ENDPOINT = '/api.json/Common/GetWebSettings/';

  final public const SHORTLIST_PRODUCT_ENDPOINT = '/api.json/Items/List/';

  final public const EDITED_SINCE_ENDPOINT = '/api.json/custom/itemlist/?editedsince=';

  final public const AVAILABILITY_ENDPOINT = '/api.json/Custom/CalculateItem/';

  final public const SINGLE_PRODUCT_ENDPOINT = '/api.json/custom/itemdetails/';

  final public const SINGLE_FILE_ENDPOINT = '/shortcuts/download/';

  final public const CREATE_PROJECT_ENDPOINT = '/api.json/custom/CreateProject/';

  final public const GET_PROJECT_ENDPOINT = '/api.json/Projects/Details/';

  final public const GET_JOB_ENDPOINT = '/api.json/Jobs/Details/';

  final public const PARENT_CATEGORY_PARAM = 'IdCategoryParent';

  final public const CATEGORY_PARAM = 'IdCategory';

  final public const STARTDATE_PARAM = 'startdate';

  final public const FINISHDATE_PARAM = 'enddate';

  final public const QUANTITY_PARAM = 'quantity';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The immutable entity clone settings configuration entity.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The HTTP Client Factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * The Logger channel Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerChannelFactory;

  /**
   * The symfony request.
   *
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
   * An array with credentials.
   *
   * @var array
   */
  protected $auth;

  /**
   * The API Token.
   *
   * @var string
   */
  protected $token;

  /**
   * Bw2ApiService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The current request stack.
   * @param \Drupal\Core\Http\ClientFactory $httpClientFactory
   *   The http client factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerChannelFactory
   *   The logger channel factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
    ClientFactory $httpClientFactory,
    LoggerChannelFactory $loggerChannelFactory
    ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->config = $config_factory->get('easyjob_api.settings');
    $this->httpClientFactory = $httpClientFactory;
    $this->loggerChannelFactory = $loggerChannelFactory;
    $this->httpClient = $httpClientFactory->fromOptions([
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
    return $this->token ?: $this->generateToken();
  }

  /**
   * Retrieve Authentication Token and hydrate all further request headers.
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

    if ($response && $response->getStatusCode() == '200') {
      // Get token, hydrate service.
      $msg = 'token retrieved from easyjob, connecting...';
      $this->loggerChannelFactory->get('easyjob_api')->notice($msg);
      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);

      $this->token = $data['access_token'];
      $this->httpClient = $this->httpClientFactory->fromOptions([
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

    if ($response && $response->getStatusCode() == '200') {
      $msg = 'Successfully loaded user settings';
      $this->loggerChannelFactory->get('easyjob_api')->notice($msg);
      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
      return $data;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getShortListProducts() {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }

    $response = $this->sendRequest('GET', self::SHORTLIST_PRODUCT_ENDPOINT);

    if ($response && $response->getStatusCode() == '200') {
      $msg = 'Succesfully retrieved products ids';
      $this->loggerChannelFactory->get('easyjob_api')->notice($msg);
      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
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

    if ($response && $response->getStatusCode() == '200') {
      $msg = 'Succesfully retrieved products ids';
      $this->loggerChannelFactory->get('easyjob_api')->notice($msg);
      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
      return $data;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSingleProductDetail($product_id) {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }
    $response = $this->sendRequest('GET', self::SINGLE_PRODUCT_ENDPOINT . $product_id);
    if ($response && $response->getStatusCode() == '200') {
      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
      return $data;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSingleFileDetail($file_id) {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }
    $response = $this->sendRequest('GET', self::SINGLE_FILE_ENDPOINT . $file_id);
    if ($response && $response->getStatusCode() == '200') {
      $content_length = (int) $response->getHeader('Content-Length')[0];
      $stream = $response->getBody();
      $file_data = $stream->read($content_length);
      $data = [
        'filename' => str_replace('attachment;filename=', '', (string) $response->getHeader('content-disposition')[0]),
        'content' => $file_data,
      ];
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

    foreach ($product_ids as $row) {
      $response = $this->sendRequest('GET', self::SINGLE_PRODUCT_ENDPOINT . $row['ID']);
      if ($response && $response->getStatusCode() == '200') {
        $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
        $products[] = $data;
      }
    }

    return $products;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductAvailabilityForPeriod($args) {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }
    if (empty($args['product_id'])) {
      throw new \Exception("Product ID is required.");
    }
    if (empty($args['start']) || empty($args['end'])) {
      throw new \Exception("Start and end dates are mandatory.");
    }
    if (empty($args['quantity'])) {
      throw new \Exception("Please provide a quantity.");
    }
    $response = $this->sendRequest('POST',
      self::AVAILABILITY_ENDPOINT . $args['product_id'] . '?' .
      self::STARTDATE_PARAM . '=' . $args['start'] . '&' .
      self::FINISHDATE_PARAM . '=' . $args['end'] . '&' .
      self::QUANTITY_PARAM . '=' . $args['quantity']
    );
    if ($response && $response->getStatusCode() == '200') {
      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
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
    $args = [
      'json' => $data,
    ];
    $response = $this->sendRequest('POST',
      self::CREATE_PROJECT_ENDPOINT,
      $args,
    );
    if ($response && $response->getStatusCode() == '200') {
      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
      return $data;
    }
    else {
      throw new \Exception("An error occured.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getProject($id) {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }
    if (empty($id)) {
      throw new \Exception("No project id.");
    }
    $response = $this->sendRequest('GET',
      self::GET_PROJECT_ENDPOINT . $id,
    );
    if ($response && $response->getStatusCode() == '200') {
      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
      return $data;
    }
    else {
      throw new \Exception("An error occured.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getJob($id) {
    if (empty($this->getToken())) {
      throw new \Exception("Easyjob API not authorized.");
    }
    if (empty($id)) {
      throw new \Exception("No job id.");
    }
    $response = $this->sendRequest('GET',
      self::GET_JOB_ENDPOINT . $id
    );
    if ($response && $response->getStatusCode() == '200') {
      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
      return $data;
    }
    else {
      throw new \Exception("An error occured.");
    }
  }

  /**
   * Helper function to encapsulate send request and catch error.
   *
   * @param string $method
   *   GET|POST.
   * @param string $url
   *   The API endpoint.
   * @param array $args
   *   Custom headers, form_params, data.
   */
  protected function sendRequest($method, $url, array $args = []) {
    try {
      $response = $this->httpClient->request($method, $url, $args);
      return $response;
    }
    catch (GuzzleException $error) {
      /*
       * Using FormattableMarkup allows for the use of <pre/> tags,
       * giving a more readable log item.
       */
      $message = new FormattableMarkup(
        'API connection error. Error details are as follows:<pre>@response</pre>',
        ['@response' => $error->getMessage()]
          );
      // Log the error.
      $this->loggerChannelFactory->get('easyjob_api')->error('Remote API Connection', [], $message);
    }
    /*
     * A non-Guzzle error occurred. T
     * The type of exception is unknown, so a generic log item is created.
     */
    catch (\Exception $error) {
      // Log the error.
      $this->loggerChannelFactory->get('easyjob_api')->error('Remote API Connection', [], $this->t('An unknown error occurred while trying to connect to the remote API. This is not a Guzzle error, nor an error in the remote API, rather a generic local error ocurred. The reported error was @error', ['@error' => $error->getMessage()]));
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
