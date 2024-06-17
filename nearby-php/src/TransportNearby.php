<?php declare(strict_types=1);
namespace Zigazou\TransportNearby;

use Zigazou\TransportNearby\SQLiteDB;

class TransportNearby
{
  /**
   * The Earth diameter in meters.
   * 
   * @var float
   */
  public const EARTH_DIAMETER = 12742142;

  /**
   * No direction.
   * 
   * @var int
   */
  public const DIRECTION_NONE = 0b0000;

  /**
   * North direction.
   * 
   * @var int
   */
  public const DIRECTION_NORTH = 0b0001;

  /**
   * Northeast direction.
   * 
   * @var int
   */
  public const DIRECTION_NORTHEAST = 0b0101;

  /**
   * East direction.
   * 
   * @var int
   */
  public const DIRECTION_EAST = 0b0100;

  /**
   * Southeast direction.
   * 
   * @var int
   */
  public const DIRECTION_SOUTHEAST = 0b0111;

  /**
   * South direction.
   * 
   * @var int
   */
  public const DIRECTION_SOUTH = 0b0011;

  /**
   * Southwest direction.
   * 
   * @var int
   */
  public const DIRECTION_SOUTHWEST = 0b1111;

  /**
   * West direction.
   * 
   * @var int
   */
  public const DIRECTION_WEST = 0b1100;

  /**
   * Northwest direction.
   * 
   * @var int
   */
  public const DIRECTION_NORTHWEST = 0b1101;

  /**
   * The minimum distance in meters to determine direction.
   * 
   * @var float
   */
  public const MIN_DISTANCE = 5.0;

  /**
   * The SQLiteDB database object.
   * 
   * @var SQLiteDB
   */
  protected SQLiteDB $db;

  /**
   * The TransportNearby constructor.
   * 
   * @param string $dbFilename
   *   The path to the transport database.
   */
  public function __construct(string $dbFilename)
  {
    $this->db = new SQLiteDB($dbFilename);
  }

  /**
   * Calculate the distance between two points on the Earth in meters according
   * to the Haversine formula.
   *
   * @param float $latitude1 The latitude of the first point in degrees.
   * @param float $longitude1 The longitude of the first point in degrees.
   * @param float $latitude2 The latitude of the second point in degrees.
   * @param float $longitude2 The longitude of the second point in degrees.
   * @return float The distance in meters.
   */
  public static function distance(
    float $latitude1,
    float $longitude1,
    float $latitude2,
    float $longitude2
  ): float {
    // Convert coordinates to radians.
    $latitude1 = deg2rad($latitude1);
    $longitude1 = deg2rad($longitude1);
    $latitude2 = deg2rad($latitude2);
    $longitude2 = deg2rad($longitude2);

    return self::EARTH_DIAMETER * asin(
      sqrt(
        sin(($latitude2 - $latitude1) / 2) ** 2 +
        cos($latitude1) *
        cos($latitude2) *
        sin(($longitude2 - $longitude1) / 2) ** 2
      )
    );
  }

  /**
   * Compare two positions and return the direction to go from the first point
   * to the second.
   *
   * @param float $latitude1 The latitude of the first point in degrees.
   * @param float $longitude1 The longitude of the first point in degrees.
   * @param float $latitude2 The latitude of the second point in degrees.
   * @param float $longitude2 The longitude of the second point in degrees.
   * @return integer The direction.
   */
  public static function comparePositions(
    float $latitude1,
    float $longitude1,
    float $latitude2,
    float $longitude2
  ): int {
    $distance = self::distance(
      $latitude1,
      $longitude1,
      $latitude2,
      $longitude2
    );

    $latitude1 = deg2rad($latitude1);
    $longitude1 = deg2rad($longitude1);
    $latitude2 = deg2rad($latitude2);
    $longitude2 = deg2rad($longitude2);

    if ($distance < self::MIN_DISTANCE) {
      return self::DIRECTION_NONE;
    }

    # Split the Earth in 8 directions
    $sinAngle = sin(deg2rad(45.0 / 2.0));

    $longitudeAngle =
      (asin($longitude2 - $longitude1) * self::EARTH_DIAMETER) / $distance;

    $latitudeAngle =
      (asin($latitude2 - $latitude1) * self::EARTH_DIAMETER) / $distance;

    $direction = self::DIRECTION_NONE;

    if ($latitudeAngle > 0.0 && $latitudeAngle > $sinAngle) {
      $direction |= self::DIRECTION_NORTH;
    } else if ($latitudeAngle < 0.0 && $latitudeAngle < -$sinAngle) {
      $direction |= self::DIRECTION_SOUTH;
    }

    if ($longitudeAngle > 0.0 && $longitudeAngle > $sinAngle) {
      $direction |= self::DIRECTION_EAST;
    } else if ($longitudeAngle < 0.0 && $longitudeAngle < -$sinAngle) {
      $direction |= self::DIRECTION_WEST;
    }

    return (integer) $direction;
  }

