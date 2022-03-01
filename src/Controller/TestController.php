<?php

namespace Drupal\easyjob_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Test Controller to Test Easyjob API 
 */
class TestController extends ControllerBase {

  private $easyjob;
  
  public function __construct(EasyjobApiService $easyjob)
  {
    $this->easyjob = $easyjob;
  }

  /**
   * 
   */
  public function getWebsettings() {
    $settings = $this->easyjob->getWebsettings();
    return new JsonResponse(
        [
          'status' => 'OK',
          'settings' => $settings,
        ]
      );
  }

}