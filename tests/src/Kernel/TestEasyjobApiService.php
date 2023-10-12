<?php

namespace Drupal\Tests\easyjob_api\Kernel;

use Drupal\easyjob_api\Service\EasyjobApiService;
use GuzzleHttp\Client;

/**
 * The test variant of the EasyjobApiService.
 *
 * Used to set the client to a mocked one.
 *
 * @package Drupal\Easyjob_Api
 */
class TestEasyjobApiService extends EasyjobApiService {

  /**
   * Set the httpClient.
   *
   * @param GuzzleHttp\Client $client
   *   The mocked httpClient.
   */
  public function setClient(Client $client) {
    $this->httpClient = $client;
  }

}