  /**
   * Find stations near a given location.
   *
   * It returns an array of stations.
   * 
   * Each station has the following keys:
   * - id: The identifier of the station.
   * - stop_name: The human name of the station.
   * - route_short_name: The short name of the route.
   * - route_long_name: The long name of the route.
   * - school: A boolean indicating if the route is for school.
   * - lat: Latitude of the station in degrees.
   * - lon: Longitude of the station in degrees.
   * - distance: distance in meters.
   * 
   * @param float $latitude The latitude of the location in degrees.
   * @param float $longitude The longitude of the location in degrees.
   * @param float $maxDistance The maximum distance in meters.
   */
  public function findStations(
    float $latitude,
    float $longitude,
    float $maxDistance
  ): array {
    $sql =
      "SELECT
        stops.stop_id             AS 'id',
        stops.stop_name           AS 'stop_name',
        routes.route_short_name   AS 'route_short_name',
        routes.route_long_name    AS 'route_long_name',
        cache_stop_routes.school  AS 'school',
        DEGREES(stops.stop_lat)   AS 'lat',
        DEGREES(stops.stop_lon)   AS 'lon',
        :diameter * ASIN(
          SQRT(
            POW(SIN((:latitude - stops.stop_lat) / 2), 2) +
            COS(:latitude) *
            COS(stops.stop_lat) *
            POW(SIN((:longitude - stops.stop_lon) / 2), 2)
          )
        )                         AS 'distance'
      FROM stops
      INNER JOIN cache_stop_routes
              ON stops.stop_id = cache_stop_routes.stop_id
      INNER JOIN routes
              ON cache_stop_routes.route_id = routes.route_id
      WHERE distance < :max_distance
      ORDER BY distance, school
    ";

    $result = $this->db->query($sql, [
      ':diameter' => self::EARTH_DIAMETER,
      ':latitude' => deg2rad($latitude),
      ':longitude' => deg2rad($longitude),
      ':max_distance' => $maxDistance,
    ]);

    if ($result->numColumns() === 0) {
      return [];
    }

    $nearPoints = [];
    $used = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
      $nearPoints[] = [
        'id' => (string) $row['id'],
        'stop_name' => (string) $row['stop_name'],
        'route_short_name' => (string) $row['route_short_name'],
        'route_long_name' => (string) $row['route_long_name'],
        'school' => (bool) ($row['school'] === 1),
        'lat' => (float) $row['lat'],
        'lon' => (float) $row['lon'],
        'distance' => (float) $row['distance'],
      ];
    }

