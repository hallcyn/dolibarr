# Incident Railway et correctifs PHP — 23 octobre 2026

L’image officielle Dolibarr 24.0.2 a été reconstruite sur PHP 8.5 le 22 octobre. Le numéro applicatif Dolibarr reste 24.0.2. Une image déjà en production ne se met pas à jour seule. Le Dockerfile vérifie désormais au build que le PHP embarqué respecte les correctifs du 21 octobre : 8.2.35, 8.3.36, 8.4.27 ou 8.5.12 minimum dans leur branche respective. La CI reconstruit avec `--pull`, consigne la version PHP et les digests upstream, et utilise uniquement des bases jetables.

## Avant intervention sur la production

Suspendre les écritures métier le temps de la sauvegarde. Depuis un terminal authentifié, conserver hors Railway une archive complète :

```bash
railway ssh --project fac978ff-d1c1-485b-a6d3-f5e1b8aec02b --environment 8b35c3fc-6c10-47e5-96ce-bde46897a65e --service 5f5f0a0a-353c-4466-813e-4414252346b4 -- dolibarr-backup --stdout > dolibarr-production-before-security.tar.gz
```

Cette archive contient la base applicative, les documents, les modules personnalisés et la clé d’instance : conserver dans un espace privé chiffré, jamais dans GitHub. Vérifier l’intégrité de l’archive puis sa restauration sur une copie isolée avant une migration de base. Conserver les volumes d’origine et une sauvegarde externe ; une copie sur le même volume ne suffit pas.

## Rotation des secrets

- Mot de passe du compte administrateur : changer depuis Dolibarr. `DOLI_ADMIN_PASSWORD` ne réinitialise pas les comptes existants ; renouveler également cette valeur d’installation.
- MariaDB : modifier les vrais comptes SQL et synchroniser `MARIADB_PASSWORD` / `MARIADB_ROOT_PASSWORD`. Les variables d’initialisation ne changent pas les comptes d’un volume déjà initialisé. Conserver base et utilisateur ; inventorier les hôtes exacts des comptes avant les commandes SQL. Préparer le redéploiement de Dolibarr avec la nouvelle référence avant de révoquer son ancien accès.
- Conserver `DOLI_DB_PASSWORD=${{MariaDB.MARIADB_PASSWORD}}` et vérifier la connexion après redéploiement.
- Renouveler `DOLI_CRON_KEY` et les éventuels appels externes. Le cron est désactivé dans la configuration fournie.
- Ne pas remplacer aveuglément `DOLI_INSTANCE_UNIQUE_ID` : le template le compare à `/data/secrets/instance-id`, et certaines valeurs chiffrées Dolibarr peuvent en dépendre. Inventorier et migrer ces valeurs avant sa rotation.
- Renouveler aussi tout secret API/SMTP/registre exposé sur d’autres services Railway ; la correction PHP ne révoque pas ces accès.

La PR MariaDB 12.3 est indépendante de ce correctif PHP et exige une recette sur copie de la sauvegarde réelle. Ne pas changer l’image MariaDB sur le volume de production 11.4 pour traiter l’incident.

## Références

- https://www.php.net/archive/2026.php
- https://github.com/Dolibarr/dolibarr-docker/commit/f457147320a484cdbaaa3ab81504944a20f796be
- https://status.railway.com/incident/QH8L8B3E
