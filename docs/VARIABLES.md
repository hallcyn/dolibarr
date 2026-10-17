# Variables du template Railway

Ces blocs sont destinés à **l’éditeur de template**, pas au terminal, à Docker Compose ou aux variables d’un projet déjà créé.

`secret(...)` est évalué lors du déploiement d’un template. Pour créer un projet manuellement, saisir de vrais secrets aléatoires.

Les deux services doivent porter exactement les noms `MariaDB` et `Dolibarr`.

## MariaDB

### Raw Editor

```dotenv
MARIADB_DATABASE=dolibarr
MARIADB_USER=dolibarr
MARIADB_PASSWORD=${{secret(64, "abcdef0123456789")}}
MARIADB_ROOT_PASSWORD=${{secret(64, "abcdef0123456789")}}
```

### Descriptions à renseigner dans le template

| Variable | Description |
| --- | --- |
| `MARIADB_DATABASE` | Database created on first start. |
| `MARIADB_USER` | Application database user; no root privileges. |
| `MARIADB_PASSWORD` | Generated database password; referenced by Dolibarr. Do not regenerate on an existing database. |
| `MARIADB_ROOT_PASSWORD` | Independent generated database root password. Keep private; not used by Dolibarr. |

## Dolibarr

### Raw Editor

```dotenv
PORT=80
DOLI_DB_HOST=${{MariaDB.RAILWAY_PRIVATE_DOMAIN}}
DOLI_DB_HOST_PORT=3306
DOLI_DB_NAME=${{MariaDB.MARIADB_DATABASE}}
DOLI_DB_USER=${{MariaDB.MARIADB_USER}}
DOLI_DB_PASSWORD=${{MariaDB.MARIADB_PASSWORD}}
DOLI_URL_ROOT=https://${{RAILWAY_PUBLIC_DOMAIN}}
DOLI_ADMIN_LOGIN=admin
DOLI_ADMIN_PASSWORD=${{secret(64, "abcdef0123456789")}}
DOLI_INSTANCE_UNIQUE_ID=${{secret(64, "abcdef0123456789")}}
DOLI_COMPANY_NAME=Mon entreprise
DOLI_COMPANY_COUNTRYCODE=FR
DOLI_INSTALL_AUTO=1
DOLI_INIT_DEMO=0
DOLI_PROD=1
DOLI_CRON=0
DOLI_RAILWAY_CRON=0
DOLI_CRON_KEY=${{secret(64, "abcdef0123456789")}}
DOLI_CRON_USER=firstadmin
PHP_INI_DATE_TIMEZONE=Europe/Paris
PHP_INI_MEMORY_LIMIT=256M
PHP_INI_UPLOAD_MAX_FILESIZE=20M
PHP_INI_POST_MAX_SIZE=24M
```

### Descriptions à renseigner dans le template

| Variable | Description |
| --- | --- |
| `PORT` | HTTP port; set the generated Railway domain target to 80 too. |
| `DOLI_DB_HOST` | Private database DNS; keep both services in the same project environment. |
| `DOLI_DB_HOST_PORT` | Private MariaDB TCP port, not the public proxy port. |
| `DOLI_DB_NAME` | Database name from the MariaDB service. |
| `DOLI_DB_USER` | Restricted database account from MariaDB. |
| `DOLI_DB_PASSWORD` | Reference the generated database password; do not generate another one here. |
| `DOLI_URL_ROOT` | Public HTTPS URL. Set a full https:// URL when switching to a custom domain. |
| `DOLI_ADMIN_LOGIN` | Initial administrator login. Letters, digits, _, ., @ and - only. |
| `DOLI_ADMIN_PASSWORD` | Generated first-install password. Read this variable to log in; later changes belong in Dolibarr. |
| `DOLI_INSTANCE_UNIQUE_ID` | Permanent instance encryption key. Back it up and never regenerate it for an existing installation. |
| `DOLI_COMPANY_NAME` | Your company name; initializes the company on first installation. Change later in Dolibarr. |
| `DOLI_COMPANY_COUNTRYCODE` | Two-letter company country code; defaults to France. |
| `DOLI_INSTALL_AUTO` | Initialize the empty database automatically; keep 1. |
| `DOLI_INIT_DEMO` | No sample customers or invoices; keep 0 for real business use. |
| `DOLI_PROD` | Production mode; keep 1. |
| `DOLI_CRON` | Upstream cron-only mode must stay 0 so Apache runs. |
| `DOLI_RAILWAY_CRON` | Optional in-container scheduler: set 1 after enabling Scheduled Jobs in Dolibarr. |
| `DOLI_CRON_KEY` | Scheduler key. Also set it in the Dolibarr Scheduled Jobs module when enabling cron. |
| `DOLI_CRON_USER` | User running scheduled jobs; firstadmin selects an active administrator. Prefer a dedicated user later. |
| `PHP_INI_DATE_TIMEZONE` | PHP timezone. Choose another IANA zone when needed. |
| `PHP_INI_MEMORY_LIMIT` | PHP memory per request, not the Railway service memory limit. |
| `PHP_INI_UPLOAD_MAX_FILESIZE` | Maximum size per uploaded attachment. |
| `PHP_INI_POST_MAX_SIZE` | POST limit; must exceed the per-file upload limit. |
