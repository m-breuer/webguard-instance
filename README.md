# WebGuard Scraper Instance

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

> 💡 **System Architecture Note:** This repository contains the **Worker Node**. It requires a running instance of the [WebGuard Core Application](https://gitlab.com/m-breuer/webguard) to function effectively.

This repository contains a WebGuard scraper instance, designed to operate as one of potentially many distributed crawling nodes for the WebGuard monitoring service. Its primary role is to perform the actual data collection for website uptime, response times, and SSL certificate statuses and send them to the core application via a REST API.

This project is a component of the [WebGuard](https://gitlab.com/m-breuer/webguard) project.

## Features

-   **Multi-Location Monitoring:** Built to run in different geographical locations by setting a `WEBGUARD_LOCATION` environment variable, allowing for region-specific monitoring.
-   **Distributed Architecture:** Designed as a distributed system, where the core application orchestrates monitoring tasks, and scraper instances execute them.
-   **Asynchronous Processing:** Leverages Laravel's Queue and Scheduler extensively to prevent long-running HTTP requests from blocking the main application thread and enables parallel processing of numerous monitoring checks.
-   **Comprehensive Checks:**
    -   **HTTP Monitoring:** Checks uptime and response time for web pages.
    -   **Keyword Monitoring:** Verifies the presence of a specific keyword on a web page.
    -   **Ping Monitoring:** Checks for open ports (HTTP, HTTPS, FTP).
    -   **Port Monitoring:** Checks if a specific TCP port is open.
    -   **SSL Certificate Monitoring:** Validates SSL certificates and manages incident states. This collected data is consumed by the Core application for display on dashboards, reporting, and triggering user notifications.
-   **API-Driven:** Communicates with the core application via a REST API to send monitoring results.


## Project Structure

The project is structured as a standard Laravel application, with a few key components:

-   `app/Console/Commands`: Contains the Artisan commands that are scheduled to run periodically.
-   `app/Jobs`: Contains the jobs that are dispatched by the Artisan commands to perform the actual monitoring checks.
-   `app/Models`: Contains the Eloquent models that are used to interact with the database.
-   `app/Enums`: Contains the PHP enums that are used to define the different types of monitoring checks, statuses, etc.
-   `routes/console.php`: Contains the scheduling for the Artisan commands.
-   `config/webguard.php`: Contains the configuration for the application.

## Getting Started

### Prerequisites

-   Docker
-   Docker Compose
-   A running instance of the [WebGuard Core](https://gitlab.com/m-breuer/webguard) application.

### Installation

1.  **Clone the repository:**
    ```bash
    git clone https://gitlab.com/m-breuer/webguard-instance.git
    cd webguard-instance
    ```
2.  **Configure Environment:**
    -   Copy `.env.example` to `.env`: `cp .env.example .env`
    -   Set the `WEBGUARD_LOCATION` variable to a unique identifier (e.g., `WEBGUARD_LOCATION="de-1"`). This is essential for the instance to pick up the correct jobs.
    -   Set the `WEBGUARD_CORE_API_URL` and `WEBGUARD_CORE_API_KEY` variables to connect to the core application.
    -   Configure your database connection in the `.env` file. The application uses a database to store information about the monitoring jobs. Ensure `DB_HOST` is set to `mysql`.
5.  **Start Services:**
    For local development, use the provided `start-dev.sh` script:
    ```bash
    ./start-dev.sh
    ```
    This command runs `docker compose -f compose.yml -f docker-compose.override.yml up -d`, which builds the `development` stage of the Dockerfile for the `php` service, mounts your local code, and sets `APP_ENV` to `local` inside the container.
    To stop the services, run:
    ```bash
    docker compose -f compose.yml -f docker-compose.override.yml down
    ```

## Deployment

For production environments, you can build and run the production-ready Docker images using the `start-prod.sh` script.

To build and start the production services:
```bash
./start-prod.sh
```
This command runs `docker compose up -d`, which builds the `production` stage of the Dockerfile for the `php` service.
To stop the services, run:
```bash
docker compose down
```

### Production Environment Tuning

For any production deployment using `compose.yml`, tune queue concurrency via `.env`:

- `QUEUE_DEFAULT_WORKERS` (default: `3`)
- `QUEUE_MONITORING_RESPONSE_WORKERS` (default: `3`)
- `QUEUE_MONITORING_SSL_WORKERS` (default: `2`)

Each value controls how many `php artisan queue:work` processes run in parallel inside the corresponding queue container.

For WebGuard integration and HTTP behavior, adjust these `.env` variables as needed:

- `WEBGUARD_LOCATION`
- `WEBGUARD_CORE_API_KEY`
- `WEBGUARD_CORE_API_URL`
- `WEBGUARD_HTTP_RETRY_TIMES` (default: `1`)
- `WEBGUARD_HTTP_RETRY_DELAY_MS` (default: `250`)

