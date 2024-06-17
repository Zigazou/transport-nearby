#!/usr/bin/env python3
"""Convert Réseau Astuce and AtouMod GTFS data to a SQLite database.

This script needs two files:
- nearby-create-database.db.sql: database schema,
- nearby-create-database.corrections.txt: corrections to apply to names.

The aim of the database is to allow fast queries to find the closest stops and
routes to a given location. This is why additional processes are done to remove
unnecessary data and to generate a cache between stops and routes.

Note: This script has a memory usage peak of around 1.5 GB when importing data.
"""

from sys import argv
from os import remove
from os.path import getctime, dirname, realpath
from time import time
from sqlite3 import connect, Row
from collections import defaultdict
from csv import DictReader
from zipfile import ZipFile
from io import TextIOWrapper
from math import radians
from datetime import datetime
from json import loads, load
from requests import get, codes as HTTP_CODES

SCRIPT_DIRECTORY = dirname(realpath(__file__))
TRANSPORT_DB_SQL = SCRIPT_DIRECTORY + "/nearby-create-database.db.sql"
CORRECTIONS_TXT = SCRIPT_DIRECTORY + "/nearby-create-database.corrections.txt"

PERFORMANCE_SQLITE_PRAGMAS = {
    "locking_mode": "EXCLUSIVE",
    "journal_mode": "WAL",
    "cache_size": "-512000",
    "secure_delete": "FALSE",
    "synchronous": "OFF",
    "temp_store": "MEMORY",
}