    return $nearPoints;
  }

  /**
   * Find stations near a given location and format the result by sorting and
   * eliminating duplicates.
   * 
   * It returns an array indexed by station names.
   * 
   * Each station contains the following keys:
   * - points: An array routes stopping at the station.
   * - distance_min: The minimum distance in meters.
   * - distance_max: The maximum distance in meters.
   * 
   * Each point contains the following keys:
   * - name: The name of the route.
   * - long_name: The long name of the route.
   * - school: A boolean indicating if the route is for school.
   * - type: The network type (AST, ATM, FLX or BBC).
   * - lat: The latitude of the station in degrees.
   * - lon: The longitude of the station in degrees.
   * 
   * @param float $latitude The latitude of the location in degrees.
   * @param float $longitude The longitude of the location in degrees.
   * @param float $maxDistance The maximum distance in meters.
   */
  public function prettyFindStations(
    float $latitude,
    float $longitude,
    float $maxDistance
  ): array {
    $nearPoints = [];
    $used = [];

    $stations = $this->findStations($latitude, $longitude, $maxDistance);

    foreach ($stations as $station) {
      $routeShortName = $station['route_short_name'];
      $school = (bool) $station['school'];
      $key = $school . '-' . $routeShortName;
      $keyNoSchool = '0-' . $routeShortName;

      if (isset($used[$key]) || ($school && isset($user[$keyNoSchool]))) {
        continue;
      }

      $distance = (integer) round($station['distance']);
      $stopName = $station['stop_name'];
      $routeLongName = $station['route_long_name'];
      $transportType = mb_substr($station['id'], 0, 3);

      if (!isset($nearPoints[$stopName])) {
        $nearPoints[$stopName] = [
          'points' => [],
          'distance_min' => $distance,
          'distance_max' => $distance,
        ];
      }

      $nearPoints[$stopName]['distance_min'] = min(
        $nearPoints[$stopName]['distance_min'],
        $distance
      );

      $nearPoints[$stopName]['distance_max'] = max(
        $nearPoints[$stopName]['distance_max'],
        $distance
      );

      $used[$key] = 1;

      $nearPoints[$stopName]['points'][] = [
        'name' => (string) $routeShortName,
        'long_name' => (string) $routeLongName,
        'school' => (bool) $school,
        'type' => (string) $transportType,
        'lat' => (float) $station['lat'],
        'lon' => (float) $station['lon'],
      ];
    }

    return $nearPoints;
  }

  /**
   * Find cycle stops near a given location.
   *
   * It returns an array of stops.
   * 
   * Each stop has the following keys:
   * - id: The identifier of the cycle stop.
   * - name: The human name of the cycle stop.
   * - type: The type of the cycle stop.
   * - free: A boolean indicating if the cycle stop is free.
   * - lat: Latitude of the cycle stop in degrees.
   * - lon: Longitude of the cycle stop in degrees.
   * - distance: distance in meters.
   * 
   * @param float $latitude The latitude of the location in degrees.
   * @param float $longitude The longitude of the location in degrees.
   * @param float $maxDistance The maximum distance in meters.
   */
  public function findCycleStops(
    float $latitude,
    float $longitude,
    float $maxDistance
  ): array {
    $sql =
      "SELECT
        cycle_stops.cycle_id            AS 'id',
        cycle_stops.cycle_name          AS 'name',
        cycle_stops.cycle_type          AS 'type',
        cycle_stops.cycle_free          AS 'free',
        DEGREES(cycle_stops.cycle_lat)  AS 'lat',
        DEGREES(cycle_stops.cycle_lon)  AS 'lon',
        :diameter * ASIN(
          SQRT(
            POW(SIN((:latitude - cycle_stops.cycle_lat) / 2), 2) +
            COS(:latitude) *
            COS(cycle_stops.cycle_lat) *
            POW(SIN((:longitude - cycle_stops.cycle_lon) / 2), 2)
          )
        )                               AS 'distance'
      FROM cycle_stops
      WHERE distance < :max_distance
      ORDER BY distance
    ";

    $result = $this->db->query($sql, [
      ':diameter' => self::EARTH_DIAMETER,
      ':latitude' => deg2rad($latitude),
      ':longitude' => deg2rad($longitude),
      ':max_distance' => $maxDistance,
    ]);

    if ($result->numColumns() === 0) {
      return [];
    }

    $stops = [];
    while ($stop = $result->fetchArray(SQLITE3_ASSOC)) {
      $stops[] = [
        'id' => (string) $stop['id'],
        'name' => (string) $stop['name'],
        'type' => (integer) $stop['type'],
        'free' => (bool) ($stop['free'] === 1),
        'lat' => (float) $stop['lat'],
        'lon' => (float) $stop['lon'],
        'distance' => (float) $stop['distance'],
      ];
    }

    return $stops;
  }

  /**
   * Find cycle stops near a given location and format the result by sorting and
   * eliminating duplicates.
   * 
   * The result is an array of cycle stops indexed by the name of the cycle
   * stop. If the cycle stop has no name, the index is '#noname'.
   * 
   * Each cycle stop contains the following keys:
   * - points: An array of points.
   * - distance_min: The minimum distance in meters.
   * - distance_max: The maximum distance in meters.
   * 
   * Each point contains the following keys:
   * - type: The type of the cycle stop.
   * - free: A boolean indicating if the cycle stop is free.
   * - distance: The distance in meters.
   * - lat: The latitude of the cycle stop in degrees.
   * - lon: The longitude of the cycle stop in degrees.
   * 
   * @param float $latitude The latitude of the location in degrees.
   * @param float $longitude The longitude of the location in degrees.
   * @param float $maxDistance The maximum distance in meters.
   * @return array
   */
  public function prettyFindCycleStops(
    float $latitude,
    float $longitude,
    float $maxDistance
  ): array {
    $nearPoints = [];

    $stops = $this->findCycleStops($latitude, $longitude, $maxDistance);
    foreach ($stops as $stop) {
      $distance = (integer) round($stop['distance']);
      $stopName = empty($stop['name']) ? '#noname' : $stop['name'];

      $direction = self::comparePositions(
        $latitude,
        $longitude,
        (float) $stop['lat'],
        (float) $stop['lon']
      );

      if (!isset($nearPoints[$stopName])) {
        $nearPoints[$stopName] = [
          'points' => [],
          'distance_min' => (float) $distance,
          'distance_max' => (float) $distance,
        ];
      }

      $nearPoints[$stopName]['points'][] = [
        'type' => (integer) $stop['type'],
        'free' => (integer) $stop['free'],
        'distance' => (float) $distance,
        'direction' => (integer) $direction,
        'lat' => (float) $stop['lat'],
        'lon' => (float) $stop['lon'],
      ];
    }

    return $nearPoints;
  }
}
