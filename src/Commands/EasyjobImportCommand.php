<?php

namespace Drupal\easyjob_api\Commands;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\easyjob_api\Service\EasyjobProductImportService;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class EasyjobImportCommand extends DrushCommands {

  /**
   * Constructs a new EasyjobImportCommand object.
   *
   * @param \Drupal\easyjob_api\Service\EasyjobProductImportService $importer
   *   Importer service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger service.
   */
  public function __construct(EasyjobProductImportService $importer, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->importer = $importer;
    $this->loggerChannelFactory = $loggerChannelFactory;
  }

  /**
   * Import all products.
   *
   * @command easyjob:import-all
   * @aliases easyjob-import-all
   *
   * @usage easyjob:import-all
   */
  public function importAll() {
    $operations = [];
    $operations = $this->importer->getOperations();
    $this->importer->doImport($operations);
    drush_backend_batch_process();
    return 0;
  }

}