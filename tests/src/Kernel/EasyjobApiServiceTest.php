<?php

namespace Drupal\Tests\easyjob_api\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Test description.
 *
 * @group easyjob_api
 */
class EasyjobApiServiceTest extends KernelTestBase implements ServiceModifierInterface {

  private const TOKEN_RESPONSE = '{
    "access_token": "dummy-token-_AQAAANCMnd8BFdERjHoAwE_dummy-test-token-a8-8HJyBISw",
    "token_type": "bearer",
    "expires_in": 172799
  }';

  private const SECOND_RESPONSE = '[[ID: 10309';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['easyjob_api'];

  /**
   * Easyjob API Service.
   *
   * @var Drupal\Tests\easyjob_api\Kernel\TestEasyjobApiService
   */
  protected $easyjobApiService;

  /**
   * The mock Handler.
   *
   * @var GuzzleHttp\Handler\MockHandler
   */
  protected $mockHandler;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_definition = $container->getDefinition('easyjob_api.client');
    $service_definition->setClass(TestEasyjobApiService::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config = $this->config('easyjob_api.settings');
    $config->set('username', 'testuser');
    $config->set('password', 'testpass');
    $config->save();

    $this->mockHandler = new MockHandler([
      new Response(200, ['X-Foo' => 'Bar'], self::TOKEN_RESPONSE),
    ]);
    $handler_stack = HandlerStack::create($this->mockHandler);
    $client = new Client(['handler' => $handler_stack]);
    $this->easyjobApiService = $this->container->get('easyjob_api.client');
    $this->easyjobApiService->setClient($client);
    $this->easyjobApiService->getToken();
  }

  /**
   * Test on 404 Not Found response.
   */
  public function testGetWebsettingsOn404NotFound() {
    $this->mockHandler->reset();
    $this->mockHandler->append(new Response(404));
    $response = $this->easyjobApiService->getWebSettings();
    self::assertFalse($response);
  }

}
