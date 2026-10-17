# Deploy and Host Dolibarr on Railway

Dolibarr is an open-source ERP and CRM for small businesses, associations, and independent professionals. Manage customers, suppliers, quotes, invoices, orders, products, inventory, bank accounts, and projects in one web application. Enable only the modules you need and extend your installation with compatible community or commercial add-ons.

## About Hosting Dolibarr

This community template deploys Dolibarr 24.0.2 with PHP and Apache, plus a private MariaDB 11.4 LTS database. It uses the official Dolibarr Docker filesystem with a small Railway-specific startup layer. Automatic setup initializes an empty database without demo data. Two volumes preserve the SQL database, documents, attachments, custom modules, sessions, and permanent instance key. Each deployment generates unique administrator and database secrets. A public HTTPS domain and database-aware deployment health check are configured automatically. The optional in-container scheduler needs no additional service. You remain responsible for backups, upgrades, email delivery, and application configuration.

Docker build, automatic installation, backup contents, and persistence after container recreation have passed the repository CI. A live Railway deployment and independent restore still need validation; see [validation status](https://github.com/hallcyn/dolibarr/blob/main/VALIDATION.md).

## Common Use Cases

- Manage customer relationships, quotations, sales orders, invoices, and payments.
- Track products, supplier purchases, warehouses, and inventory movements.
- Centralize business documents, projects, expenses, and association administration.

## Dependencies for Dolibarr Hosting

- A Railway plan supporting two persistent volumes and sufficient compute for Dolibarr and MariaDB. The one-volume Free plan cannot host this complete stack.
- The public hallcyn/dolibarr repository and the official MariaDB container image; both sources are configured in the template.
- An external email delivery service if you need email. SMTP is not bundled; see the email section below for Railway plan restrictions.
- A backup destination outside the running services for a recoverable installation.

## Deploy

1. Set your company name and two-letter country code. Defaults use France and the Europe/Paris timezone.
2. Keep generated secrets and database references unchanged.
3. Deploy both services with their attached volumes in the same region.
4. Wait for Dolibarr to become healthy, then open its public domain. The target port must be 80.
5. Sign in with DOLI_ADMIN_LOGIN and the generated DOLI_ADMIN_PASSWORD from the Dolibarr service variables. Change the password inside Dolibarr after signing in.

The initial administrator password and company settings apply during first installation. Editing environment variables later does not reset the existing account or company. Manage those through Dolibarr.

## Persistent storage

| Service | Mount path | Contents |
| --- | --- | --- |
| MariaDB | /var/lib/mysql | Database files |
| Dolibarr | /data | Documents, attachments, custom modules, sessions, instance key, local backup archives |

Do not mount a volume over `/var/www/html` or `/var/www`. Application code stays inside the image, allowing controlled version updates. The startup layer maps the data directories into /data.

Never regenerate DOLI_INSTANCE_UNIQUE_ID for an existing installation. The startup layer stores a protected copy and refuses to run with a different value.

## Optional scheduled jobs

Cron is disabled by default. Enable the Scheduled Jobs module in Dolibarr, set its security key to DOLI_CRON_KEY, and set DOLI_RAILWAY_CRON=1 on the Dolibarr service. Jobs are checked every five minutes. Keep DOLI_CRON=0; that upstream setting selects a separate cron-only container mode and would stop the web server.

Jobs run only while the service is active. Leave Railway sleeping disabled when using the scheduler. Configure each job and its permissions inside Dolibarr. Prefer a dedicated scheduler user later.

## Email

Configure an external provider in Dolibarr and test delivery. As of October 2026, outbound SMTP requires Railway Pro or above and is disabled on Free, Trial, and Hobby. These plans need an explicitly compatible HTTPS email integration, which this template does not include. See [Railway outbound networking](https://docs.railway.com/networking/outbound-networking).

## Backups and maintenance

Configure backups for both volumes and keep an additional copy outside Railway. The bundled dolibarr-backup command creates an archive containing the SQL export, documents, modules, version number, and permanent instance key. Pause application writes and cron for a consistent backup, protect the archive, and test restoration.

Versions are deliberately pinned. Back up before upgrades, follow the migration procedure, and do not downgrade the application against an already migrated database.

## Hosting costs and limitations

Dolibarr and the template are free software; Railway resources are billed under your plan. Check [current Railway plans](https://docs.railway.com/pricing/plans) before deploying. Resource limits are not cost guarantees.

This is a single-instance deployment without high availability. Volume-backed redeployments have a short interruption. The deployment health check is not a continuous uptime monitor. SMTP, off-site backup automation, paid extensions, and managed support are not included. Third-party modules have their own requirements and licences.

## Why Deploy Dolibarr on Railway?

Railway keeps the application, private database, HTTPS domain, deployment logs, environment variables, and persistent volumes in one project. This template supplies the connections and startup configuration, making a fresh Dolibarr installation straightforward to deploy and maintain. Keep one application instance because the template uses local persistent storage.

## Documentation and support

- [Complete setup guide in French](https://github.com/hallcyn/dolibarr/blob/main/docs/RAILWAY.md)
- [Backups, restores, upgrades, cron and troubleshooting](https://github.com/hallcyn/dolibarr/blob/main/docs/OPERATIONS.md)
- [Source and template issues](https://github.com/hallcyn/dolibarr)
- [Official Dolibarr Docker image](https://github.com/Dolibarr/dolibarr-docker)

Maintained independently by Hallcyn; this is not an official Railway or Dolibarr template.
