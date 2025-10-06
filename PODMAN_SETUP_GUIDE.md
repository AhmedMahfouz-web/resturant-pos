# Sugar POS Podman Setup Guide

## Why Podman?

Podman is a daemonless container engine that's fully compatible with Docker commands but doesn't require root privileges and avoids GPU conflicts. Perfect for your setup!

## Prerequisites

1. **Verify Podman Installation:**

    ```bash
    podman --version
    podman-compose --version
    ```

2. **If podman-compose is not installed:**
    ```bash
    pip3 install podman-compose
    ```

## Quick Start

### Option 1: Using Podman Compose (Recommended)

```bash
# Navigate to your project directory
cd c:\xampp\htdocs\sugar_pos

# Build and run with Podman
podman-compose -f podman-compose.yaml up --build

# Or run in detached mode
podman-compose -f podman-compose.yaml up -d --build
```

### Option 2: Using Podman with Docker Compose

```bash
# Set alias for docker-compose to use podman
alias docker-compose='podman-compose'

# Then use regular docker-compose commands
docker-compose up --build
```

### Option 3: Manual Podman Commands

```bash
# Create network
podman network create sugar_pos_network

# Run MySQL container
podman run -d \
  --name sugar_pos_db \
  --network sugar_pos_network \
  -e MYSQL_ROOT_PASSWORD=rootpassword \
  -e MYSQL_DATABASE=sugar_pos \
  -e MYSQL_USER=sugar_user \
  -e MYSQL_PASSWORD=sugar_password \
  -p 3306:3306 \
  mysql:8.0

# Build application image
podman build -f Dockerfile.podman -t sugar_pos_app .

# Run application container
podman run -d \
  --name sugar_pos_app \
  --network sugar_pos_network \
  -e DB_HOST=sugar_pos_db \
  -e DB_DATABASE=sugar_pos \
  -e DB_USERNAME=root \
  -e DB_PASSWORD=rootpassword \
  -p 8000:8000 \
  -v .:/var/www:Z \
  sugar_pos_app
```

## Configuration Files

### Podman-Specific Files Created:

-   `podman-compose.yaml` - Podman-optimized compose file
-   `Dockerfile.podman` - Rootless Dockerfile for Podman
-   `.env.docker` - Environment configuration (works with Podman)

### Key Podman Optimizations:

-   **Rootless operation**: Runs without root privileges
-   **SELinux compatibility**: `:Z` volume flags for proper labeling
-   **Named volumes**: Better performance for vendor dependencies
-   **Restart policies**: Automatic container restart

## Useful Podman Commands

### Container Management:

```bash
# List running containers
podman ps

# List all containers
podman ps -a

# Stop containers
podman-compose -f podman-compose.yaml down

# View logs
podman logs sugar_pos_app
podman logs sugar_pos_db

# Access application container
podman exec -it sugar_pos_app bash

# Access database
podman exec -it sugar_pos_db mysql -u root -p sugar_pos
```

### Image Management:

```bash
# List images
podman images

# Remove unused images
podman image prune

# Rebuild application
podman-compose -f podman-compose.yaml build --no-cache app
```

### Volume Management:

```bash
# List volumes
podman volume ls

# Remove unused volumes
podman volume prune
```

## Environment Configuration

Your application will use these environment variables:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=sugar_pos
DB_USERNAME=root
DB_PASSWORD=rootpassword
```

## Accessing Your Application

Once running:

-   **Application**: http://localhost:8000
-   **Database**: localhost:3306 (from host machine)

## Troubleshooting

### Common Issues:

1. **Permission Denied Errors:**

    ```bash
    # Fix SELinux context
    sudo setsebool -P container_manage_cgroup true
    ```

2. **Port Already in Use:**

    ```bash
    # Check what's using the port
    netstat -tulpn | grep :8000
    # Kill the process or change port in podman-compose.yaml
    ```

3. **Database Connection Issues:**

    ```bash
    # Check if database is ready
    podman exec sugar_pos_db mysqladmin ping -h localhost -u root -p
    ```

4. **Container Won't Start:**
    ```bash
    # Check logs
    podman logs sugar_pos_app
    podman logs sugar_pos_db
    ```

### Reset Everything:

```bash
# Stop and remove all containers
podman-compose -f podman-compose.yaml down -v

# Remove images
podman rmi sugar_pos_app mysql:8.0

# Rebuild from scratch
podman-compose -f podman-compose.yaml up --build
```

## Development Workflow

### Making Code Changes:

1. Edit your PHP files
2. Changes are automatically reflected (volume mounted)
3. For dependency changes: `podman-compose restart app`

### Database Operations:

```bash
# Run migrations
podman exec sugar_pos_app php artisan migrate

# Seed database
podman exec sugar_pos_app php artisan db:seed

# Fresh migration with seeding
podman exec sugar_pos_app php artisan migrate:fresh --seed
```

### Laravel Commands:

```bash
# Clear cache
podman exec sugar_pos_app php artisan cache:clear

# Generate key
podman exec sugar_pos_app php artisan key:generate

# Run tests
podman exec sugar_pos_app php artisan test
```

## Production Considerations

For production deployment:

1. Use `Dockerfile.podman` (already rootless)
2. Set `APP_ENV=production` in environment
3. Use proper secrets management
4. Configure reverse proxy (nginx/apache)
5. Set up SSL certificates
6. Use persistent volumes for data

## Advantages of Podman over Docker

-   ✅ No daemon required
-   ✅ Rootless operation (better security)
-   ✅ No GPU conflicts
-   ✅ Compatible with Docker commands
-   ✅ Better integration with systemd
-   ✅ Pod support (multiple containers)

Your Sugar POS application is now ready to run with Podman!
