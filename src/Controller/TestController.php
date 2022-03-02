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

}