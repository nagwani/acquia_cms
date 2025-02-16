<?php

namespace Drupal\acquia_cms_support\Controller;

use Drupal\acquia_cms_common\Services\AcmsUtilityService;
use Drupal\acquia_cms_support\Service\AcquiaCmsConfigSyncService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Return page with list of overridden configuration related to acquia cms.
 */
class AcquiaCmsConfigSyncOverridden extends ControllerBase implements ContainerInjectionInterface {

  /**
   * AcquiaCmsConfigSyncService.
   *
   * @var \Drupal\acquia_cms_support\Service\AcquiaCmsConfigSyncService
   */
  protected $acmsConfigSync;

  /**
   * The acquia cms utility service.
   *
   * @var \Drupal\acquia_cms_common\Services\AcmsUtilityService
   */
  protected $acmsUtilityService;

  /**
   * AcquiaCmsConfigSyncOverridden constructor.
   *
   * @param \Drupal\acquia_cms_support\Service\AcquiaCmsConfigSyncService $acms_config_sync
   *   The acquia cms config sync.
   * @param \Drupal\acquia_cms_common\Services\AcmsUtilityService $acmsUtilityService
   *   The acquia cms utility service.
   */
  public function __construct(AcquiaCmsConfigSyncService $acms_config_sync, AcmsUtilityService $acmsUtilityService) {
    $this->acmsConfigSync = $acms_config_sync;
    $this->acmsUtilityService = $acmsUtilityService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_cms_support.config_service'),
      $container->get('acquia_cms_common.utility')
    );
  }

  /**
   * Returns a renderable array for a configuration page.
   *
   * @throws \Drupal\Core\Config\StorageTransformerException
   */
  public function build() {
    $header = [
      $this->t('Name'),
      $this->t('Module'),
      $this->t('Default Parity'),
      $this->t('Operations'),
    ];
    $acquiaCmsModules = $this->acmsUtilityService->getAcquiaCmsModuleList();
    $overriddenConfig = [];
    foreach ($acquiaCmsModules as $module) {
      $path = $module->getPath();
      $multipleStorage = [
        'install' => $this->acmsConfigSync->getInstallStorage($path),
        'optional' => $this->acmsConfigSync->getOptionalStorage($path),
      ];

      foreach ($multipleStorage as $storageType => $storage) {
        $configChangeList = $this->acmsConfigSync->getOverriddenConfig($storage);
        if (empty($configChangeList)) {
          continue;
        }

        foreach ($configChangeList as $config) {
          $parity = $config['parity'];
          $configName = $config['name'];
          $overriddenConfig[] = [
            'name' => $configName,
            'module' => $module->getName(),
            'config' => [
              'class' => $this->getParityClass($parity),
              'data' => ['#markup' => "<span>$parity  %</span>"],
            ],
            'operations' => [
              'data' => [
                '#type' => 'operations',
                '#links' => $this->getViewDifference($module->getName(), $module->getType(), $storageType, $configName),
              ],
            ],
          ];
        }
      }
    }

    asort($overriddenConfig);

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $overriddenConfig,
      '#attached' => [
        'library' => ['acquia_cms_support/diff-modal'],
      ],
    ];
  }

  /**
   * Get classname for colour code based on parity value.
   *
   * @param int $parity
   *   Parity value.
   *
   * @return string
   *   Class.
   */
  private function getParityClass($parity) {
    $className = '';
    if ($parity <= 30) {
      $className = 'color-parity-30';
    }
    elseif ($parity > 30 && $parity <= 75) {
      $className = 'color-parity-75';
    }
    else {
      $className = 'color-parity-above-75';
    }
    return $className;
  }

  /**
   * Get the view difference link for each configuration.
   *
   * @param string $name
   *   The configuration name.
   * @param string $type
   *   The entity type.
   * @param string $storage
   *   The storage.
   * @param string $config_file
   *   The configuration file.
   *
   * @return array
   *   The diff link in modal.
   */
  public function getViewDifference($name, $type, $storage, $config_file) {
    $links = [];
    $route_name = 'acquia_cms_support.config_diff';
    $route_options = [
      'name' => $name,
      'type' => $type,
      'storage' => $storage,
      'source_name' => $config_file,
    ];
    $links['view_diff'] = [
      'title' => $this->t('View differences'),
      'url' => Url::fromRoute($route_name, $route_options),
      'attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => json_encode([
          'width' => 700,
        ]),
      ],
    ];
    return $links;
  }

}
