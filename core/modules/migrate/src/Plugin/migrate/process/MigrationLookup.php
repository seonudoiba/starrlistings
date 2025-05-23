<?php

namespace Drupal\migrate\Plugin\migrate\process;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\MigrateStubInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Looks up the value of a property based on a previous migration.
 *
 * It is important to maintain relationships among content coming from the
 * source site. For example, on the source site, a given user account may
 * have an ID of 123, but the Drupal user account created from it may have
 * a uid of 456. The migration process maintains the relationships between
 * source and destination identifiers in map tables, and this information
 * is leveraged by the migration_lookup process plugin.
 *
 * Available configuration keys
 * - migration: A single migration ID, or an array of migration IDs.
 * - source_ids: (optional) An array keyed by migration IDs with values that are
 *   a list of source properties.
 * - stub_id: (optional) Identifies the migration which will be used to create
 *   any stub entities.
 * - no_stub: (optional) Prevents the creation of a stub entity when no
 *   relationship is found in the migration map.
 *
 * Examples:
 *
 * Consider a node migration, where you want to maintain authorship. Let's
 * assume that users are previously migrated in a migration named 'users'. The
 * 'users' migration saved the mapping between the source and destination IDs in
 * a map table. The node migration example below maps the node 'uid' property so
 * that we first take the source 'author' value and then do a lookup for the
 * corresponding Drupal user ID from the map table.
 * @code
 * process:
 *   uid:
 *     plugin: migration_lookup
 *     migration: users
 *     source: author
 * @endcode
 *
 * The value of 'migration' can be a list of migration IDs. When using multiple
 * migrations it is possible each use different source identifiers. In this
 * case one can use source_ids which is an array keyed by the migration IDs
 * and the value is a list of source properties. See example below.
 * @code
 * process:
 *   uid:
 *     plugin: migration_lookup
 *     migration:
 *       - users
 *       - members
 *     source_ids:
 *       users:
 *         - author
 *       members:
 *         - id
 * @endcode
 *
 * It's not required to describe source identifiers for each migration. If the
 * source identifier for a migration is not specified, the default source value
 * will be used. In the example below, the 'author' source property will be used
 * to do a lookup in the 'users' migration, and the 'uid' property in the
 * 'members' migration.
 * @code
 * process:
 *   uid:
 *     plugin: migration_lookup
 *     source: uid
 *     migration:
 *       - users
 *       - members
 *     source_ids:
 *       users:
 *         - author
 * @endcode
 *
 * If the migration_lookup plugin does not find the source ID in the migration
 * map it will create a stub entity for the relationship to use. This stub is
 * generated by the migration provided. In the case of multiple migrations the
 * first value of the migration list will be used, but you can select the
 * migration you wish to use by using the stub_id configuration key. The example
 * below uses 'members' migration to create stub entities.
 * @code
 * process:
 *   uid:
 *     plugin: migration_lookup
 *     migration:
 *       - users
 *       - members
 *     stub_id: members
 * @endcode
 *
 * To prevent the creation of a stub entity when no relationship is found in the
 * migration map, 'no_stub' configuration can be used as shown below.
 * @code
 * process:
 *   uid:
 *     plugin: migration_lookup
 *     migration: users
 *     no_stub: true
 *     source: author
 * @endcode
 *
 * If the source value passed in to the plugin is NULL, boolean FALSE, an empty
 * array or an empty string, the plugin will return NULL and stop further
 * processing on the pipeline. This is done for backwards compatibility reasons,
 * and future versions of this plugin should simply return NULL and allow
 * processing to continue.
 * @see https://www.drupal.org/project/drupal/issues/3246666
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 */
#[MigrateProcess('migration_lookup')]
class MigrationLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The migration to be executed.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * The migrate lookup service.
   *
   * @var \Drupal\migrate\MigrateLookupInterface
   */
  protected $migrateLookup;

  /**
   * The migrate stub service.
   *
   * @var \Drupal\migrate\MigrateStubInterface
   */
  protected $migrateStub;

  /**
   * Constructs a MigrationLookup object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The Migration the plugin is being used in.
   * @param \Drupal\migrate\MigrateLookupInterface $migrate_lookup
   *   The migrate lookup service.
   * @param \Drupal\migrate\MigrateStubInterface $migrate_stub
   *   The migrate stub service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, MigrateLookupInterface $migrate_lookup, MigrateStubInterface $migrate_stub) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migration = $migration;
    $this->migrateLookup = $migrate_lookup;
    $this->migrateStub = $migrate_stub;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('migrate.lookup'),
      $container->get('migrate.stub')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $lookup_migration_ids = (array) $this->configuration['migration'];
    $self = FALSE;
    $destination_ids = NULL;
    $source_id_values = [];
    foreach ($lookup_migration_ids as $lookup_migration_id) {
      $lookup_value = $value;
      if ($lookup_migration_id == $this->migration->id()) {
        $self = TRUE;
      }
      if (isset($this->configuration['source_ids'][$lookup_migration_id])) {
        $lookup_value = array_values($row->getMultiple($this->configuration['source_ids'][$lookup_migration_id]));
      }
      $lookup_value = (array) $lookup_value;
      $this->skipInvalid($lookup_value);
      if ($this->isPipelineStopped()) {
        return NULL;
      }
      $source_id_values[$lookup_migration_id] = $lookup_value;

      // Re-throw any PluginException as a MigrateException so the executable
      // can shut down the migration.
      try {
        $destination_id_array = $this->migrateLookup->lookup($lookup_migration_id, $lookup_value);
      }
      catch (PluginNotFoundException $e) {
        $destination_id_array = [];
      }
      catch (MigrateException $e) {
        throw $e;
      }
      catch (\Exception $e) {
        throw new MigrateException(sprintf('A %s was thrown while processing this migration lookup', gettype($e)), $e->getCode(), $e);
      }

      if ($destination_id_array) {
        $destination_ids = array_values(reset($destination_id_array));
        break;
      }
    }

    if (!$destination_ids && !empty($this->configuration['no_stub'])) {
      return NULL;
    }

    if (!$destination_ids && ($self || isset($this->configuration['stub_id']) || count($lookup_migration_ids) == 1)) {
      // If the lookup didn't succeed, figure out which migration will do the
      // stubbing.
      if ($self) {
        $stub_migration = $this->migration->id();
      }
      elseif (isset($this->configuration['stub_id'])) {
        $stub_migration = $this->configuration['stub_id'];
      }
      else {
        $stub_migration = reset($lookup_migration_ids);
      }
      // Rethrow any exception as a MigrateException so the executable can shut
      // down the migration.
      try {
        $destination_ids = $this->migrateStub->createStub($stub_migration, $source_id_values[$stub_migration], [], FALSE);
      }
      catch (\LogicException) {
        // For BC reasons, we must allow attempting to stub a derived migration.
      }
      catch (PluginNotFoundException) {
        // For BC reasons, we must allow attempting to stub a non-existent
        // migration.
      }
      catch (MigrateException $e) {
        throw $e;
      }
      catch (MigrateSkipRowException $e) {
        // Build a new message.
        $skip_row_exception_message = $e->getMessage();
        if (empty($skip_row_exception_message)) {
          $new_message = sprintf("Migration lookup for destination '%s' attempted to create a stub using migration %s, which resulted in a row skip",
            $destination_property,
            $stub_migration,
          );
        }
        else {
          $new_message = sprintf("Migration lookup for destination '%s' attempted to create a stub using migration %s, which resulted in a row skip, with message '%s'",
            $destination_property,
            $stub_migration,
            $skip_row_exception_message,
          );
        }
        throw new MigrateSkipRowException($new_message, 0);
      }
      catch (\Exception $e) {
        throw new MigrateException(sprintf('%s was thrown while attempting to stub: %s', get_class($e), $e->getMessage()), $e->getCode(), $e);
      }
    }
    if ($destination_ids) {
      if (count($destination_ids) == 1) {
        return reset($destination_ids);
      }
      else {
        return $destination_ids;
      }
    }
  }

  /**
   * Skips the migration process entirely if the value is invalid.
   *
   * @param array $value
   *   The incoming value to check.
   */
  protected function skipInvalid(array $value) {
    if (!array_filter($value, [$this, 'isValid'])) {
      $this->stopPipeline();
    }
  }

  /**
   * Determines if the value is valid for lookup.
   *
   * The only values considered invalid are: NULL, FALSE, [] and "".
   *
   * @param string $value
   *   The value to test.
   *
   * @return bool
   *   Return true if the value is valid.
   */
  protected function isValid($value) {
    return !in_array($value, [NULL, FALSE, [], ""], TRUE);
  }

}
