# Auth Microservice

This project is a Symfony-based authentication microservice responsible for handling user management, authorization, and token-based access control for both web and API clients.

## Overview

The service provides a lightweight and modular authentication layer within a microservice ecosystem. It handles user registration, login, role and permission management, and issues JWT tokens for API authentication.

This service is part of a broader distributed system where other microservices rely on it for user verification and permission validation.

## Local Development

The development environment is containerized with **Docker Compose** and includes the following core services:

- **PHP-FPM** — runs the Symfony application
- **MySQL** — primary relational database
- **Redis** — used for caching and internal metrics storage
- **Nginx** — reverse proxy and HTTP server

To start the environment:

```bash
    docker compose up --build
```

Before running, create a local environment file based on the example provided:

```bash
    cp .env.local.example .env.local
```

After startup, the application will be available at [http://localhost:8081](http://localhost:8081).

## Technology Stack

- **Symfony Framework 7.x** – core framework providing routing, dependency injection, and configuration.
- **Doctrine ORM 3.x** – handles data persistence and entity mapping.
- **MySQL 8.x** – primary relational database.
- **LexikJWTAuthenticationBundle** – responsible for JWT token creation and validation.
- **PHPStan / PHP CS Fixer / PHPUnit** – used for static analysis, code style enforcement, and testing.
- The project depends on the shared [MSA Infrastructure repository](https://github.com/msa-sandbox/infrastructure), which provides core platform components such as **Kafka**.

## Observability and System Features

- **Prometheus** – is integrated for collecting and exposing metrics, available at the /metrics endpoint.
- **Health checks** – are provided via the /health endpoint to monitor application dependencies.
- **Rate limiting** – is enabled to prevent abuse and brute-force attacks.
- **Monolog** – is used for structured JSON logging (stdout), ready for aggregation through Promtail / Loki.


## Architecture

The project follows a **clean architecture** structure. <br />
While not full DDD, the structure keeps clear separation of concerns and is easy to extend.
