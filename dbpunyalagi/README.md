
# AquaWash Laundry Management System

## Project Overview
AquaWash is a PHP-based laundry management system
using MySQL for data storage. The application is
containerized with Docker to simplify setup and execution.

## Technologies
- PHP 8.2
- Apache
- MySQL 8.0
- Docker
- Docker Compose

## Prerequisites
- Docker Desktop
- Docker Compose

## How to Run

1. Clone or download this project.
2. Open a terminal in the project directory.
3. Build and start the containers:

   ```bash
   docker compose up --build -d
   ```

4. Open the application:

   http://localhost:8080

## Services
- App: PHP and Apache, accessible on port 8080.
- Database: MySQL 8.0, accessible internally to the app.

## Verification

Check container status:

```bash
docker compose ps
```

Check application and database logs:

```bash
docker compose logs --tail=50
```

Check database tables:

```bash
docker compose exec db mysql -uroot -plocal_dev_password -e "SHOW TABLES IN laundry_db;"
```

## Stop the Application

```bash
docker compose down
```

The MySQL data is stored in a Docker named volume
to persist across normal container restarts.

## Troubleshooting

- If port 8080 is occupied, change the host port in compose.yaml.
- If the database is unhealthy, inspect its logs.
- The setup.sql initialization script runs automatically
  when MySQL initializes a fresh data volume.
