# WebGuard Scraper Instance

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

> 💡 **System Architecture Note:** This repository contains the **Worker Node**. It requires a running instance of the [WebGuard Core Application](https://github.com/m-breuer/webguard) to function effectively.

This repository contains a WebGuard scraper instance, designed to operate as one of potentially many distributed crawling nodes for the WebGuard monitoring service. Its primary role is to perform the actual data collection for website uptime, response times, and SSL certificate statuses and send them to the core application via a REST API.

This project is a component of the [WebGuard](https://github.com/m-breuer/webguard) project.

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
-   **Redis Client:** Uses `predis/predis` as the Redis client library for PHP. For optimal performance, `phpredis` can be installed as a PHP extension.

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

-   PHP 8.2 or higher
-   Composer
-   Redis server
-   A running instance of the [WebGuard Core](https://github.com/m-breuer/webguard) application.

### Installation

1.  **Clone the repository:**
    ```bash
    git clone [https://github.com/m-breuer/webguard-instance.git](https://github.com/m-breuer/webguard-instance.git)
    cd webguard-instance
    ```
2.  **Install dependencies:**
    ```bash
    composer install
    ```
3.  **Configure Environment:**
    -   Copy `.env.example` to `.env`: `cp .env.example .env`
    -   Set the `WEBGUARD_LOCATION` variable to a unique identifier (e.g., `WEBGUARD_LOCATION="de-1"`). This is essential for the instance to pick up the correct jobs.
    -   Set the `WEBGUARD_CORE_API_URL` and `WEBGUARD_CORE_API_KEY` variables to connect to the core application.
    -   Configure your Redis connection in the `.env` file. Ensure `REDIS_CLIENT` is set to `predis` or `phpredis` if the extension is installed.
    -   Configure your database connection in the `.env` file. The application uses a database to store information about the monitoring jobs.
4.  **Application Setup:**
    ```bash
    php artisan key:generate
    php artisan migrate
    ```
5.  **Start Services:**
    For local development, you need to run both the queue worker and the scheduler.
    ```bash
    # Terminal 1: Queue Worker
    php artisan queue:work --queue=default,monitoring-response,monitoring-ssl

    # Terminal 2: Scheduler
    php artisan schedule:work
    ```

## Deployment

For a production environment, it is crucial to use a queue worker to process queues and a cron job to run the scheduler.

### Queue Worker

To run the queue worker, use the `php artisan queue:work` command. You will need a process manager like Supervisor to keep the `php artisan queue:work` process running.

```bash
php artisan queue:work --queue=default,monitoring-response,monitoring-ssl
```
