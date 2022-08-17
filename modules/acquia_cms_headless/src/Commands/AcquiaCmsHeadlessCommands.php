<?php

namespace Drupal\acquia_cms_headless\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Drupal\acquia_cms_headless\Service\StarterkitNextjsService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drush\Commands\DrushCommands;

/**
 * Implements Acquia CMS Headless commands for Drush.
 */
class AcquiaCmsHeadlessCommands extends DrushCommands {

  /**
   * The next.js starter kit service.
   *
   * @var \Drupal\acquia_cms_headless\Service\StarterkitNextjsService
   */
  private $starterKit;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system interface.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs an AcquiaCmsHeadlessCommands object.
   *
   * @param \Drupal\acquia_cms_headless\Service\StarterkitNextjsService $starter_kit
   *   The next.js starter kit service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   Lets us create a directory.
   */
  public function __construct(StarterkitNextjsService $starter_kit, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    $this->starterKit = $starter_kit;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * Sets up a next.js app backend.
   *
   * @option site-url
   *   The base URl of the site.
   * @option site-name
   *   The site name for setting up.
   * @option env-file
   *   The file where the generated environment variables should be written.
   * @usage acms:headless:new-nextjs  --site-url='http://localhost:3000' --site-name='Headless site'
   *   Initializes a next.js app backend.
   *
   * @command acms:headless:new-nextjs
   */
  public function acmsHeadlessNewNextjs(array $options = [
    'site-url' => NULL,
    'site-name' => NULL,
    'env-file' => NULL,
  ]): void {
    $existing_sites = $this->entityTypeManager->getStorage('next_site')->loadMultiple();
    $site_id = $this->getSiteMachineName($options['site-name']);
    $data = [
      'site-name' => $options['site-name'],
      'site-url' => $options['site-url'],
    ];
    // Check if there's no Next.js site available.
    if (empty($existing_sites)) {
      $site = $this->starterKit->initStarterkitNextjs($site_id, $data);
    }
    // Let's create the one requested.
    else {
      // Create a new Next.js Consumer.
      $this->starterKit->createHeadlessConsumer($data);
      // Create a new Next.js Site.
      $site = $this->starterKit->createHeadlessSite($site_id, $data);
    }
    $env = $this->starterKit->getEnvironmentVariablesAsString($site);
    if ($file = $options['env-file']) {
      file_put_contents($file, $env);
      $this->logger()->success("Environment variables were written to $file.");
    }
    else {
      $this->logger()->notice("Use these environment variables for your Next.js application. Place them in your .env file:\n" . print_r($env, TRUE));
    }
  }

  /**
   * Hook validates for acms:headless:new-nextjs command.
   *
   * @hook validate acms:headless:new-nextjs
   */
  public function validateAcmsHeadlessNewNextJs(CommandData $commandData) {
    $options = $commandData->input()->getOptions();
    $messages = [];
    if (!isset($options['site-url'])) {
      $messages[] = dt("Missing required parameter site URL.");
    }
    if (!isset($options['site-name'])) {
      $messages[] = dt("Missing required parameter site name.");
    }
    if (isset($options['site-url']) && isset($options['site-name'])) {
      $site_machine_name = $this->getSiteMachineName($options['site-name']);
      if ($this->starterKit->getHeadlessSite($site_machine_name)) {
        $messages[] = dt("Site with name [@site] already exists!", ['@site' => $options['site-name']]);
      }
    }
    if (isset($options['env-file'])) {
      if (!file_exists($options['env-file'])) {
        if (!is_dir($options['env-file'])) {
          $dir = $this->fileSystem->dirname($options['env-file']);
          $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
        }
      }
    }
    if ($messages) {
      return new CommandError(implode('\n', $messages));
    }
  }

  /**
   * Get site machine name out of name.
   *
   * @param string $site_name
   *   The site-name.
   *
   * @return string
   *   The site machine name.
   */
  private function getSiteMachineName(string $site_name): string {
    $site_name_lower = strtolower($site_name);
    $site_machine_name = preg_replace('/[^a-z0-9_]+/', '_', $site_name_lower);
    return preg_replace('/_+/', '_', $site_machine_name);
  }

  /**
   * Regenerate consumer secret.
   *
   * @option site-url
   *   The site url for which consumer key has to re-regenerate.
   * @option env-file
   *   The file where generated consumer secret should be written.
   * @usage acms:headless:regenerate-env  --site-url='http://localhost:3000' --env-file='../frontend/.env.local'
   *   Regenerate consumer secret.
   *
   * @command acms:headless:regenerate-env
   */
  public function acmsHeadlessRegenerateEnv(array $options = [
    'site-url' => NULL,
    'env-file' => NULL,
  ]) {
    if ($options['site-url']) {
      $cid = $this->starterKit->getHeadlessConsumerDataByUri($options['site-url'])->id();
      $consumer = $this->entityTypeManager->getStorage('consumer')->load($cid);
      if ($consumer) {
        // Generate a new secret key.
        $secret = $this->starterKit->createHeadlessSecret();
        // Apply the new secret to the consumer.
        $consumer->secret = $secret;
        // Update the consumer.
        $consumer->save();

        $site = $this->starterKit->getHeadlessSiteByBaseUrl($options['site-url']);
        $env = $this->starterKit->getEnvironmentVariablesAsString($site);
        if ($file = $options['env-file']) {
          file_put_contents($file, $env);
          $this->logger()->success("Environment variables were written to $file.");
        }
        else {
          $this->logger()->notice("Use these environment variables for your Next.js application. Place them in your .env file:\n" . print_r($env, TRUE));
        }
      }
    }
  }

  /**
   * Hook validates for acms:headless:regenerate-env command.
   *
   * @hook validate acms:headless:regenerate-env
   */
  public function validateAcmsHeadlessRegenerateEnv(CommandData $commandData) {
    $options = $commandData->input()->getOptions();
    $messages = [];
    $existing_sites = $this->entityTypeManager->getStorage('next_site')->loadMultiple();
    if (empty($existing_sites)) {
      $messages[] = dt("There's no Next.js site found, at least one Next.js site should exists in order to generate the secret.");
    }
    if (!isset($options['site-url'])) {
      $messages[] = dt("Missing required parameter site URL.");
    }
    if (isset($options['site-url'])) {
      $site = $this->starterKit->getHeadlessSiteByBaseUrl($options['site-url']);
      if (!$site) {
        $messages[] = dt("No site with base url [@url] found.", ['@url' => $options['site-url']]);
      }
      if ($site) {
        $cid = $this->starterKit->getHeadlessConsumerDataByUri($options['site-url'])->id();
        $consumer = $this->entityTypeManager->getStorage('consumer')->load($cid);
        if (!$consumer) {
          $messages[] = dt("No consumer with redirect url [@url] found.", ['@url' => $options['site-url']]);
        }
      }

    }
    if (isset($options['site-url']) && isset($options['env-file'])) {
      if (!file_exists($options['env-file'])) {
        if (!is_dir($options['env-file'])) {
          $dir = $this->fileSystem->dirname($options['env-file']);
          $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
        }
      }
    }

    if ($messages) {
      return new CommandError(implode('\n', $messages));
    }
  }

}