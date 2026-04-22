-- Extensions required by the DWH schema
CREATE EXTENSION IF NOT EXISTS citext;

-- Schemas used by the application
CREATE SCHEMA IF NOT EXISTS marts;
CREATE SCHEMA IF NOT EXISTS reports;
CREATE SCHEMA IF NOT EXISTS raw;
