# DUDHO Laravel backend — Docker Engine in WSL2 Ubuntu (no Docker Desktop)

This is a **development-only** starter for the DUDHO React Native + Laravel project. It does not include application business logic or an OTP provider integration. Use it alongside `DUDHO_Master_UI_UX_Development_Blueprint_v3.docx`.

## 1. Prerequisites

In Windows PowerShell (Administrator), confirm WSL2 Ubuntu:

```powershell
wsl --list --verbose
wsl --update
```

In Ubuntu, enable systemd if not already enabled:

```bash
sudo nano /etc/wsl.conf
# Add:
# [boot]
# systemd=true
```

After modifying this file, from Windows PowerShell run `wsl --shutdown` and reopen Ubuntu.

Install **Docker Engine and Compose plugin inside Ubuntu**, following Docker's official Ubuntu instructions: https://docs.docker.com/engine/install/ubuntu/ . Do not install Docker Desktop. Remove conflicting Ubuntu docker.io/containerd packages if the official instructions require it. Confirm:

```bash
sudo systemctl enable --now docker
sudo docker version
sudo docker compose version
```

Optional non-root access (note: membership in the docker group grants root-equivalent privileges):

```bash
sudo usermod -aG docker "$USER"
newgrp docker
```

## 2. Create backend and start services

Keep project files in Ubuntu's Linux filesystem, e.g. `~/projects/dudho`, **not** `/mnt/c/` for better bind-mount performance.

```bash
mkdir -p ~/projects/dudho
cd ~/projects/dudho
# Copy/extract all starter files into this directory.
cp .env.example .env
cp .env.docker.example .env.docker
nano .env
nano .env.docker
# Set the same MYSQL_PASSWORD and DB_PASSWORD values.
# Generate the Laravel application in backend/ using a one-shot Composer container:
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD:/work" -w /work composer:2 create-project laravel/laravel backend
# Set Laravel application .env (see below) before running migrations.
docker compose up -d --build
```

Update `backend/.env` with:

```dotenv
APP_NAME=DUDHO
APP_URL=http://localhost:8080
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=dudho
DB_USERNAME=dudho
DB_PASSWORD=<same as MYSQL_PASSWORD>
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS=no-reply@dudho.test
MAIL_FROM_NAME="DUDHO"
```

**Note:** Compose `env_file: .env.docker` overrides any same-named variables in `backend/.env` at runtime. Keep their credentials and settings consistent. The simplest path is to configure both files with matching values.

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan about
docker compose ps
```

- API: http://localhost:8080
- Mailpit inbox: http://localhost:8025
- MySQL desktop client: 127.0.0.1:3307 (user/password from `.env`)
- MySQL inside containers: mysql:3306
- Redis inside containers: redis:6379
- SMTP inside containers: mailpit:1025

## 3. OTP strategy

Laravel implements OTP generation, hashing, expiry, throttling, verification attempt limits, and dispatch via queue `otp`. A local **email** OTP driver can send through SMTP Mailpit, and test emails appear at http://localhost:8025. For **real phone OTP**, add an SMS or WhatsApp provider API integration; a Docker container alone cannot deliver messages to phones. Never log or return OTP codes in production responses. Use a provider interface (e.g. `OtpSender`) with `MailOtpSender`, `SmsOtpSender`, and `WhatsAppOtpSender` adapters. Use test phone numbers/provider sandbox for development.

## 4. React Native networking

- Android emulator (standard Android emulator): `http://10.0.2.2:8080/api/v1`
- Physical phone: use your Windows machine's LAN IP and bind Nginx to a suitable host interface (the default `127.0.0.1` binding is **not** phone-accessible). Secure access with firewall and preferably a development tunnel; do not publicly expose MySQL, Redis, Mailpit or Docker daemon.
- Never hardcode production secrets in the mobile app.

## 5. Operations

```bash
docker compose logs -f app queue nginx
docker compose exec app php artisan migrate:status
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
docker compose restart queue scheduler
docker compose down                 # retains MySQL/Redis volumes
docker compose down -v              # DANGER: deletes DB and Redis volumes
```

If the worker starts before Laravel is fully initialized, restart `queue` and `scheduler` after `key:generate` and migrations.

## 6. Scope and next steps

The master blueprint covers the future Laravel domain model: identity/memberships, dairy/farmers, dynamic quality and pricing, milk collections, settlements, payments, disputes, audit, and offline sync/idempotency. This starter only creates the infrastructure. Next implement Phase 1 authentication + dairy onboarding, migrations, API contracts, policies, and automated tests. Keep all financial calculations on the backend and store money as DECIMAL.