# DataGouvIds. Identifiers below have been extracted from the API
# https://transport.data.gouv.fr/api/datasets
DATA_FILES = {
    "astuce": {
        "type": "gtfs",
        "description": "Métropole Rouen Normandie Réseau Astuce GTFS data",
        "datagouv_id": "5cd4321f8b4c4137d1244318",
        "resource_id": 64973,
        "temp_file": "/tmp/astuce.gtfs.zip",
    },
    "cycling": {
        "type": "csv",
        "description": "Métropole Rouen Normandie cycling data",
        "datagouv_id": "61f3ef4cc1ed500d1b135719",
        "resource_id": 79694,
        "temp_file": "/tmp/cycling.csv",
    },
    "lovélo": {
        "type": "gbfs",
        "gbfs_key": "station_information",
        "description": "Métropole Rouen Normandie Lovélo data",
        "datagouv_id": "64919d2f03c6861c686e0e87",
        "resource_id": 81000,
        "temp_file": "/tmp/lovelo.json",
    },
    "atoumod": {
        "type": "gtfs",
        "description": "Région Normandie AtouMod GTFS data",
        "datagouv_id": "5ced52ed8b4c4177b679d377",
        "resource_id": 81628,
        "temp_file": "/tmp/atoumod.gtfs.zip",
    },
    "flixbus": {
        "type": "gtfs",
        "description": "Flixbus GTFS data",
        "datagouv_id": "5c6ad5248b4c411c3d7ae435",
        "resource_id": 11681,
        "temp_file": "/tmp/flixbus.gtfs.zip",
    },
    "blablacarbus": {
        "type": "gtfs",
        "description": "BlaBlaCar Bus GTFS data",
        "datagouv_id": "5cdef3698b4c416d21fd76b9",
        "resource_id": 52605,
        "temp_file": "/tmp/blablacarbus.gtfs.zip",
    },
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

# Fields to prefix with the base_id when importing GTFS data.
FIELD_IDS = ["trip_id", "stop_id", "route_id", "service_id", "cycle_id"]

# Fields to drop from the GTFS data because they are not useful when one needs
# to find the closest stop and routes to a given location.
FIELD_DROPS = [
    "agency_id",
    "stop_url",
    "stop_timezone",
    "stop_desc",
    "zone_id",
    "route_desc",
    "route_color",
    "route_text_color",
    "route_url",
    "route_sort_order",
    "stop_headsign",
    "shape_dist_traveled",
    "timepoint",
    "trip_short_name",
    "trip_headsign",
    "block_id",
    "shape_id",
    "arrival_time",
    "departure_time",
    "pickup_type",
    "drop_off_type",
    "monday",
    "tuesday",
    "wednesday",
    "thursday",
    "friday",
    "saturday",
    "sunday",
    "stop_code",
    "location_type",
    "parent_station",
    "bikes_allowed",
    "trip_bikes_allowed",
    "platform_code",
    "stop_times.route_short_name",
    "trips.route_short_name",
    "ticketing_trip_id",
    "ticketing_type",
]


class CannotDownload(Exception):
    """Exception raised when a download fails."""

    def __init__(self, message: str, url: str, status_code: int):
        super().__init__(message)
        (self.url, self.status_code) = (url, status_code)


def load_corrections(filename: str):
    """Load corrections from a file.

    Args:
        filename (str): The filename of the corrections file.
    """
    with open(filename, encoding="utf-8") as corrections_file:
        return [
            line.split("\t")
            for line in corrections_file.read().splitlines()
            if "\t" in line
        ]


CORRECTIONS = load_corrections(CORRECTIONS_TXT)


class SQLiteDB:
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

        # Let SQLite3 analyze the database to optimize future queries.
        self.cursor.execute("PRAGMA analysis_limit = 40000")
        self.cursor.execute("PRAGMA optimize")

        self.connection.close()


def download_json(url: str):
    """Download a JSON file from a URL.

    Args:
        url (str): The URL of the JSON file.
    """
    res = get(url, timeout=30)

    if res.status_code != HTTP_CODES.ok:
        raise CannotDownload("Failed to download JSON", url, res.status_code)

    return loads(res.content.decode("utf-8"))


def download_dataset_info(datagouvid: str):
    """Download dataset info from DataGouv.

    Args:
        datagouvid (str): The DataGouv ID of the dataset.
    """
    dataset_url = f"https://transport.data.gouv.fr/api/datasets/{datagouvid}"
    return download_json(dataset_url)


def download_as(url: str, filename: str):
    """Download a file from a URL.

    Args:
        url (str): The URL of the file to download.
        filename (str): The filename to save the file.
    """
    res = get(url, timeout=30)

    if res.status_code != HTTP_CODES.ok:
        raise CannotDownload("Failed to download file", url, res.status_code)

    with open(filename, "wb") as file:
        for chunk in res.iter_content():
            file.write(chunk)


def load_csv_from_zip(zipfile: str, csvfile: str) -> list:
    """Load a CSV file from a ZIP archive.
    The CSV file is expected to be encoded in UTF-8 with BOM.

    Args:
        zipfile (str): The ZIP archive file.
        csvfile (str): The CSV file to load from the ZIP archive.
    """
    with ZipFile(zipfile) as handle:
        with TextIOWrapper(handle.open(csvfile), encoding="utf-8-sig") as csv:
            content = [row for row in DictReader(csv)]

    return content


def load_csv(csvfile: str, delimiter=",") -> list:
    """Load a CSV file.

    Args:
        csvfile (str): The CSV file to load.
        delimiter (str, optional): The delimiter used in the CSV file. Defaults
            to ','.
    """
    with open(csvfile, encoding="utf-8-sig") as csv:
        content = [row for row in DictReader(csv, delimiter=delimiter)]

    return content


def create_database(db_filename: str):
    """Create a SQLite database with the given filename.

    The database schema is loaded from TRANSPORT_DB_SQL file.

    Args:
        db_filename (str): The filename of the SQLite database."""
    with SQLiteDB(db_filename) as cursor:
        with open(TRANSPORT_DB_SQL, encoding="utf-8") as schema_file:
            cursor.executescript(schema_file.read())


def sql_insert(table_name: str, record: dict, ignore=False) -> str:
    """Generate an SQL INSERT statement.

    Args:
        table_name (str): The name of the table.
        record (dict): The record to insert.
        ignore (bool, optional): Whether to ignore duplicates when inserting.
            Defaults to False.
    """
    columns = ", ".join(record.keys())
    variables = ", ".join([":" + column for column in record.keys()])
    ignore = " OR IGNORE" if ignore else ""
    return f"INSERT{ignore} INTO {table_name}({columns}) VALUES({variables});"


def normalize_name(name: str) -> str:
    """Normalize a name by applying a set of corrections.

    Args:
        name (str): The name to normalize.
    """
    # Set the name to title case.
    name = name.title()

    for [bad, good] in CORRECTIONS:
        name = name.replace(bad, good)

    return name


def import_table(cursor, table_name: str, base_id: str, records: list, ignore=False):
    """Import a list of records into a table.

    Args:
        cursor (sqlite3.Cursor): The cursor to use to import.
        table_name (str): The name of the table.
        base_id (str): The base ID to prefix to the IDs in the records. The IDs
            are defined in FIELD_IDS.
        records (list): The records to import, indexed by the column names.
        ignore (bool, optional): Whether to ignore duplicates when inserting.
            Defaults to False.
    """
    for record in records:
        values = {
            key: (base_id + record[key] if key in FIELD_IDS else record[key])
            for key in record
            if (key not in FIELD_DROPS and f"{table_name}.{key}" not in FIELD_DROPS)
        }

        cursor.execute(sql_insert(table_name, values, ignore), defaultdict(str, values))


def derive_cycle_type(mobilier: str) -> int:
    """Derive the cycle type from the mobilier field.

    Args:
        mobilier (str): The mobilier field. Possible values are 'ARCEAU',
            'PARC', 'POTELET', 'RATELIER' and 'LOVELO'. Anything else will
            return -1.
    """
    cycle_types = {"ARCEAU": 0, "PARC": 1, "POTELET": 2, "RATELIER": 3, "LOVELO": 4}

    return cycle_types[mobilier] if mobilier in cycle_types else -1


def derive_cycle_free(acces: str) -> int:
    """Derive the cycle free field from the acces field.

    Args:
        acces (str): The acces field. If it is equal to 'LIBRE ACCES', it will
            return 1. Anything else will return 0.
    """
    return 1 if acces == "LIBRE ACCES" else 0


def derive_cycle_latitude(coordonneesxy: str) -> float:
    """Derive the cycle latitude from the coordonneesxy field.

    Args:
        coordonneesxy (str): The coordonneesxy field. It is expected to be in
            the format '(latitude, longitude)'.
    """
    return float(coordonneesxy[1:-1].split(",")[1])


def derive_cycle_longitude(coordonneesxy: str) -> float:
    """Derive the cycle longitude from the coordonneesxy field.

    Args:
        coordonneesxy (str): The coordonneesxy field. It is expected to be in
            the format '(latitude, longitude)'.
    """
    return float(coordonneesxy[1:-1].split(",")[0])


def import_cycle_data(db_filename: str):
    """Import cycle data into the database.

    Args:
        db_filename (str): The filename of the database.
    """

    # Métropole Rouen Normandie cycle data uses ';' as delimiter.
    cycle_data = load_csv(DATA_FILES["cycling"]["temp_file"], ";")

    cycle_stops = [
        {
            "cycle_id": row["id_local"],
            "cycle_name": None,
            "cycle_lat": radians(derive_cycle_latitude(row["coordonneesxy"])),
            "cycle_lon": radians(derive_cycle_longitude(row["coordonneesxy"])),
            "cycle_type": derive_cycle_type(row["mobilier"]),
            "cycle_free": derive_cycle_free(row["acces"]),
        }
        for row in cycle_data
    ]

    with SQLiteDB(db_filename) as cursor:
        import_table(cursor, "cycle_stops", "CYC-", cycle_stops)


def import_lovelo(db_filename: str):
    """Import Lovélo data into the database.

    Args:
        db_filename (str): The filename of the database.
    """

    # Métropole Rouen Normandie cycle data uses ';' as delimiter.
    with open(DATA_FILES["lovélo"]["temp_file"], "rb") as gbfs:
        lovelo_data = load(gbfs)["data"]["stations"]

    lovelo_stops = [
        {
            "cycle_id": row["station_id"],
            "cycle_name": normalize_name(row["name"]),
            "cycle_lat": radians(float(row["lat"])),
            "cycle_lon": radians(float(row["lon"])),
            "cycle_type": derive_cycle_type("LOVELO"),
            "cycle_free": derive_cycle_free("PAYANT"),
        }
        for row in lovelo_data
    ]

    with SQLiteDB(db_filename) as cursor:
        import_table(cursor, "cycle_stops", "LOV-", lovelo_stops)


def import_gtfs_data(zipfile: str, base_id: str, db_filename: str):
    """Import GTFS data into the database from a ZIP file.

    Args:
        zipfile (str): The ZIP file containing the GTFS data.
        base_id (str): The base ID for prefixing the IDs in the records. The IDs
            are defined in FIELD_IDS.
        db_filename (str): The filename of the database.
    """

    datas = {}

    # Import routes while normalizing route_long_name.
    datas["routes"] = []
    for row in load_csv_from_zip(zipfile, "routes.txt"):
        route = row
        route["route_long_name"] = normalize_name(row["route_long_name"])
        datas["routes"].append(route)

    datas["trips"] = load_csv_from_zip(zipfile, "trips.txt")
    datas["stop_times"] = load_csv_from_zip(zipfile, "stop_times.txt")

    datas["stops"] = []
    for row in load_csv_from_zip(zipfile, "stops.txt"):
        # Ignore stops outside the Métropole Rouen Normandie.
        latitude = float(row["stop_lat"])
        if latitude < MIN_LATITUDE or latitude > MAX_LATITUDE:
            continue

        longitude = float(row["stop_lon"])
        if longitude < MIN_LONGITUDE or longitude > MAX_LONGITUDE:
            continue

        stop = row
        stop["stop_name"] = normalize_name(row["stop_name"])
        stop["stop_lat"] = radians(latitude)
        stop["stop_lon"] = radians(longitude)
        datas["stops"].append(stop)

    try:
        datas["calendar"] = load_csv_from_zip(zipfile, "calendar.txt")
        datas["calendar_dates"] = None
    except KeyError:
        datas["calendar"] = None
        datas["calendar_dates"] = load_csv_from_zip(zipfile, "calendar_dates.txt")

    with SQLiteDB(db_filename) as cursor:
        import_table(cursor, "routes", base_id, datas["routes"])
        import_table(cursor, "stops", base_id, datas["stops"])
        import_table(cursor, "stop_times", base_id, datas["stop_times"])
        import_table(cursor, "trips", base_id, datas["trips"])

        if datas["calendar_dates"]:
            import_table(cursor, "calendar_dates", base_id, datas["calendar_dates"])
        else:
            import_table(cursor, "calendar", base_id, datas["calendar"])


def generate_gtfs_cache(db_filename: str):
    """Generate a cache between stops and routes.

    This is necessary to speed up queries to find the closest stops and routes
    because the stops and routes are not directly related in the GTFS data, one
    needs to go through the stop_times and trips tables to find the
    relationship.

    Args:
        db_filename (str): The filename of the database.
    """
    with SQLiteDB(db_filename) as cursor:
        sql = """
            SELECT DISTINCT
                stop_times.stop_id AS "stop_id",
                trips.route_id     AS "route_id",
                trips.service_id   AS "service_id"
            FROM stop_times
            INNER JOIN trips ON stop_times.trip_id = trips.trip_id
        """
        cursor.execute(sql)

        route_stops = [
            {
                "stop_id": row["stop_id"],
                "route_id": row["route_id"],
                "school": 1 if row["service_id"].startswith("AST-IST") else 0,
            }
            for row in cursor.fetchall()
        ]

        import_table(cursor, "cache_stop_routes", "", route_stops, True)


def remove_trips(db_filename: str, trip_ids: list):
    """Remove trips and associated stops and routes from the database.

    Args:
        db_filename (str): The filename of the database.
        trip_ids (list): The list of trip IDs to remove.
    """
    with SQLiteDB(db_filename) as cursor:
        # Find all stops associated with the trip.
        sql_params = ",".join(["?"] * len(trip_ids))
        sql = f"SELECT stop_id FROM stop_times WHERE trip_id IN ({sql_params})"
        cursor.execute(sql, trip_ids)
        stop_ids = {row["stop_id"] for row in cursor.fetchall()}

        # Find all routes associated with the trip.
        sql = f"SELECT route_id FROM trips WHERE trip_id IN ({sql_params})"
        cursor.execute(sql, trip_ids)
        route_ids = {row["route_id"] for row in cursor.fetchall()}

        # Delete trips.
        sql = f"DELETE FROM trips WHERE trip_id IN ({sql_params})"
        cursor.execute(sql, trip_ids)

        # Delete stops associated with the trip.
        sql_params = ",".join(["?"] * len(stop_ids))
        sql = f"DELETE FROM stops WHERE stop_id IN ({sql_params})"
        cursor.execute(sql, list(stop_ids))

        # Delete cache_stop_routes associated with the trip.
        sql = f"DELETE FROM cache_stop_routes WHERE stop_id IN ({sql_params})"
        cursor.execute(sql, list(stop_ids))

        # Delete routes associated with the trip.
        sql_params = ",".join(["?"] * len(route_ids))
        sql = f"DELETE FROM routes WHERE route_id IN ({sql_params})"
        cursor.execute(sql, list(route_ids))


def remove_elevators(db_filename: str):
    """Remove elevators from the database.

    The Réseau Astuce GTFS data contains trips targeting elevators. This
    function removes the elevators from the database because they are not
    useful for stops and routes queries.

    Args:
        db_filename (str): The filename of the database.
    """
    with SQLiteDB(db_filename) as cursor:
        # Find all trips targeting elevators.
        sql = """
            SELECT trip_id
            FROM trips
            WHERE service_id LIKE 'AST-ASCESC%'
            OR service_id LIKE 'AST-___ASC'
        """
        trips = [row["trip_id"] for row in cursor.execute(sql)]

        # Delete elevators.
        cursor.execute("DELETE FROM stops WHERE stop_id LIKE 'AST-___ASC'")

    remove_trips(db_filename, trips)

    with SQLiteDB(db_filename) as cursor:
        # Delete elevators.
        cursor.execute("DELETE FROM stops WHERE stop_id LIKE 'AST-___ASC'")


def remove_atoumod_duplicates(db_filename: str):
    """Remove AtouMod duplicates from the database.

    AtouMod GTFS data contains duplicates of Réseau Astuce routes. This function
    removes the duplicates from the database.

    AtouMod routes have the same name as Réseau Astuce routes but without '<>'.

    Args:
        db_filename (str): The filename of the database.
    """
    with SQLiteDB(db_filename) as cursor:
        # Find all routes from Réseau Astuce.
        # Remove '<>' from route_long_name because AtouMod routes have the same
        # name but without '<>' (This explains why AtouMod routes have double
        # spaces in their names.)
        sql = "SELECT route_long_name FROM routes WHERE route_id LIKE 'AST-%'"
        route_long_names = []
        for row in cursor.execute(sql):
            route_long_names.append(row["route_long_name"].replace("<>", ""))
            route_long_names.append(row["route_long_name"].replace("<>", "/"))

        # Find routes from AtouMod with the same long name.
        sql_params = ",".join(["?"] * len(route_long_names))
        sql = f"""
            SELECT route_id
            FROM routes
            WHERE route_long_name IN ({sql_params})
            AND route_id LIKE 'ATM-%'
        """
        route_ids = [row["route_id"] for row in cursor.execute(sql, route_long_names)]

        sql_params = ",".join(["?"] * len(route_ids))

        # Delete trips associated with the routes.
        sql = f"DELETE FROM trips WHERE route_id IN ({sql_params})"
        cursor.execute(sql, list(route_ids))

        # Delete routes.
        sql = f"DELETE FROM routes WHERE route_id IN ({sql_params})"
        cursor.execute(sql, list(route_ids))

        # Delete cache_stop_routes associated with the routes.
        sql = f"DELETE FROM cache_stop_routes WHERE route_id in ({sql_params})"
        cursor.execute(sql, list(route_ids))


def remove_orphaned_stop_times(db_filename: str):
    """Remove orphaned stop_times from the database.

    An orphaned stop_time is a stop_time that references a stop that does not
    exist in the stops table.

    Args:
        db_filename (str): The filename of the database.
    """
    with SQLiteDB(db_filename) as cursor:
        # Find all orphaned stop_times.
        sql = """
            SELECT stop_times.stop_id AS 'stop_id'
            FROM stop_times
            LEFT JOIN stops ON stop_times.stop_id = stops.stop_id
            WHERE stops.stop_id IS NULL
        """
        stop_ids = {row["stop_id"] for row in cursor.execute(sql)}

        # Delete orphaned stop_times.
        sql_params = ",".join(["?"] * len(stop_ids))
        sql = f"DELETE FROM stop_times WHERE stop_id IN ({sql_params})"
        cursor.execute(sql, list(stop_ids))


def remove_orphaned_trips(db_filename: str):
    """Remove orphaned trips from the database.

    An orphaned trip is a trip that does not have any stop_times.

    Args:
        db_filename (str): The filename of the database.
    """
    with SQLiteDB(db_filename) as cursor:
        # Find all orphaned trips.
        sql = """
            SELECT trips.trip_id AS 'trip_id'
            FROM trips
            LEFT JOIN stop_times ON trips.trip_id = stop_times.trip_id
            WHERE stop_times.trip_id IS NULL
        """
        trip_ids = {row["trip_id"] for row in cursor.execute(sql)}

        # Delete orphaned trips.
        sql_params = ",".join(["?"] * len(trip_ids))
        sql = f"DELETE FROM trips WHERE trip_id IN ({sql_params})"
        cursor.execute(sql, list(trip_ids))


def remove_orphaned_routes(db_filename: str):
    """Remove orphaned routes from the database.

    An orphaned route is a route that does not have any trips.

    Args:
        db_filename (str): The filename of the database.
    """
    with SQLiteDB(db_filename) as cursor:
        # Find all orphaned routes.
        sql = """
            SELECT routes.route_id AS 'route_id'
            FROM routes
            LEFT JOIN trips ON routes.route_id = trips.route_id
            WHERE trips.route_id IS NULL
        """
        route_ids = {row["route_id"] for row in cursor.execute(sql)}

        # Delete orphaned routes.
        sql_params = ",".join(["?"] * len(route_ids))
        sql = f"DELETE FROM routes WHERE route_id IN ({sql_params})"
        cursor.execute(sql, list(route_ids))


def convert_calendar_dates(db_filename: str):
    """Convert calendar_dates to calendar.

    While the calendar table contains the start and end dates of a service, the
    calendar_dates table contains only specific dates.

    This function converts the calendar_dates table to the calendar table by
    finding the start and end dates of a service. This makes the database
    schema more consistent, and lighter.

    Args:
        db_filename (str): The filename of the database.
    """
    with SQLiteDB(db_filename) as cursor:
        # Convert calendar_dates to calendar.
        cursor.execute(
            """
            INSERT INTO calendar(service_id, start_date, end_date)
            SELECT
                service_id AS 'service_id',
                MIN(date) AS 'start_date',
                MAX(date) AS 'end_date' 
            FROM calendar_dates
            GROUP BY service_id
        """
        )

        # Drop the original calendar_dates table.
        cursor.execute("DROP TABLE calendar_dates")


def shrink_database(db_filename: str):
    """Shrink the database to reduce its size.

    This function reclaims unused space in the database file since a lot of
    records has been erased.

    Args:
        db_filename (str): The filename of the database.
    """
    with SQLiteDB(db_filename) as cursor:
        cursor.execute("DROP TABLE stop_times")
        cursor.execute("DROP TABLE trips")
        cursor.execute("VACUUM")


def step(message: str):
    """Print a step message preceded by the time it is sent.

    Args:
        message (str): The message to print.
    """
    print(f"{datetime.now().isoformat()}\t{message}")


def get_resource(info: dict, res_id):
    """Get a ressource from a dataset info.

    Args:
        info (dict): The dataset info.
        res_id (str): The ID of the ressource.
    """
    return next((res for res in info["resources"] if res["id"] == res_id), None)


def get_gbfs_url(url: dict, feed_name: str):
    """Get the URL of a GBFS feed.

    Args:
        url (str): The URL of the GBFS feed.
        feed_name (str): The name of the feed to find.
    """
    gbfs = download_json(url)
    return next(
        (
            feed["url"]
            for feed in gbfs["data"]["fr"]["feeds"]
            if feed["name"] == feed_name
        ),
        None,
    )


def download_all_data():
    """Download all data needed to generate the transport database."""
    one_day = 24 * 60 * 60

    for data_info in DATA_FILES.values():
        # Determine if data has already been download less than one day ago.
        try:
            file_age = getctime(data_info["temp_file"])
            is_up_to_date = (time() - file_age) < one_day
        except FileNotFoundError:
            is_up_to_date = False

        if is_up_to_date:
            step(f"{data_info['description']} is up to date")
            continue

        step(f"Downloading {data_info['description']} metadata")
        info = download_dataset_info(data_info["datagouv_id"])

        resource = get_resource(info, data_info["resource_id"])

        if data_info["type"] == "gbfs":
            step("Get URL of Lovélo station information")
            url = get_gbfs_url(resource["url"], data_info["gbfs_key"])

            step(f"Downloading {data_info['description']}")
            download_as(url, data_info["temp_file"])
        else:
            step(f"Downloading {data_info['description']}")
            download_as(resource["url"], data_info["temp_file"])


def import_astuce_gtfs_data(db_filename: str):
    """Import Réseau Astuce GTFS data into the database."""
    import_gtfs_data(DATA_FILES["astuce"]["temp_file"], "AST-", db_filename)


def import_atoumod_gtfs_data(db_filename: str):
    """Import Réseau Astuce GTFS data into the database."""
    import_gtfs_data(DATA_FILES["atoumod"]["temp_file"], "ATM-", db_filename)


def import_flixbus_gtfs_data(db_filename: str):
    """Import FlixBus GTFS data into the database."""
    import_gtfs_data(DATA_FILES["flixbus"]["temp_file"], "FLX-", db_filename)


def import_blablacarbus_gtfs_data(db_filename: str):
    """Import Blablacar Bus GTFS data into the database."""
    import_gtfs_data(DATA_FILES["blablacarbus"]["temp_file"], "BBC-", db_filename)


def generate_transport_database(db_filename: str):
    """Generate the transport database.

    This function creates the transport database from Réseau Astuce and AtouMod
    GTFS data.

    Args:
        db_filename (str): The filename of the database.
    """
    # Remove the database if it already exists.
    try:
        remove(db_filename)
    except FileNotFoundError:
        pass

    process_steps = [
        ("Creating database", create_database),
        ("Importing BlaBlaCar Bus GTFS data", import_blablacarbus_gtfs_data),
        ("Importing FlixBus GTFS data", import_flixbus_gtfs_data),
        ("Importing Réseau Astuce GTFS data", import_astuce_gtfs_data),
        ("Importing AtouMod GTFS data", import_atoumod_gtfs_data),
        ("Generating cache between stops and routes", generate_gtfs_cache),
        ("Removing AtouMod duplicates", remove_atoumod_duplicates),
        ("Converting calendar_dates to calendar", convert_calendar_dates),
        ("Removing elevators", remove_elevators),
        ("Removing orphaned stop_times", remove_orphaned_stop_times),
        ("Removing orphaned trips", remove_orphaned_trips),
        ("Removing orphaned routes", remove_orphaned_routes),
        ("Importing cycle data", import_cycle_data),
        ("Importing Lovélo data", import_lovelo),
        ("Shrinking database", shrink_database),
    ]

    for step_name, step_function in process_steps:
        step(step_name)
        step_function(db_filename)


if __name__ == "__main__":
    step("[Download all data]")
    download_all_data()

    step("[Generate transport database]")
    generate_transport_database(argv[1])

    step("[Done]")
