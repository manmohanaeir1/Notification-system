# Docker Setup Guide

This guide covers the complete Docker setup for the Notification System.

## Table of Contents

1. [Architecture](#architecture)
2. [Prerequisites](#prerequisites)
3. [Quick Start](#quick-start)
4. [Services](#services)
5. [Configuration](#configuration)
6. [Common Tasks](#common-tasks)
7. [Troubleshooting](#troubleshooting)

---

## Architecture

The system uses Docker Compose to orchestrate 5 services:

```
┌─────────────┐         ┌──────────────┐         ┌──────────────┐
│   Laravel   │         │   Queue      │         │   Database   │
│   API :8000 │         │   Worker     │         │  MySQL :3306 │
└──────┬──────┘         └──────┬───────┘         └──────────────┘
       │                       │                       ▲
       └───────────┬───────────┴───────────────────────┘
                   │
         ┌─────────┴─────────┐
         │                   │
    ┌────▼────┐         ┌────▼─────────┐
    │  Redis  │         │  RabbitMQ    │
    │ :6379   │         │ :5672 :15672 │
    └─────────┘         └──────────────┘
```

---

## Prerequisites

### System Requirements

- **Docker:** v20.10 or higher
- **Docker Compose:** v1.29 or higher
- **Memory:** 4GB minimum (2GB recommended per container)
- **Disk:** 5GB free space
- **OS:** Linux, macOS, or Windows with WSL2

### Check Installation

```bash
docker --version
docker-compose --version
```

### Installation

#### Ubuntu/Debian
```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

#### macOS
```bash
brew install docker docker-compose
# Or use Docker Desktop: https://www.docker.com/products/docker-desktop
```

#### Windows
- Install Docker Desktop: https://www.docker.com/products/docker-desktop
- Enable WSL2 backend
- Restart Docker Desktop

---

## Quick Start

### 1. Initialize Project

```bash
cd /path/to/notification-system

# Copy environment file
cp .env.example .env

# Ensure Docker is running
docker --version
docker-compose --version
```

### 2. Start Services

```bash
# Build and start all services in background
docker-compose up -d

# Watch startup progress
docker-compose logs -f
```

### 3. Setup Database

```bash
# Run migrations
docker-compose exec app php artisan migrate

# (Optional) Seed test data
docker-compose exec app php artisan db:seed
```

### 4. Verify Services

```bash
# Check all services running
docker-compose ps

# Test API endpoint
curl http://localhost:8000/api/v1/notifications/summary

# Test Redis connection
docker-compose exec redis redis-cli ping

# Test RabbitMQ connection
docker-compose exec rabbitmq rabbitmqctl status
```

### 5. View APIs in Action

```bash
# Create a test notification
curl -X POST http://localhost:8000/api/v1/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "test-user",
    "type": "welcome",
    "message": "Welcome to the notification system!",
    "channel": "database"
  }'

# View queue worker processing logs
docker-compose logs -f queue-worker
```

---

## Services

### Main Application (`app`)

**Purpose:** Runs Laravel API server

```yaml
Port: 8000
Hostname: app
Working Dir: /app
```

**Commands:**
```bash
# Access shell
docker-compose exec app bash

# Run Artisan commands
docker-compose exec app php artisan tinker
docker-compose exec app php artisan cache:clear

# Run tests
docker-compose exec app php artisan test
```

### Queue Worker (`queue-worker`)

**Purpose:** Processes notifications from queue

```yaml
Service: queue-worker
Process: php artisan queue:work --queue=notifications --tries=3
```

**Environment:**
- Connects to Redis/RabbitMQ
- Updates database on success/failure
- Logs processing to `/app/storage/logs/`

**Commands:**
```bash
# View worker logs
docker-compose logs queue-worker

# Restart worker (if stuck)
docker-compose restart queue-worker

# View specific error count
docker-compose logs queue-worker | grep -i error
```

### MySQL Database (`mysql`)

**Purpose:** Stores notifications and system data

```yaml
Port: 3306
Root Password: root_secret
Username: notifications_user
Password: secret
Database: notifications_db
```

**Commands:**
```bash
# Access MySQL CLI
docker-compose exec mysql mysql -u notifications_user -p
# Enter password: secret

# Run SQL query
docker-compose exec mysql mysql -u notifications_user -pn=notifications_db -e "SELECT COUNT(*) FROM notifications;"

# Backup database
docker-compose exec mysql mysqldump -u notifications_user -p notifications_db > backup.sql

# Restore database
docker-compose exec -T mysql mysql -u notifications_user -p notifications_db < backup.sql
```

### Redis Cache (`redis`)

**Purpose:** Message queue and caching

```yaml
Port: 6379
DB: 0 (notifications), 1 (cache)
```

**Commands:**
```bash
# Access Redis CLI
docker-compose exec redis redis-cli

# Within Redis CLI:
redis-cli> KEYS *                    # List all keys
redis-cli> GET notification:summary  # Get cached summary
redis-cli> FLUSHALL                  # Clear all cache
redis-cli> INFO stats                # View statistics
```

### RabbitMQ Message Broker (`rabbitmq`)

**Purpose:** Alternative message queue (optional)

```yaml
AMQP Port: 5672
Management UI: 15672
Default User: guest
Default Password: guest
```

**Access:**
- Management UI: http://localhost:15672
- Credentials: guest/guest

**Commands:**
```bash
# List queues
docker-compose exec rabbitmq rabbitmqctl list_queues

# View connections
docker-compose exec rabbitmq rabbitmqctl list_connections

# Reset RabbitMQ
docker-compose exec rabbitmq rabbitmqctl reset
```

---

## Configuration

### Environment Variables

Edit `.env` file to customize:

```env
# Application
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=notifications_db
DB_USERNAME=notifications_user
DB_PASSWORD=secret

# Queue Driver
QUEUE_CONNECTION=redis    # or rabbitmq

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
```

### Rebuild After Config Changes

```bash
# Rebuild containers
docker-compose up -d --build

# Verify changes
docker-compose ps
```

### Switch Queue Driver

**Option 1: Redis (Default)**
```env
QUEUE_CONNECTION=redis
```

**Option 2: RabbitMQ**
```env
QUEUE_CONNECTION=rabbitmq
```

**Option 3: Database (Development Only)**
```env
QUEUE_CONNECTION=database
```

Then restart:
```bash
docker-compose restart
```

---

## Common Tasks

### Starting Services

```bash
# Start all services (background)
docker-compose up -d

# Start and follow logs
docker-compose up

# Start specific service
docker-compose up -d mysql redis
```

### Stopping Services

```bash
# Stop all services (keep volumes)
docker-compose stop

# Stop and remove containers
docker-compose down

# Stop and remove everything (including volumes)
docker-compose down -v
```

### Viewing Logs

```bash
# All services
docker-compose logs

# Specific service (last 50 lines)
docker-compose logs -f --tail=50 app

# Service-specific
docker-compose logs queue-worker
docker-compose logs mysql
docker-compose logs redis
docker-compose logs rabbitmq

# Filter logs
docker-compose logs | grep -i error
docker-compose logs | grep "notification"
```

### Running Commands

```bash
# Laravel Artisan commands
docker-compose exec app php artisan migrate
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan queue:failed  # View failed jobs

# Database commands
docker-compose exec mysql mysql -u notifications_user -p notifications_db

# Redis commands
docker-compose exec redis redis-cli

# Bash shell access
docker-compose exec app bash
docker-compose exec mysql bash
```

### Database Operations

```bash
# Migrations
docker-compose exec app php artisan migrate
docker-compose exec app php artisan migrate:rollback
docker-compose exec app php artisan migrate:fresh  # ⚠️ Warning: Clears DB

# Seeding
docker-compose exec app php artisan db:seed

# Reset everything
docker-compose exec app php artisan migrate:fresh --seed
```

### Testing

```bash
# Run all tests
docker-compose exec app php artisan test

# Run specific test file
docker-compose exec app php artisan test tests/Feature/NotificationApiTest.php

# Run with coverage report
docker-compose exec app php artisan test --coverage

# Run specific test method
docker-compose exec app php artisan test --filter=test_can_create_notification
```

### Performance Monitoring

```bash
# View resource usage
docker stats

# View process list
docker-compose top mysql
docker-compose top queue-worker

# View network
docker network ls
docker network inspect notification-system_notification-network
```

---

## Troubleshooting

### Services Won't Start

**Error:** Port already in use

```bash
# Find service using port
lsof -i :8000
lsof -i :6379
lsof -i :5672

# Change port in docker-compose.yml
# Or stop the conflicting service
sudo fuser -k 8000/tcp
```

**Error:** Out of memory

```bash
# Reduce container resources in docker-compose.yml
services:
  app:
    deploy:
      resources:
        limits:
          memory: 512M
```

### Database Connection Fails

```bash
# Verify MySQL is running
docker-compose ps mysql

# Check database logs
docker-compose logs mysql

# Test connection
docker-compose exec mysql mysql -u notifications_user -p -e "SELECT 1;"

# Recreate database
docker-compose exec app php artisan migrate:fresh
```

### Queue Worker Not Processing

**Check 1:** Is Redis/RabbitMQ running?
```bash
docker-compose ps redis
docker-compose ps rabbitmq
```

**Check 2:** View worker logs
```bash
docker-compose logs -f queue-worker
```

**Check 3:** Check failed jobs
```bash
docker-compose exec app php artisan queue:failed
```

**Check 4:** Restart worker
```bash
docker-compose restart queue-worker
```

### High Memory Usage

```bash
# View memory usage
docker stats

# Prune unused images/containers
docker system prune -a

# Rebuild containers
docker-compose up -d --build
```

### Permission Denied

```bash
# Fix file permissions
docker-compose exec app chown -R www-data:www-data /app/storage
docker-compose exec app chmod -R 775 /app/storage
```

### Redis Connection Refused

```bash
# Verify Redis is running
docker-compose logs redis

# Check Redis status
docker-compose exec redis redis-cli ping

# Restart Redis
docker-compose restart redis
```

---

## Production Deployment

### Before Production

1. **Change passwords:**
   ```bash
   # Update .env with strong passwords
   DB_PASSWORD=strong_password_here
   REDIS_PASSWORD=strong_password_here
   RABBITMQ_PASSWORD=strong_password_here
   ```

2. **Disable debug mode:**
   ```bash
   APP_DEBUG=false
   APP_ENV=production
   ```

3. **Setup backups:**
   ```bash
   # Backup volumes
   docker-compose exec mysql mysqldump -u root -p > backup.sql
   ```

4. **Monitor logs:**
   ```bash
   # Centralize logs (use ELK, Datadog, etc.)
   docker-compose logs --follow --tail=100
   ```

### Scaling Queue Workers

```bash
# Run multiple queue worker instances
docker-compose up -d --scale queue-worker=3

# Monitor all workers
docker-compose logs -f queue-worker
```

---

## Cleanup

```bash
# Remove stopped containers
docker container prune

# Remove unused volumes
docker volume prune

# Remove unused images
docker image prune

# Full cleanup (⚠️ Warning: Removes all stopped containers/images/volumes)
docker system prune -a
```

---

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Reference](https://docs.docker.com/compose/compose-file/)
- [Laravel Docker Guide](https://laravel.com/docs/deployment)
- [Redis Documentation](https://redis.io/documentation)
- [RabbitMQ Documentation](https://www.rabbitmq.com/documentation.html)
