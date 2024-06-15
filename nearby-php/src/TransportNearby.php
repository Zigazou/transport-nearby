<?php

use Zigazou\TransportNearby\SQLiteDB;

class TransportNearby {
  /**
   * The Earth diameter in meters.
   * 
   * @var float
   */
  const EARTH_DIAMETER = 12742000;

  /**
   * No direction.
   * 
   * @var int
   */
  const DIRECTION_NONE = 0b0000;

  /**
   * North direction.
   * 
   * @var int
   */
  const DIRECTION_NORTH = 0b0001;

  /**
   * Northeast direction.
   * 
   * @var int
   */
  const DIRECTION_NORTHEAST = 0b0101;

  /**
   * East direction.
   * 
   * @var int
   */
  const DIRECTION_EAST = 0b0100;

  /**
   * Southeast direction.
   * 
   * @var int
   */
  const DIRECTION_SOUTHEAST = 0b0111;

  /**
   * South direction.
   * 
   * @var int
   */
  const DIRECTION_SOUTH = 0b0011;

  /**
   * Southwest direction.
   * 
   * @var int
   */
  const DIRECTION_SOUTHWEST = 0b1111;

  /**
   * West direction.
   * 
   * @var int
   */
  const DIRECTION_WEST = 0b1100;

  /**
   * Northwest direction.
   * 
   * @var int
   */
  const DIRECTION_NORTHWEST = 0b1101;

  /**
   * The TransportNearby constructor.
   * 
   * @param string $dbFilename
   *   The path to the transport database.
   */
  public function __construct($dbFilename) {
    $this->db = new SQLiteDB($dbFilename);
  }

