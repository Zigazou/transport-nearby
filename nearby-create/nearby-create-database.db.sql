BEGIN TRANSACTION;
DROP TABLE IF EXISTS "stop_times";
CREATE TABLE IF NOT EXISTS "stop_times" (
	"trip_id"	TEXT NOT NULL,
	"stop_id"	TEXT,
	"stop_sequence"	INTEGER
);
DROP TABLE IF EXISTS "cycle_stops";
CREATE TABLE IF NOT EXISTS "cycle_stops" (
	"cycle_id"	TEXT NOT NULL,
	"cycle_name" TEXT,
	"cycle_lat"	REAL NOT NULL,
	"cycle_lon"	REAL NOT NULL,
	"cycle_type"	INTEGER NOT NULL,
	"cycle_free"	INTEGER NOT NULL,
	PRIMARY KEY("cycle_id")
);
DROP TABLE IF EXISTS "cache_stop_routes";
CREATE TABLE IF NOT EXISTS "cache_stop_routes" (
	"stop_id"	TEXT NOT NULL,
	"route_id"	TEXT NOT NULL,
	"school"	INTEGER NOT NULL,
	PRIMARY KEY("stop_id","route_id","school")
);
DROP TABLE IF EXISTS "calendar";
CREATE TABLE IF NOT EXISTS "calendar" (
	"service_id"	TEXT NOT NULL,
	"start_date"	TEXT NOT NULL,
	"end_date"	TEXT NOT NULL,
	PRIMARY KEY("service_id")
);
DROP TABLE IF EXISTS "calendar_dates";
CREATE TABLE IF NOT EXISTS "calendar_dates" (
	"service_id"	TEXT NOT NULL,
	"date"	TEXT NOT NULL,
	"exception_type"	INTEGER NOT NULL,
	PRIMARY KEY("service_id","date")
);
DROP TABLE IF EXISTS "routes";
CREATE TABLE IF NOT EXISTS "routes" (
	"route_id"	TEXT NOT NULL,
	"route_short_name"	TEXT NOT NULL,
	"route_long_name"	TEXT NOT NULL,
	"route_type"	INTEGER NOT NULL,
	PRIMARY KEY("route_id")
);
DROP TABLE IF EXISTS "stops";
CREATE TABLE IF NOT EXISTS "stops" (
	"stop_id"	TEXT NOT NULL,
	"stop_name"	TEXT NOT NULL,
	"stop_lat"	REAL NOT NULL,
	"stop_lon"	REAL NOT NULL,
	"wheelchair_boarding"	INTEGER,
	PRIMARY KEY("stop_id")
);
DROP TABLE IF EXISTS "trips";
CREATE TABLE IF NOT EXISTS "trips" (
	"trip_id"	TEXT NOT NULL,
	"route_id"	TEXT NOT NULL,
	"service_id"	TEXT NOT NULL,
	"direction_id"	INTEGER,
	"wheelchair_accessible"	INTEGER,
	"bikes_allowed"	INTEGER,
	PRIMARY KEY("trip_id")
);
DROP INDEX IF EXISTS "stop_times_stop_id";
CREATE INDEX IF NOT EXISTS "stop_times_stop_id" ON "stop_times" (
	"stop_id"
);
DROP INDEX IF EXISTS "stop_times_trip_id";
CREATE INDEX IF NOT EXISTS "stop_times_trip_id" ON "stop_times" (
	"trip_id"
);
DROP INDEX IF EXISTS "trips_service_id";
CREATE INDEX IF NOT EXISTS "trips_service_id" ON "trips" (
	"service_id"
);
DROP INDEX IF EXISTS "trips_route_id";
CREATE INDEX IF NOT EXISTS "trips_route_id" ON "trips" (
	"route_id"
);
DROP INDEX IF EXISTS "routes_route_long_name";
CREATE INDEX IF NOT EXISTS "routes_route_long_name" ON "routes" (
	"route_long_name"
);
COMMIT;
