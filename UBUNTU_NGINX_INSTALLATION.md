# Ubuntu & Nginx Production Installation Guide

This guide provides step-by-step instructions for deploying this **Laravel AI Agent & MCP Platform** on an **Ubuntu Linux Server (22.04 LTS or 24.04 LTS)** using **Nginx**, **PHP 8.3-FPM**, **SQLite** (or MySQL), **Node.js/Vite**, and **Python 3 / Docker** for built-in tool execution.

---

## Table of Contents
1. [Architecture & System Requirements](#1-architecture--system-requirements)
2. [Step 1: System Update & Prerequisites](#step-1-system-update--prerequisites)
3. [Step 2: Install PHP 8.3 & Required Extensions](#step-2-install-php-83--required-extensions)
4. [Step 3: Install Nginx Web Server](#step-3-install-nginx-web-server)
5. [Step 4: Install Composer & Node.js](#step-4-install-composer--nodejs)
6. [Step 5: Setup Python 3 or Docker (For Tool Execution)](#step-5-setup-python-3-or-docker-for-tool-execution)
7. [Step 6: Deploy Application Code](#step-6-deploy-application-code)
8. [Step 7: Configure Environment (.env)](#step-7-configure-environment-env)
9. [Step 8: Build Frontend Assets with Vite](#step-8-build-frontend-assets-with-vite)
10. [Step 9: Database Setup & Migrations](#step-9-database-setup--migrations)
11. [Step 10: Configure File Ownership & Permissions](#step-10-configure-file-ownership--permissions)
12. [Step 11: Configure Nginx (with SSE Streaming Support)](#step-11-configure-nginx-with-sse-streaming-support)
13. [Step 12: Configure Queue Worker (Systemd Service)](#step-12-configure-queue-worker-systemd-service)
14. [Step 13: Setup Cron Scheduler](#step-13-setup-cron-scheduler)
15. [Step 14: Secure with SSL (Let's Encrypt / Certbot)](#step-14-secure-with-ssl-lets-encrypt--certbot)
16. [Step 15: Firewall Setup (UFW)](#step-15-firewall-setup-ufw)
17. [Step 16: Production Cache Optimization](#step-16-production-cache-optimization)
18. [Troubleshooting & Maintenance](#troubleshooting--maintenance)

---

## 1. Architecture & System Requirements

| Component | Requirement | Details |
| :--- | :--- | :--- |
| **Operating System** | Ubuntu 22.04 LTS or 24.04 LTS | 64-bit x86_64 or ARM64 |
| **Hardware** | Min 1 CPU core, 1 GB RAM (2 GB+ recommended) | Vite asset build requires ~1 GB RAM or swap |
| **Web Server** | Nginx | HTTP reverse proxy with SSE streaming support |
| **PHP** | PHP 8.3+ | `php8.3-fpm` and CLI |
| **Database** | SQLite 3 (default) or MySQL 8.0+ / MariaDB / PostgreSQL | Default configuration uses local SQLite |
| **Package Managers** | Composer 2.x & Node.js 20+ / npm | Required for backend and Vite frontend build |
| **Python** | Python 3.10+ (or Docker) | Required by `BuiltInToolsService` for executing scripts |

---

## Step 1: System Update & Prerequisites

Connect to your Ubuntu server via SSH and update package repositories:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip zip software-properties-common ca-certificates gnupg ufw lsb-release
```

*(Optional but recommended)* If your server has 1GB of RAM, configure a 2GB swap space to prevent out-of-memory errors during dependency installations:

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## Step 2: Install PHP 8.3 & Required Extensions

Laravel 13 requires **PHP 8.3 or higher**.

### On Ubuntu 24.04 LTS (Noble Numbat)
PHP 8.3 is available in default Ubuntu repositories:

```bash
sudo apt install -y php8.3-fpm php8.3-cli php8.3-common php8.3-curl php8.3-mbstring \
    php8.3-xml php8.3-zip php8.3-bcmath php8.3-sqlite3 php8.3-intl php8.3-readline
```

### On Ubuntu 22.04 LTS (Jammy Jellyfish)
Add the Ondřej Surý PPA to get PHP 8.3:

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-common php8.3-curl php8.3-mbstring \
    php8.3-xml php8.3-zip php8.3-bcmath php8.3-sqlite3 php8.3-intl php8.3-readline
```

*(Optional)* If you plan to use MySQL or PostgreSQL instead of SQLite, also install:
```bash
sudo apt install -y php8.3-mysql php8.3-pgsql
```

### Tune PHP-FPM Configuration
Open `/etc/php/8.3/fpm/php.ini`:

```bash
sudo nano /etc/php/8.3/fpm/php.ini
```

Ensure the following values are adjusted for AI workloads and file handling:

```ini
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 256M
max_execution_time = 300
date.timezone = UTC
```

Restart and enable the PHP 8.3-FPM service:

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl enable php8.3-fpm
```

---

## Step 3: Install Nginx Web Server

Install Nginx:

```bash
sudo apt install -y nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

Check that Nginx is running:

```bash
sudo systemctl status nginx --no-pager
```

---

## Step 4: Install Composer & Node.js

### 1. Install Composer 2 (PHP Dependency Manager)
Download and install Composer globally:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
composer --version
```

### 2. Install Node.js 20.x or 22.x LTS & npm
The application uses **Vite 8** with Tailwind CSS and Bootstrap. Install NodeSource LTS:

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

---

## Step 5: Setup Python 3 or Docker (For Tool Execution)

The application provides a built-in tool (`BuiltInToolsService`) that can run Python calculations and data processing.

### Option A: Local Python 3 (Simplest)
Install Python 3 on the host:

```bash
sudo apt install -y python3 python3-pip
python3 --version
```

### Option B: Docker Sandbox (Most Secure for Untrusted Code)
If you install Docker, `BuiltInToolsService` automatically detects `docker` and runs code in an isolated container without network access:

```bash
sudo apt install -y docker.io
sudo systemctl enable --now docker
# Add www-data user to the docker group so PHP-FPM can invoke it
sudo usermod -aG docker www-data
# Pre-pull the lightweight python image
sudo docker pull python:3.10-slim
```

---

## Step 6: Deploy Application Code

Standard production directory: `/var/www/agent`

### 1. Clone the Repository
```bash
sudo mkdir -p /var/www/agent
sudo chown -R $USER:$USER /var/www/agent

# Clone your repository (or copy your files)
git clone <YOUR_GIT_REPOSITORY_URL> /var/www/agent
cd /var/www/agent
```

*(If copying files manually instead of git, ensure all files including `.env.example` and hidden files are transferred to `/var/www/agent`)*.

---

## Step 7: Configure Environment (.env)

1. Create the `.env` file from `.env.example`:

```bash
cd /var/www/agent
cp .env.example .env
```

2. Install Composer dependencies without dev packages:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

3. Generate the application encryption key:

```bash
php artisan key:generate
```

4. Edit `.env` for production:

```bash
nano .env
```

Configure the following essential settings:

```ini
APP_NAME="AI Agent Platform"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error

# SQLite Database (Default)
DB_CONNECTION=sqlite

# Sessions, Cache, Queue (Database driver uses migrations already in repo)
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Ensure URL is set properly for MCP local endpoint resolution
```

> **Note on MySQL / MariaDB (Optional):**
> If you prefer MySQL over SQLite:
> ```ini
> DB_CONNECTION=mysql
> DB_HOST=127.0.0.1
> DB_PORT=3306
> DB_DATABASE=agent_db
> DB_USERNAME=agent_user
> DB_PASSWORD=your_secure_password
> ```

---

## Step 8: Build Frontend Assets with Vite

Compile the production CSS and JavaScript bundle:

```bash
cd /var/www/agent
npm ci
npm run build
```

Verify that the build output directory `public/build/assets/` contains compiled `.css` and `.js` files.

---

## Step 9: Database Setup & Migrations

### 1. SQLite Database Initialization
If using SQLite (the default), create the database file:

```bash
cd /var/www/agent
touch database/database.sqlite
```

### 2. Run Database Migrations
Run all database tables creation:

```bash
php artisan migrate --force
```

### 3. Seed Default Records
Seed initial records (Default Master Prompt, IT Support Skill, HR Policy Skill, Built-in MCP Server, and OpenAI Provider template):

```bash
php artisan db:seed --force
```

### 4. Create or Promote an Administrator Account
Run the custom console command to create your admin login:

```bash
php artisan admin:create
```

You will be prompted for:
- **Full Name**: e.g., `Super Administrator`
- **Email**: e.g., `admin@your-domain.com`
- **Password**: Min 8 characters

*(You can also run non-interactively: `php artisan admin:create --name="Admin User" --email="admin@example.com" --password="StrongPassword123" --no-interaction`)*

---

## Step 10: Configure File Ownership & Permissions

Web servers run as user `www-data`. Strict permissions are vital for security while allowing Laravel to write caches, sessions, logs, and SQLite data.

```bash
cd /var/www/agent

# Set base ownership to www-data
sudo chown -R www-data:www-data /var/www/agent

# Set directory permissions to 755 and file permissions to 644
sudo find /var/www/agent -type d -exec chmod 755 {} \;
sudo find /var/www/agent -type f -exec chmod 644 {} \;

# Allow write access to storage, cache, and temp script execution directory
sudo chmod -R 775 /var/www/agent/storage
sudo chmod -R 775 /var/www/agent/bootstrap/cache

# Create temp_scripts directory if not present and make it writable
sudo mkdir -p /var/www/agent/storage/app/temp_scripts
sudo chmod -R 775 /var/www/agent/storage/app/temp_scripts

# CRITICAL for SQLite: The directory AND the database file must both be writable
# because SQLite creates temporary write-ahead log (-wal) and shared-memory (-shm) files
sudo chmod 775 /var/www/agent/database
if [ -f /var/www/agent/database/database.sqlite ]; then
    sudo chmod 664 /var/www/agent/database/database.sqlite
fi
```

---

## Step 11: Configure Nginx (with SSE Streaming Support)

This application uses **Server-Sent Events (SSE)** at `/chat/{session}/stream` to stream AI responses in real time. **Nginx FastCGI buffering must be disabled for streaming endpoints**, and timeouts must be set high enough to accommodate LLM reasoning time.

1. Create a new Nginx virtual host configuration:

```bash
sudo nano /etc/nginx/sites-available/agent.conf
```

2. Paste the following production configuration (replace `your-domain.com` with your actual domain or server IP):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /var/www/agent/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;
    charset utf-8;

    # Client body and file upload limits
    client_max_body_size 64M;

    # Primary Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Health check endpoint
    location = /up {
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    # Static assets caching (Vite build)
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, no-transform";
        access_log off;
    }

    # Disable logging for favicon and robots
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # PHP-FPM processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Server-Sent Events (SSE) & LLM Streaming Optimization
        # Disables proxy buffering so chunks are delivered immediately to the browser
        fastcgi_buffering off;
        fastcgi_cache off;
        fastcgi_read_timeout 300s;
        fastcgi_send_timeout 300s;
    }

    # Security: Deny access to hidden files and directories (.env, .git, etc.)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

3. Enable the site and remove the default Nginx site:

```bash
sudo ln -s /etc/nginx/sites-available/agent.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
```

4. Test Nginx syntax and reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## Step 12: Configure Queue Worker (Systemd Service)

In `.env`, the queue driver is set to `QUEUE_CONNECTION=database`. To process background jobs (token usage aggregation, notifications, asynchronous MCP sync), configure a systemd service.

1. Create the systemd service file:

```bash
sudo nano /etc/systemd/system/agent-worker.service
```

2. Add the following service definition:

```ini
[Unit]
Description=Laravel Agent Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/agent/artisan queue:work --sleep=3 --tries=3 --max-time=3600
RestartSec=5s

[Install]
WantedBy=multi-user.target
```

3. Enable and start the service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now agent-worker.service
sudo systemctl status agent-worker.service --no-pager
```

---

## Step 13: Setup Cron Scheduler

Laravel features an automated task scheduler (`php artisan schedule:run`). Setup a cron job for the `www-data` user:

```bash
sudo crontab -u www-data -e
```

Add the following line at the bottom:

```cron
* * * * * cd /var/www/agent && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

---

## Step 14: Secure with SSL (Let's Encrypt / Certbot)

Install Certbot for Nginx to obtain a free SSL certificate:

```bash
sudo apt install -y certbot python3-certbot-nginx
```

Run Certbot to configure HTTPS:

```bash
sudo certbot --nginx -d your-domain.com
```

Certbot will automatically verify your domain, configure SSL directives in `/etc/nginx/sites-available/agent.conf`, and schedule automatic certificate renewals.

Test renewal with:

```bash
sudo certbot renew --dry-run
```

---

## Step 15: Firewall Setup (UFW)

Secure the server using Ubuntu's Uncomplicated Firewall (UFW):

```bash
# Allow SSH connections
sudo ufw allow OpenSSH

# Allow HTTP and HTTPS for Nginx
sudo ufw allow 'Nginx Full'

# Enable the firewall
sudo ufw enable
sudo ufw status
```

---

## Step 16: Production Cache Optimization

Once everything is installed and verified, cache the Laravel framework bootstrap files for maximum performance:

```bash
cd /var/www/agent

# Cache configuration, routes, and blade views
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> **Warning:** Whenever you modify `.env` or files in `routes/` or `config/`, you must re-run:
> ```bash
> php artisan config:clear
> php artisan config:cache
> ```

---

## Troubleshooting & Maintenance

### 1. Check Application Health
Laravel provides an internal health check endpoint:
```bash
curl -I http://localhost/up
# Expect: HTTP/1.1 200 OK
```

### 2. Log Files Location
- **Laravel Application Logs**:
  ```bash
  tail -f /var/www/agent/storage/logs/laravel.log
  ```
- **Nginx Access & Error Logs**:
  ```bash
  sudo tail -f /var/log/nginx/error.log
  sudo tail -f /var/log/nginx/access.log
  ```
- **PHP 8.3 FPM Logs**:
  ```bash
  sudo tail -f /var/log/php8.3-fpm.log
  ```
- **Queue Worker Logs**:
  ```bash
  sudo journalctl -u agent-worker.service -f
  ```

### 3. Common Issues & Solutions

#### "502 Bad Gateway"
- Verify PHP 8.3 FPM socket is running:
  ```bash
  sudo systemctl status php8.3-fpm
  ls -l /run/php/php8.3-fpm.sock
  ```
- Confirm the path in Nginx matches `unix:/run/php/php8.3-fpm.sock`.

#### "database.sqlite: attempt to write a readonly database"
- In SQLite, the containing folder `database/` **and** the database file `database.sqlite` must be writable by `www-data`:
  ```bash
  sudo chown -R www-data:www-data /var/www/agent/database
  sudo chmod 775 /var/www/agent/database
  sudo chmod 664 /var/www/agent/database/database.sqlite
  ```

#### AI Chat Streaming appears all at once instead of word-by-word
- Verify `fastcgi_buffering off;` is present in your Nginx `.php` block.
- Verify `fastcgi_read_timeout 300s;` is set.
- If using Cloudflare or a reverse proxy in front of Nginx, disable response buffering in the proxy settings.

#### Updating the Application in the Future
To deploy code updates in production:

```bash
cd /var/www/agent
git pull origin main

# Update dependencies if needed
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci && npm run build

# Run any new migrations
php artisan migrate --force

# Refresh caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart background workers
sudo systemctl restart agent-worker.service
sudo systemctl restart php8.3-fpm
```

---

## Congratulations!
Your **Laravel AI Agent & MCP Platform** is now securely installed and running on Ubuntu with Nginx!
You can access your admin panel at `https://your-domain.com/admin` using the admin credentials created in Step 9.
