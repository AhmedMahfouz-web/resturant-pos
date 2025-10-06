# Sugar POS Docker Setup Guide

## Prerequisites

Docker is not currently installed on your system. You have two options:

### Option 1: Install Docker (Recommended)

1. **Download Docker Desktop for Windows:**
   - Visit: https://www.docker.com/products/docker-desktop/
   - Download Docker Desktop for Windows
   - Run the installer and follow the setup wizard

2. **After Installation:**
   ```bash
   # Verify Docker is installed
   docker --version
   docker-compose --version
   ```

3. **Run Your Application:**
   ```bash
   # Navigate to your project directory
   cd c:\xampp\htdocs\sugar_pos

   # Build and run the containers
   docker-compose up --build

   # Or run in detached mode
   docker-compose up -d --build
   ```

4. **Access Your Application:**
   - Open your browser and go to: http://localhost:8000
   - Your Laravel POS application will be running

### Option 2: Use XAMPP (Current Setup)

Since you already have XAMPP installed, you can run the application directly:

1. **Start XAMPP Services:**
   - Start Apache and MySQL from XAMPP Control Panel

2. **Configure Database:**
   - Create a database named `sugar_pos` in phpMyAdmin
   - Update your `.env` file with XAMPP MySQL settings:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=sugar_pos
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Install Dependencies and Run:**
   ```bash
   cd c:\xampp\htdocs\sugar_pos
   composer install
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   php artisan serve
   ```

## Docker Configuration Summary

I've updated your Docker configuration with the following improvements:

### Updated Dockerfile:
- Uses PHP 8.3 with FPM
- Installs all required PHP extensions (MySQL, GD, ZIP, etc.)
- Proper Laravel directory structure
- Optimized for production

### Updated docker-compose.yaml:
- Fixed MySQL configuration (was incorrectly using PostgreSQL settings)
- Proper volume mounting
- Health checks for database
- Consistent networking
- Environment variables for database connection

### Created Files:
- `.env.docker` - Docker-specific environment configuration
- `.dockerignore` - Optimizes Docker build process
- `DOCKER_SETUP_GUIDE.md` - This guide

## Useful Docker Commands

Once Docker is installed:

```bash
# Build and start containers
docker-compose up --build

# Start containers in background
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs app
docker-compose logs db

# Access application container
docker-compose exec app bash

# Access database
docker-compose exec db mysql -u root -p sugar_pos

# Rebuild containers
docker-compose down
docker-compose up --build
```

## Troubleshooting

### Common Issues:
1. **Port 8000 already in use:** Stop other services or change port in docker-compose.yaml
2. **Database connection failed:** Ensure MySQL container is healthy
3. **Permission issues:** Run `docker-compose down` and `docker-compose up --build`

### Database Issues:
```bash
# Reset database
docker-compose exec app php artisan migrate:fresh --seed
```

## Next Steps

1. Install Docker Desktop if you want to use containerization
2. Or continue using XAMPP with the provided XAMPP setup instructions
3. Both approaches will get your Sugar POS application running successfully
