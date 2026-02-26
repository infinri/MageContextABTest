# Magento 2 Docker Environment

Dockerized development environment for **Magento 2.4.8-p3** with PHP 8.4, MySQL 8.0, OpenSearch 2.12, Redis 7, RabbitMQ 3, and Mailpit.

## Services

| Service | Container | Ports | Description |
|---------|-----------|-------|-------------|
| **App** | `magento-app` | `80` (HTTP), `9003` (Xdebug) | PHP 8.4-FPM, Nginx, Composer 2, Node.js 18 |
| **MySQL** | `magento-mysql` | `3306` | MySQL 8.0 database |
| **OpenSearch** | `magento-opensearch` | `9200` | OpenSearch 2.12 (catalog search) |
| **Redis** | `magento-redis` | `6379` | Cache, page cache & sessions |
| **RabbitMQ** | `magento-rabbitmq` | `5672`, `15672` (management UI) | Message queue |
| **Mailpit** | `magento-mailpit` | `1025` (SMTP), `8025` (web UI) | Email testing |

## Prerequisites

- Docker & Docker Compose
- `vm.max_map_count >= 262144` (required by OpenSearch)

```bash
# Check current value
sysctl vm.max_map_count

# Set if needed (persists until reboot)
sudo sysctl -w vm.max_map_count=262144
```

## Quick Start

All commands are run from the **project root** (parent of `docker/`).

### 1. Start all containers

```bash
docker compose -f docker/docker-compose.yml up -d
```

### 2. Build / rebuild the app container

```bash
docker compose -f docker/docker-compose.yml up --build -d app
```

### 3. Check container status

```bash
docker compose -f docker/docker-compose.yml ps -a
```

All 6 containers should show `Up` and `healthy` before proceeding.

## Useful Commands

### Stop all containers

```bash
docker compose -f docker/docker-compose.yml down
```

### Stop and remove volumes (destroys data)

```bash
docker compose -f docker/docker-compose.yml down -v
```

### Shell into the app container

```bash
docker compose -f docker/docker-compose.yml exec app bash
```
