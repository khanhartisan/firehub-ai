-- Enable pgvector in the default (application) database
CREATE EXTENSION IF NOT EXISTS vector;

-- Enable pgvector in the testing database (created by Sail's 10-create-testing-database.sql)
\connect testing
CREATE EXTENSION IF NOT EXISTS vector;
