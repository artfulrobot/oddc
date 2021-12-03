<?php
namespace Civi\Oddc;

use CRM_Core_DAO;

/**
 *
 * A simple self-maintaining cache.
 *
 * This is a copy of inlaypay's cache but with a name change. See sql/crate_cache.sql
 *
 * We can't use Civi's cache because we do not want a cache clear to erase our cache values,
 * as this would risk losing data and income!
 */
class Cache {

  public static $cacheHasBeenTrimmed = FALSE;

  /**
   * Get a value from our storage, as long as it hasn't expired.
   */
  public static function getCacheVal(string $key, bool $andDelete=TRUE) : ?Array {
    if (!static::$cacheHasBeenTrimmed) {
      // Once per PHP request, trim the cache table to keep it from bloating.
      // It's imagined that this table will be tiny, so this won't slow stuff
      // down much and therefore does not warrant a cron task.
      static::$cacheHasBeenTrimmed = TRUE;
      CRM_Core_DAO::executeQuery('DELETE FROM civicrm_oddc_cache WHERE expires <= CURRENT_TIMESTAMP');
    }

    // Find the key we need, as long as it's in-date
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT * FROM civicrm_oddc_cache WHERE `key` = %1 AND expires > CURRENT_TIMESTAMP', [
      1 => [$key, 'String']
    ]);
    if ($dao->fetch()) {
      if ($andDelete) {
        // Ensure it can only be used once.
        static::deleteCacheVal($key);
      }
      return json_decode($dao->value, TRUE);
    }

    return NULL;
  }
  /**
   * Delete a key
   */
  public static function deleteCacheVal(string $key) :void {
    CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_oddc_cache WHERE `key` = %1',
      [1 => [$key, 'String']]);
  }

  /**
   * Store a value in our storage.
   *
   * Default lifespan is 30mins.
   */
  public static function setCacheVal(string $key, $value, int $lifespanMinutes=30) :void {
    // Make sure key does not exist.
    static::deleteCacheVal($key);
    // Insert it
    CRM_Core_DAO::executeQuery(
      'INSERT INTO civicrm_oddc_cache VALUES (%1, %2, CURRENT_TIMESTAMP + INTERVAL %3 MINUTE)',
      [
        1 => [$key, 'String'],
        2 => [json_encode($value), 'String'],
        3 => [$lifespanMinutes, 'Integer'],
      ]
    );
  }
}