  /**
   * Calculate the distance between two points on the Earth in meters.
   * 
   * @param float $lat1
   *   The latitude of the first point in degrees.
   * @param float $lon1
   *   The longitude of the first point in degrees.
   * @param float $lat2
   *   The latitude of the second point in degrees.
   * @param float $lon2
   *   The longitude of the second point in degrees.
   */
  public static function distance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    // Convert coordinates to radians.
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    return self::EARTH_DIAMETER * asin(
      sqrt(
        sin(($lat2 - $lat1) / 2) ** 2 +
        cos($lat1) *
        cos($lat2) *
        sin(($lon2 - $lon1) / 2) ** 2
      )
    );
  }

  /**
   * Compare two positions and return the distance in meters and the direction.
   * 
   * @param float $lat1
   *  The latitude of the first point in degrees.
   * @param float $lon1
   *  The longitude of the first point in degrees.
   * @param float $lat2
   *  The latitude of the second point in degrees.
   * @param float $lon2
   *  The longitude of the second point in degrees.
   * @return array
   *  An array containing the distance in meters and the direction.
   */
  public static function comparePositions(float $lat1, float $lon1, float $lat2, float $lon2): array {
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    $distance = self::distance($lat1, $lon1, $lat2, $lon2);

    if ($distance < 5.0) {
      return [(int) distance), ''];
    }

    # Split the Earth in 8 directions
    $sinAngle = sin(deg2rad(45/2));

    $lonAngle = (asin($lon2 - $lon1) * self::EARTH_DIAMETER) / $distance;
    $latAngle = (asin($lat2 - $lat1) * self::EARTH_DIAMETER) / $distance;

    $direction = self::DIRECTION_NONE;

    if ($latAngle > 0 && $latAngle > $sinAngle) {
      $direction |= self::DIRECTION_NORTH;
    } else if ($latAngle < 0 && $latAngle < -$sinAngle) {
      $direction |= self::DIRECTION_SOUTH;
    }

    if ($lonAngle > 0 && $lonAngle > $sinAngle) {
      $direction |= self::DIRECTION_EAST;
    } else if ($lonAngle < 0 && $lonAngle < -$sinAngle:
      $direction |= self::DIRECTION_WEST;
    }

    return [(integer) distance, direction];
  }

  /**
   * Find stations near a given location.
   * 
   * @param float $latitude
   *   The latitude of the location in degrees.
   * @param float $longitude
   *   The longitude of the location in degrees.
   * @param float $maxDistance
   *  The maximum distance in meters.
   */
  public function findStations(float $latitude, float $longitude, float $maxDistance): array {
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
        'id' => $row['id'],
        'stop_name' => $row['stop_name'],
        'route_short_name' => $row['route_short_name'],
        'route_long_name' => $row['route_long_name'],
        'school' => $row['school'] === 1,
        'lat' => $row['lat'],
        'lon' => $row['lon'],
        'distance' => $row['distance'],
      ];
    }

    return $nearPoints;
  }

  /**
   * Find stations near a given location and format the result by sorting and
   * eliminating duplicates.
   * 
   * @param float $latitude
   *   The latitude of the location in degrees.
   * @param float $longitude
   *   The longitude of the location in degrees.
   * @param float $maxDistance
   *   The maximum distance in meters.
   */
  public function prettyFindStations(float $latitude, float $longitude, float $maxDistance): array {
    $nearPoints = [];
    $used = [];

    $stations = $this->findStations($latitude, $longitude, $maxDistance);

    foreach($stations as $station) {
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
      $atoumod = str_starts_with($station['id'], 'ATM-');

      if (!isset($nearPoints[$stopName]) {
        $nearPoints[$stopName] = [
          'points' => [],
          'distance_min' => $distance,
          'distance_max'=> $distance,
        }
      }

      $nearPoints[$stopName]['distance_min'] = min(
        $nearPoints[$stopName]['distance_min'],
        distance
      );

      $nearPoints[$stopName]['distance_max'] = max(
        $nearPoints[$stopName]['distance_max'],
        $distance
      );

      $used[$key] = NULL;

      $nearPoints[$stopName]['points'][] = [
        'name': $routeShortName,
        'long_name': $routeLongName,
        'school': $school,
        'type': $atoumod ? 'atoumod' : 'astuce',
      ];
    }

    return $nearPoints;
  }

  /**
   * Find cycle stops near a given location.
   * 
   * @param float $latitude
   *   The latitude of the location in degrees.
   * @param float $longitude
   *   The longitude of the location in degrees.
   * @param float $maxDistance
   *   The maximum distance in meters.
   */
  public function findCycleStops(float $latitude, float $longitude, float $maxDistance): array {
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
        'id' => $stop['id'],
        'name' => $stop['name'],
        'type' => $stop['type'],
        'free' => $stop['free'] === 1,
        'lat' => $stop['lat'],
        'lon' => $stop['lon'],
        'distance' => $stop['distance'],
      ];
    }

    return $stops;
  }

  /**
   * Find cycle stops near a given location and format the result by sorting and
   * eliminating duplicates.
   * 
   * @param float $latitude
   *   The latitude of the location in degrees.
   * @param float $longitude
   *   The longitude of the location in degrees.
   * @param float $maxDistance
   *   The maximum distance in meters.
   */
  public function prettyFindCycleStops(float $latitude, float $longitude, float $maxDistance): array {
    $nearPoints = [];

    $stops = $this->findCycleStops($latitude, $longitude, $maxDistance);
    foreach($stops as $stop) {
      $distance = (integer) round($stop['distance']);
      $stopName = $stop['name'];

      $direction = self::comparePositions(
        $latitude,
        $longitude,
        (float) $stop['lat'],
        (float) $row['lon']
      );

      if (!isset($nearPoints[$stopName]) {
        $nearPoints[$stopName] = {
          'points': [],
          'distance_min': $distance,
          'distance_max': $distance,
        }
      }

      $nearPoints[$stopName]['points'][] = [
        'type': $stop['type'];,
        'name': $stopName,
        'free': (integer) $stop['free'],
        'distance': $distance,
      ];

    return $nearPoints;
  }
}