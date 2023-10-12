<?php

namespace Drupal\Tests\easyjob_api\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Test description.
 *
 * @group easyjob_api
 */
class EasyjobApiServiceTest extends KernelTestBase implements ServiceModifierInterface {

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

    $mock = new MockHandler([
      new Response(200, ['X-Foo' => 'Bar'], 'Hello, World'),
      new Response(202, ['Content-Length' => 0]),
      new RequestException('Error Communicating with Server', new Request('GET', 'test'))
    ]);
    $handler_stack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handler_stack]);
    $this->easyjobApiService = $this->container->get('easyjob_api.client');
    $this->easyjobApiService->setClient($client);
  }

  /**
   * Test something.
   */
  public function testGetWebsettings() {
    // Mock and assign a new client for each test.
    self::assertTrue(TRUE);
  }

}
