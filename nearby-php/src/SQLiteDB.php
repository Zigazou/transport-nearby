<?php declare(strict_types=1);
namespace Zigazou\TransportNearby;

use Zigazou\TransportNearby\Exception\FileNotFoundException;
use Zigazou\TransportNearby\Exception\BadDatabaseException;

final class SQLiteDB {
  /**
   * The SQLite3 database object.
   * 
   * @var \SQLite3
   */
  protected \SQLite3 $db;

  /**
   * SQLiteDB constructor.
   *
   * @param string $dbFilename
   *   The path to the transport database.
   */
  public function __construct(string $dbFilename) {
    // Check if the database file exists and is readable.
    if (!is_readable($dbFilename)) {
      throw new FileNotFoundException(
        sprintf("Database file not found or not readable: %s", $dbFilename)
      );
    }

    // Open the database and enable SQLite3 exceptions.
    $this->db = new \SQLite3($dbFilename, SQLITE3_OPEN_READWRITE);
    $this->db->enableExceptions(true);

    // Minimum checks.
    try {
      $this->db->exec("PRAGMA quick_check(1)");
    } catch (\SQLite3Exception $e) {
      throw new BadDatabaseException("The database is corrupted.");
    }

    // Optimize SQLite3 for reading only.
    $this->db->exec("PRAGMA journal_mode = WAL");
    $this->db->exec("PRAGMA locking_mode = NORMAL");
    $this->db->exec("PRAGMA cache_size = -512000");
    $this->db->exec("PRAGMA secure_delete = FALSE");
    $this->db->exec("PRAGMA synchronous = OFF");
    $this->db->exec("PRAGMA temp_store = MEMORY");
  }

  public function __destruct() {
    $this->db->close();
  }

  public function query(string $sql, array $bindings = []): \SQLite3Result {
    $stmt = $this->db->prepare($sql);

    // Automatically bind values with their SQLite3 counterpart types.
    // This only supports integers, floats and strings.
    foreach ($bindings as $key => $value) {
      $sqliteType = SQLITE3_TEXT;
      if (is_int($value)) {
        $sqliteType = SQLITE3_INTEGER;
      } elseif (is_float($value)) {
        $sqliteType = SQLITE3_FLOAT;
      }

      $stmt->bindValue($key, $value, $sqliteType);
    }

    return $stmt->execute();
  }
}