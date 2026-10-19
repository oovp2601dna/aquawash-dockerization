
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
- App: PHP 8.2 and Apache, accessible at host port 8080, mapped to container port 80.
- Database: MySQL 8.0, accessible internally through the Docker Compose service name db on port 3306.

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

- Stop and remove the application and database containers:
docker compose down

- The named Docker volume mysql_data is retained by default, so database data persists when the containers are stopped and removed normally.
- To also delete the stored database data, run:
docker compose down -v

Warning: The -v option deletes the Compose-managed database volume and its stored data.

## Troubleshooting

- If port 8080 is occupied, change the host port in compose.yaml.
- If the database is unhealthy, inspect its logs.
- The setup.sql initialization script runs automatically
  when MySQL initializes a fresh data volume.
