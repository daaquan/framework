CREATE TABLE IF NOT EXISTS users (
     id                 INTEGER PRIMARY KEY AUTOINCREMENT,
     device_id          TEXT,
     name               TEXT NOT NULL,
     email              TEXT NOT NULL,
     email_verified_at  DATETIME,
     password           TEXT NOT NULL,
     birthday           DATE,
     created_at         DATETIME,
     updated_at         DATETIME,
     deleted_at         DATETIME,
     UNIQUE (email)
);
