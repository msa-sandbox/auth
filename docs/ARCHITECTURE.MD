# Architecture Overview

## Purpose

This document describes the architectural foundations of the **Auth Microservice**, outlining its structure, core principles, and design philosophy. The goal is to provide a clear understanding of how components interact and why specific approaches were chosen.

## Design Goals

* **Simplicity & Maintainability** – The service should remain small, clear, and easy to extend.
* **Separation of Concerns** – Business logic, transport layer, and persistence are isolated.
* **Scalability** – Components are independent enough to scale or migrate separately.
* **Consistency** – Common patterns are followed across services for predictable structure.

## High-Level Overview

The service provides authentication and authorization features for a distributed system. It exposes APIs for user management, token generation, and role-based access control.

It is built around a modular, layered architecture inspired by **Clean Architecture** principles:

```
┌───────────────────────────────┐
│           HTTP Layer          │   → Symfony controllers (routing, requests, responses)
├───────────────────────────────┤
│        Application Layer      │  → Services orchestrating business logic
├───────────────────────────────┤
│          Domain Layer         │  → Entities, Value Objects, and Domain Rules
├───────────────────────────────┤
│      Infrastructure Layer     │  → Repositories, Kafka, Redis, and Database adapters
└───────────────────────────────┘
```

## Core Components

### 1. **Http Layer**

* Located in `src/Http/Controller`.
* Handles incoming HTTP requests and returns JSON responses.
* Delegates all logic to application services.

### 2. **Service Layer**

* Located in `src/Service`.
* Contains business logic (e.g., user creation, permission checks).
* Interacts with repositories and security layer.

### 3. **Repository Layer**

* Located in `src/Repository`.
* Implements data access through Doctrine ORM.
* Exposes interfaces for loose coupling and testability.

### 4. **Entity Layer**

* Located in `src/Entity`.
* Contains Doctrine entities mapped to MySQL tables.
* Each entity represents a database model (e.g., `User`).

### 5. **Security Layer**

* Located in `src/Security`.
* Manages roles, permissions, voters, and JWT integration.

### 6. **Infrastructure Layer**

* Adapters for external systems such as MySQL, Redis, and Kafka.
* Handles persistence, caching, and asynchronous communication.

## Integration Points

* **Kafka** – Used for publishing user and permission change events.
* **Redis** – Serves as a cache and message transport for queue-based features.
* **MySQL** – Primary persistent store for user data.
* **JWT** – Provides stateless authentication for API clients.

## Development Principles

1. **Explicit over implicit** – Avoid magic and hidden dependencies.
2. **Composition over inheritance** – Prefer small, self-contained components.
3. **Clean boundaries** – Keep controllers, services, and repositories isolated.
4. **Immutable data** – Entities and DTOs favor immutability where practical.
5. **Single Responsibility** – Each class or module has one clear purpose.

## Future Directions

* Implement caching layer for frequently accessed user data.
* Add event sourcing or outbox pattern for Kafka reliability.
* Extend JWT handling with refresh tokens.
* Introduce monitoring and metrics collection.

---

This document serves as a living reference for the project's structure and architectural intent. It should evolve as the system grows or design decisions change.
