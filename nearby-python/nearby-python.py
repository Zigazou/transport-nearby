#!/usr/bin/env python3

from sqlite3 import connect, Row
from math import radians, asin, sin, cos, sqrt

EARTH_DIAMETER = 12742000


def gtfs(database_filename: str, max_distance: int, lat: float, lon: float):
    connection = connect(database_filename)
    connection.row_factory = Row
    cursor = connection.cursor()
    lat = radians(lat)
    lon = radians(lon)

    sql = """
        SELECT
            stops.stop_id AS 'id',
            stops.stop_name AS 'stop_name',
            routes.route_short_name AS 'route_short_name',
            routes.route_long_name AS 'route_long_name',
            cache_stop_routes.school AS 'school',
            DEGREES(stops.stop_lat) AS 'lat',
            DEGREES(stops.stop_lon) AS 'lon',
            :diameter * ASIN(
                SQRT(
                    POW(SIN((:latitude - stops.stop_lat) / 2), 2) +
                    COS(:latitude) *
                    COS(stops.stop_lat) *
                    POW(SIN((:longitude - stops.stop_lon) / 2), 2)
                )
            ) AS 'distance'
        FROM stops
        INNER JOIN cache_stop_routes
                ON stops.stop_id = cache_stop_routes.stop_id
        INNER JOIN routes
                ON cache_stop_routes.route_id = routes.route_id
        WHERE distance < :max_distance
        ORDER BY distance, school
    """
    cursor.execute(
        sql,
        {
            'diameter': EARTH_DIAMETER,
            'latitude': lat,
            'longitude': lon,
            'max_distance': max_distance
        }
    )
    return [row for row in cursor.fetchall()]


def gps_distance(lat1: float, lon1: float, lat2: float, lon2: float):
    return EARTH_DIAMETER * asin(
        sqrt(
            sin((lat2 - lat1) / 2) ** 2 +
            cos(lat1) *
            cos(lat2) *
            sin((lon2 - lon1) / 2) ** 2
        )
    )


def compare_positions(lat1: float, lon1: float, lat2: float, lon2: float):
    lat1 = radians(lat1)
    lon1 = radians(lon1)
    lat2 = radians(lat2)
    lon2 = radians(lon2)

    distance = gps_distance(lat1, lon1, lat2, lon2)

    if distance < 5.0:
        return f"à {int(distance)}m d’ici"

    # Split the Earth in 8 directions
    sin_angle = sin(radians(45/2))

    lon_angle = (asin(lon2 - lon1) * EARTH_DIAMETER) / distance
    lat_angle = (asin(lat2 - lat1) * EARTH_DIAMETER) / distance

    direction = ''

    if lat_angle > 0 and lat_angle > sin_angle:
        direction += 'au nord'
    elif lat_angle < 0 and lat_angle < -sin_angle:
        direction += 'au sud'

    if lon_angle > 0 and lon_angle > sin_angle:
        direction += ("-" if direction else "à l’") + 'est'
    elif lon_angle < 0 and lon_angle < -sin_angle:
        direction += ("-" if direction else "à l’") + 'ouest'

    return f"à {int(distance)}m {direction}"


def cycle_stops(database_filename: str, max_distance: int, lat: float, lon: float):
    connection = connect(database_filename)
    connection.row_factory = Row
    cursor = connection.cursor()
    lat = radians(lat)
    lon = radians(lon)
    earth_diameter = 12742000

    sql = """
        SELECT
            cycle_stops.cycle_id AS 'id',
            cycle_stops.cycle_name AS 'name',
            cycle_stops.cycle_type AS 'type',
            cycle_stops.cycle_free AS 'free',
            DEGREES(cycle_stops.cycle_lat) AS 'lat',
            DEGREES(cycle_stops.cycle_lon) AS 'lon',
            :diameter * ASIN(
                SQRT(
                    POW(SIN((:latitude - cycle_stops.cycle_lat) / 2), 2) +
                    COS(:latitude) *
                    COS(cycle_stops.cycle_lat) *
                    POW(SIN((:longitude - cycle_stops.cycle_lon) / 2), 2)
                )
            ) AS 'distance'
        FROM cycle_stops
        WHERE distance < :max_distance
        ORDER BY distance
    """
    cursor.execute(
        sql,
        {
            'diameter': earth_diameter,
            'latitude': lat,
            'longitude': lon,
            'max_distance': max_distance
        }
    )
    return [row for row in cursor.fetchall()]


points = {
    'ei': [49.438890217904074, 1.0917218961064563],
    'fb': [49.436875053104544, 1.1118279406028548],
    'vf': [49.43800735961855, 1.142694196803923],
    'csc': [49.43351963077896, 1.1100699256053772],
    'emmn': [49.44344, 1.10493],
    'pss': [49.43066, 1.0853],
    'hdv': [49.44327, 1.09983],
}

coordinates = points['emmn']

near_points = {}
distance_mins = {}
distance_maxs = {}
used = set()
for row in gtfs('transport.db', 300, coordinates[0], coordinates[1]):
    distance = int(row['distance'])
    stop_name = row['stop_name']
    route_long_name = row['route_long_name']
    route_short_name = row['route_short_name']
    school = int(row['school'])
    atoumod = row['id'].startswith('ATM-')

    if (route_short_name, school) in used:
        continue

    if school and (route_short_name, 0) in used:
        continue

    if stop_name not in near_points:
        near_points[stop_name] = set()
        distance_mins[stop_name] = distance
        distance_maxs[stop_name] = distance

    distance_mins[stop_name] = min(distance_mins[stop_name], distance)
    distance_maxs[stop_name] = max(distance_maxs[stop_name], distance)

    used.add((route_short_name, school))

    if atoumod:
        near_points[stop_name].add(f"{route_long_name} ({route_short_name})")
    elif school:
        near_points[stop_name].add(f"{route_short_name} (scolaire)")
    else:
        near_points[stop_name].add(route_short_name)

print(f"À moins de 300 mètres")
for near_point in near_points:
    lines = ", ".join(sorted(list(near_points[near_point])))
    distance_min = round(distance_mins[near_point] / 10) * 10
    distance_max = round(distance_maxs[near_point] / 10) * 10
    many = 's' if len(near_points[near_point]) > 1 else ''
    if distance_min == distance_max:
        print(f"    - Arrêt {near_point} à {distance_min}m : ligne{many} {lines}")
    else:
        print(
            f"    - Arrêt {near_point} de {distance_min} à {distance_max}m : ligne{many} {lines}")


print()
print(f"Arceaux vélo à moins de 150 mètres")
cycle_stops_data = cycle_stops(
    'transport.db', 150, coordinates[0], coordinates[1])

free_count = 0
for row in cycle_stops_data:
    if not row['free'] or row['type'] == 4:
        continue

    lat1 = coordinates[0]
    lon1 = coordinates[1]

    lat2 = row['lat']
    lon2 = row['lon']

    direction = compare_positions(lat1, lon1, lat2, lon2)

    print(f"    - {direction}")

print()
print("Stations Lovélo")
for row in cycle_stops_data:
    if row['type'] != 4:
        continue

    lat1 = coordinates[0]
    lon1 = coordinates[1]

    lat2 = row['lat']
    lon2 = row['lon']

    direction = compare_positions(lat1, lon1, lat2, lon2)

    print(f"    - {row['name']} {direction}")
