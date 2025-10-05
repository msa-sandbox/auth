# Auth Microservice

This project is a **Symfony-based authentication microservice** responsible for handling user management, authorization, and token-based access control for both web and API clients.

## Overview

The service provides a lightweight and modular authentication layer within a microservice ecosystem. It handles user registration, login, role and permission management, and issues JWT tokens for API authentication.

This service is part of a broader distributed system where other microservices rely on it for user verification and permission validation.

## Local Development

The environment is containerized using **Docker Compose**. It includes the following core services:

* **PHP-FPM** (application runtime)
* **MySQL** (primary database)
* **Redis** (for caching and message queue usage)
* **Nginx** (reverse proxy and HTTP server)

To start the environment:

```bash
    docker compose up --build
```

The application will be available at `http://localhost:8081`.

## Technology Stack

* **Symfony Framework 7.x** – base framework providing routing, DI, and configuration.
* **Doctrine ORM 3.x** – used for data persistence and entity management.
* **MySQL 8.x** – main relational database.
* **LexikJWTAuthenticationBundle** – JWT token generation and validation.
* **PHPStan / CS Fixer / PHPUnit** – for static analysis, code style, and testing.

## Architecture

The project follows a **clean architecture** structure:

* `Http/` – controllers and request handling.
* `Service/` – business logic and application services.
* `Repository/` – database access layer.
* `Entity/` – Doctrine entities representing data models.
* `Security/` – roles, permissions, and voters.

While not full DDD, the structure keeps clear separation of concerns and is easy to extend.
