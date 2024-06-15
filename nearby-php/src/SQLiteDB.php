<?php
# Bounding box of the Métropole Rouen Normandie.
MRN_FAR_EAST = {"lat": 49.48682, "lon": 0.77446}
MRN_FAR_NORTH = {"lat": 49.54676, "lon": 0.85565}
MRN_FAR_WEST = {"lat": 49.37555, "lon": 1.28984}
MRN_FAR_SOUTH = {"lat": 49.25066, "lon": 1.00526}

MAX_LATITUDE = max(MRN_FAR_NORTH["lat"], MRN_FAR_SOUTH["lat"])
MIN_LATITUDE = min(MRN_FAR_NORTH["lat"], MRN_FAR_SOUTH["lat"])
MAX_LONGITUDE = max(MRN_FAR_EAST["lon"], MRN_FAR_WEST["lon"])
MIN_LONGITUDE = min(MRN_FAR_EAST["lon"], MRN_FAR_WEST["lon"])


def gps_distance(lat1: float, lon1: float, lat2: float, lon2: float):
  return EARTH_DIAMETER * asin(
    sqrt(
      sin((lat2 - lat1) / 2) ** 2 +
      cos(lat1) *
      cos(lat2) *
      sin((lon2 - lon1) / 2) ** 2
    )
  )


final class SQLiteDB {
  /**
   * The database path.
   *
   * @var string
   */
  protected $dbFilename;

  /**
   * SQLiteDB constructor.
   *
   * @param string $dbFilename
   *   The path to the transport database.
   */
  public function __construct(string $dbFilename) {
    $this->db = new \SQLite3($dbFilename);

    // Optimizations.
    $this->db->exec("PRAGMA locking_mode = EXCLUSIVE");
    $this->db->exec("PRAGMA journal_mode = WAL");
    $this->db->exec("PRAGMA cache_size = -512000");
    $this->db->exec("PRAGMA secure_delete = FALSE");
    $this->db->exec("PRAGMA synchronous = OFF");
    $this->db->exec("PRAGMA temp_store = MEMORY");
  }

  public function __destruct() {
    $this->connection->commit();
    $this->connection->close();
  }

  public function query(string $sql, array $bindings = []): \SQLite3Result {
    $stmt = $this->db->prepare($sql);

    foreach ($bindings as $key => $value) {
      $sqliteType = SQLITE3_TEXT;
      if (is_int($value)) {
        $sqliteType = SQLITE3_INTEGER;
      } elseif (is_float($value)) {
        $sqliteType = SQLITE3_FLOAT);
      }

      $stmt->bindValue($key, $value, $sqliteType);
    }

    return $stmt->execute();
  }

  public static function findStations($latitude, $longitude, $maxDistance) {
  }
}