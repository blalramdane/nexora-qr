# NEXORA QR — Free VM deployment runbook

## Suggested host

Use an Oracle Cloud Infrastructure **Always Free-eligible** ARM VM for a development/pilot deployment, subject to account eligibility and capacity. Oracle documents up to 2 OCPUs and 12 GB RAM of Always Free Ampere A1 resources. Capacity is not guaranteed, and Oracle can reclaim instances it considers idle. Keep independent backups; do not treat this as a production SLA.

Official references:
- https://www.oracle.com/cloud/free/
- https://docs.oracle.com/en-us/iaas/Content/FreeTier/freetier_topic-Always_Free_Resources.htm

Create only resources explicitly labelled **Always Free**. Do not upgrade the account or provision paid resources. Oracle may require card/phone verification. Follow the provider's account eligibility rules; do not bypass identity or age checks.

## What this deployment package preserves

- MySQL data in the `mysql_data` Docker volume.
- Product photos in the `product_uploads` Docker volume.
- The Laravel database queue in MySQL, serviced by the separate `queue` container.
- HTTPS certificates and Caddy state in separate named volumes.
- No exposed public MySQL port.

For blitz.cloud's Compose-based deployment, use the repository's top-level `compose.yaml` instead of this VM-specific file; it defines only the web and queue parts and leaves the managed database to the platform.

The app does **not** migrate or seed the database automatically. This is deliberate: the current migration set includes business-data changes for known demo tenants and an Al Saqa menu repair. Review the target database and take a restorable backup before applying migrations.

## Preparation on the VM

1. Provision an Always Free eligible Ubuntu ARM VM and retain its boot volume. Configure the cloud firewall/security list and host firewall for inbound TCP 80 and 443 only; SSH should be restricted to your own IP where possible.
2. Install Docker Engine and the Docker Compose plugin using Docker's official instructions for the VM operating system.
3. Point a domain/subdomain A record to the VM's public IPv4 address. Do not expose the site as production until HTTPS is active.
4. Copy the project source to the VM through a private, authenticated method. Do not publish credentials or the production env file to GitHub.
5. From this `ai-core` directory, create the server-only environment file:

   ```sh
   cp deploy/.env.production.example deploy/.env.production
   chmod 600 deploy/.env.production
   ```

6. Edit `deploy/.env.production`: set `APP_URL` and `APP_DOMAIN` to the real HTTPS hostname; set `MYSQL_PASSWORD` and `MYSQL_ROOT_PASSWORD` to two different random hex values (for example, generate each separately with `openssl rand -hex 32`); replace `MYSQL_DATABASE`/`MYSQL_USER` if desired. Keep `APP_DEBUG=false`. Keep WhatsApp and OpenAI keys empty until those integrations are deliberately configured later.
7. Build the image and generate an application key without changing any existing local `.env` file:

   ```sh
   docker compose --env-file deploy/.env.production -f deploy/compose.free-vm.yml build
   docker compose --env-file deploy/.env.production -f deploy/compose.free-vm.yml run --rm --no-deps app php artisan key:generate --show
   ```

   Copy the printed `base64:...` value into `APP_KEY` in `deploy/.env.production`; do not share or commit it.

## First start — only after database review/backup

Start only the database first:

```sh
docker compose --env-file deploy/.env.production -f deploy/compose.free-vm.yml up -d db
```

If the target is a **brand-new, empty production database**, inspect the pending migrations first, then run the migration command explicitly:

```sh
docker compose --env-file deploy/.env.production -f deploy/compose.free-vm.yml run --rm app php artisan migrate:status
docker compose --env-file deploy/.env.production -f deploy/compose.free-vm.yml run --rm app php artisan migrate --force
```

If importing an existing database, take and verify a backup before any migration. Do not run `migrate:fresh`, `db:wipe`, or seeders against a live database. Do not run `db:seed` automatically; initial platform owner/account setup must be handled as a separate controlled step.

Then start the application, queue, and HTTPS proxy:

```sh
docker compose --env-file deploy/.env.production -f deploy/compose.free-vm.yml up -d --build
docker compose --env-file deploy/.env.production -f deploy/compose.free-vm.yml ps
docker compose --env-file deploy/.env.production -f deploy/compose.free-vm.yml logs --tail=100 app queue caddy
```

Caddy obtains TLS certificates only after the public DNS record points to this VM and ports 80/443 are reachable. Do not reconnect WhatsApp/Meta or other external APIs as part of this initial deployment.

## Before real customers

- Set up and restore-test backups **off the VM** for both MySQL and uploaded photos. A backup on the same free VM is not disaster recovery.
- Create the platform owner through an approved, secure account-setup process; do not place a password in this repository.
- Confirm `APP_URL` exactly matches the HTTPS domain and test login, product-photo upload persistence across container recreation, QR scanning from a phone, order creation, order tracking, queue processing, and tenant separation.
- Check the Cloud provider's current free-tier terms/quotas and monitor usage. This package does not create billing resources or deploy anything by itself.
