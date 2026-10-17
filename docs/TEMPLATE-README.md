# Dolibarr ERP & CRM

Run your own Dolibarr installation on Railway with a private MariaDB database, automatic first-time setup, and persistent storage for documents and external modules.

This community template uses the official Dolibarr Docker filesystem and adds a small Railway-specific startup layer. It does not include commercial modules or a managed support service.

## Included

- Dolibarr **24.0.2**, with PHP and Apache.
- MariaDB **11.4 LTS**, reachable only over Railway's private network.
- Two persistent volumes: one for SQL data and one for documents, custom modules, sessions, and the permanent instance key.
- Unique database, administrator, and instance secrets generated when deploying the template.
- Automatic installation on an empty database, with demo data disabled.
- HTTPS public domain, a database-aware deployment health check, and an optional in-container scheduler.
- Portable backup script and documented recovery / upgrade procedures.

Enable the Dolibarr modules you need: customers, suppliers, quotes, invoices, orders, products, inventory, bank accounts, projects, and more. Third-party modules have their own requirements and licences.

## Deploy

1. Set your company name and two-letter country code. Defaults use France and the Europe/Paris timezone.
2. Keep the generated secrets and database references unchanged.
3. Deploy both services with their attached volumes in the same region.
4. Wait for the Dolibarr service to become healthy.
5. Open the Dolibarr public domain. Its target port must be **80**.
6. Sign in using `DOLI_ADMIN_LOGIN` and the generated `DOLI_ADMIN_PASSWORD` in the Dolibarr service variables. Change the password in Dolibarr after signing in.

The initial administrator password and company settings apply during first installation. Editing the environment later does not reset the existing account or company. Manage those through Dolibarr.

## Persistent storage

| Service | Mount path | Contents |
| --- | --- | --- |
| MariaDB | `/var/lib/mysql` | Database files |
| Dolibarr | `/data` | Documents, attachments, custom modules, sessions, instance key, local backup archives |

Do not mount a volume over `/var/www/html` or `/var/www`. The startup layer maps Dolibarr's data directories into `/data`. The application code stays inside the image, allowing controlled version updates.

Never regenerate `DOLI_INSTANCE_UNIQUE_ID` for an existing installation. The startup layer stores a protected copy and refuses to run with a different value.

## Optional scheduled jobs

Cron is disabled by default. Enable the Scheduled Jobs module in Dolibarr, set its security key to `DOLI_CRON_KEY`, and set `DOLI_RAILWAY_CRON=1` on the Dolibarr service. Jobs are checked every five minutes. Keep `DOLI_CRON=0`; that upstream setting selects a separate cron-only container mode and would stop the web server.

Jobs run only while the service is active. Leave Railway sleeping disabled when using the scheduler. No extra service is required. Job configuration and permissions still need to be set in Dolibarr.

## E-mail

An SMTP service is not included. Configure an external SMTP provider in Dolibarr and test delivery. As of October 2026, outbound SMTP requires Railway Pro or above and is disabled on Free, Trial, and Hobby. These plans need an explicitly compatible HTTPS email integration, which this template does not include. See [Railway outbound networking](https://docs.railway.com/networking/outbound-networking).

## Backups and maintenance

Configure backups for **both volumes** and keep an additional copy outside Railway. The bundled `dolibarr-backup` command creates an archive containing the SQL export, documents, modules, version number, and permanent instance key. Pause application writes and cron for a consistent backup, protect the resulting archive, and test restoration.

Versions are deliberately pinned. Back up before upgrades, follow the migration procedure, and do not downgrade the application against an already migrated database.

## Hosting costs and limitations

Dolibarr and the template are free software; Railway resources are billed under your plan. The two-service configuration requires two volumes and is not suitable for Railway's one-volume Free plan. Check [current pricing](https://docs.railway.com/pricing/plans) before deploying. Resource limits are not cost guarantees.

This is a single-instance deployment, without high availability. Redeployments involving a volume have a short interruption. Railway's deployment health check is not a continuous uptime monitor. SMTP, off-site backup automation, and paid extensions are not included.

## Documentation and support

- [Complete setup guide in French](https://github.com/hallcyn/dolibarr/blob/main/docs/RAILWAY.md)
- [Backups, restores, upgrades, cron and troubleshooting](https://github.com/hallcyn/dolibarr/blob/main/docs/OPERATIONS.md)
- [Source and template issues](https://github.com/hallcyn/dolibarr)
- [Dolibarr official Docker image](https://github.com/Dolibarr/dolibarr-docker)

This template is maintained independently and is not an official Railway or Dolibarr publication.
