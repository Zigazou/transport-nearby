#!/usr/bin/env python3
from sqlite3 import connect, Row
from math import radians, asin, sin, cos, sqrt
from typing import Union
from fastapi import FastAPI
from uvicorn import run

EARTH_DIAMETER = 12742000
TRANSPORT_DB = 'transport.db'

PERFORMANCE_SQLITE_PRAGMAS = {
    'locking_mode': 'EXCLUSIVE',
    'journal_mode': 'WAL',
    'cache_size': '-512000',
    'secure_delete': 'FALSE',
    'synchronous': 'OFF',
    'temp_store': 'MEMORY',
}

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


class SQLiteDB():
    """Context manager to handle a SQLite database connection."""

    def __init__(self, db_filename):
        self.db_filename = db_filename
        self.connection = None
        self.cursor = None

    def __enter__(self):
        self.connection = connect(self.db_filename)
        self.connection.row_factory = Row
        self.cursor = self.connection.cursor()

        for key, value in PERFORMANCE_SQLITE_PRAGMAS.items():
            self.cursor.execute(f"PRAGMA {key} = {value}")

        return self.cursor

    def __exit__(self, *args):
        self.connection.commit()
        self.connection.close()


def find_stations(cursor, max_distance: int, lat: float, lon: float):
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


def find_cycle_stops(cursor, max_distance: int, lat: float, lon: float):
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


def compare_positions(lat1: float, lon1: float, lat2: float, lon2: float):
    lat1 = radians(lat1)
    lon1 = radians(lon1)
    lat2 = radians(lat2)
    lon2 = radians(lon2)

    distance = gps_distance(lat1, lon1, lat2, lon2)

    if distance < 5.0:
        return (int(distance), '')

    # Split the Earth in 8 directions
    sin_angle = sin(radians(45/2))

    lon_angle = (asin(lon2 - lon1) * EARTH_DIAMETER) / distance
    lat_angle = (asin(lat2 - lat1) * EARTH_DIAMETER) / distance

    direction = ''

    if lat_angle > 0 and lat_angle > sin_angle:
        direction += 'N'
    elif lat_angle < 0 and lat_angle < -sin_angle:
        direction += 'S'

    if lon_angle > 0 and lon_angle > sin_angle:
        direction += 'E'
    elif lon_angle < 0 and lon_angle < -sin_angle:
        direction += 'W'

    return (int(distance), direction)


def prepare_stations(cursor, distance: float, latitude: float, longitude: float):
    near_points = {}
    used = set()

    for row in find_stations(cursor, 300, latitude, longitude):
        route_short_name = row['route_short_name']
        school = bool(int(row['school']))

        if (route_short_name, school) in used:
            continue

        if school and (route_short_name, 0) in used:
            continue

        distance = int(row['distance'])
        stop_name = row['stop_name']
        route_long_name = row['route_long_name']
        atoumod = row['id'].startswith('ATM-')

        if stop_name not in near_points:
            near_points[stop_name] = {
                'points': [],
                'distance_min': distance,
                'distance_max': distance,
            }

        near_points[stop_name]['distance_min'] = min(
            near_points[stop_name]['distance_min'],
            distance
        )

        near_points[stop_name]['distance_max'] = max(
            near_points[stop_name]['distance_max'],
            distance
        )

        used.add((route_short_name, school))

        near_points[stop_name]['points'].append({
            'name': route_short_name,
            'long_name': route_long_name,
            'school': school,
            'type': 'atoumod' if atoumod else 'astuce',
        })

    return near_points


def prepare_cycle_stops(cursor, distance: float, latitude: float, longitude: float):
    near_points = {}

    for row in find_cycle_stops(cursor, 150, latitude, longitude):
        distance = int(row['distance'])
        stop_name = row['name']
        stop_type = row['type']
        stop_free = bool(int(row['free']))
        stop_latitude = float(row['lat'])
        stop_longitude = float(row['lon'])

        direction = compare_positions(
            latitude,
            longitude,
            stop_latitude,
            stop_longitude
        )

        if stop_name not in near_points:
            near_points[stop_name] = {
                'points': [],
                'distance_min': distance,
                'distance_max': distance,
            }

        near_points[stop_name]['points'].append({
            'type': stop_type,
            'name': stop_name,
            'free': stop_free,
            'distance': distance,
        })

    return near_points


near_facilities_app = FastAPI()


@near_facilities_app.get("/transport_facilities/{latitude}/{longitude}")
def find_facilities(latitude: float, longitude: float):
    latitude = float(latitude)
    longitude = float(longitude)

    with SQLiteDB(TRANSPORT_DB) as cursor:
        return {
            'stations': prepare_stations(cursor, 300, latitude, longitude),
            'cycle_stops': prepare_cycle_stops(cursor, 150, latitude, longitude),
        }


def run_server():
    run(near_facilities_app, host="127.0.0.1", port=8080)


if __name__ == '__main__':
    run_server()
