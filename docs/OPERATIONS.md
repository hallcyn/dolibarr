# Exploitation, sauvegardes et dépannage

## Ce qui persiste et ce qui est régénéré

| Élément | Emplacement | Comportement |
| --- | --- | --- |
| Base SQL | Volume MariaDB `/var/lib/mysql` | Conservée après redéploiement |
| Documents, PDF, pièces jointes | Volume Dolibarr `/data/documents` | Conservés |
| Modules externes | `/data/custom` | Conservés ; vérifier leur compatibilité à chaque mise à jour |
| Sessions PHP | `/data/sessions` | Conservées ; nettoyées par le mécanisme de sessions PHP |
| Clé d’instance | Variable `DOLI_INSTANCE_UNIQUE_ID` et `/data/secrets/instance-id` | Doivent rester identiques |
| Configuration PHP | `/var/www/html/conf/conf.php` | Régénérée à chaque démarrage depuis les variables |
| Code de Dolibarr | Image Docker | Remplacé lors d’une mise à jour |
| Sauvegardes locales | `/data/backups` | Conservées, mais sur le même volume que les données |

Ne pas modifier directement `conf.php` : la modification serait écrasée. Ce template prend en charge l’authentification Dolibarr et une base MariaDB privée. Une intégration LDAP ou une base externe avec TLS demande une adaptation explicite du générateur de configuration.

## Sauvegardes

### Sauvegardes Railway

Dans chacun des deux volumes, activer le planning de sauvegarde proposé par le plan et créer une première sauvegarde manuelle. Vérifier la rétention, le prix et la procédure de restauration dans l’interface.

Les sauvegardes des deux services ne sont pas automatiquement une transaction commune. Pour une paire cohérente avant une opération importante, interdire les écritures et suspendre cron, puis sauvegarder les deux volumes. Garder aussi une copie indépendante hors Railway.

### Archive portable

Prérequis : installer la [CLI Railway](https://docs.railway.com/cli), se connecter, lier le bon projet avec `railway link` et enregistrer une clé SSH lors du premier `railway ssh` si demandé. Vérifier projet, environnement et service avant toute commande.

La commande suivante se lance **dans un terminal de votre ordinateur** :

```bash
mkdir -p backups
railway ssh --service Dolibarr -- dolibarr-backup --stdout > backups/dolibarr.tar.gz
tar -tzf backups/dolibarr.tar.gz
```

Faire ce transfert avec une version récente de la CLI supportant une commande SSH sans pseudo-terminal. Lister l’archive doit réussir et afficher `database.sql`, `dolibarr-version.txt`, `documents/`, `custom/` et `secrets/instance-id`. En cas d’échec, ne pas considérer le fichier comme une sauvegarde valide.

Autre méthode : ouvrir `railway ssh --service Dolibarr`, lancer `dolibarr-backup`, puis récupérer le fichier indiqué avec le navigateur de volumes Railway ou `scp`, conformément à la [documentation SSH](https://docs.railway.com/cli/ssh).

```bash
scp VOTRE-DOMAINE.up.railway.app@ssh.railway.com:/data/backups/NOM-EXACT.tar.gz ./backups/
```

Remplacer les deux valeurs par celles affichées dans votre projet ; elles ne sont pas des commandes à copier telles quelles. Ne pas partager l’archive : elle contient les données métier et la clé d’instance.

**Cohérence :** le dump SQL utilise `--single-transaction`, mais les fichiers ne sont pas figés en même temps. Faire sortir les utilisateurs, empêcher l’accès au domaine public pendant l’opération et désactiver le planificateur (`DOLI_RAILWAY_CRON=0`, redéploiement si nécessaire). Avec un service toujours disponible par SSH, on peut arrêter temporairement Apache depuis la session root avec `apache2ctl -k graceful-stop`, exécuter la sauvegarde, puis redéployer le même service pour le relancer. Valider d’abord ce déroulé dans une instance de test. Ne pas arrêter MariaDB pendant un dump.

Les archives conservées dans `/data/backups` ne sont pas une protection contre la perte de ce volume. Les télécharger régulièrement, conserver plusieurs générations et supprimer les anciennes une fois les copies externes vérifiées. Le script ne supprime aucune archive automatiquement.

## Restauration sur une instance séparée

Tester d’abord la restauration sans toucher à la production. Utiliser la **même version de Dolibarr** que celle indiquée dans `dolibarr-version.txt` ; ne pas restaurer une base récente sur une image plus ancienne.

### Test de restauration local avec Docker Compose

Dans un **nouveau clone et un projet Compose distinct**, placer l’archive dans `backups/`. Les commandes ci-dessous travaillent uniquement dans ce nouveau projet, nommé `dolibarr-restore` :

```bash
python3 scripts/setup-local.py
mkdir -p backups/restored
tar -xzf backups/dolibarr.tar.gz -C backups/restored
```

Vérifier le contenu et la version. Copier la valeur de `backups/restored/secrets/instance-id` dans `DOLI_INSTANCE_UNIQUE_ID` du nouveau `.env`, en conservant les autres identifiants DB nouvellement générés. Mettre à jour le `FROM` et `DOLI_VERSION` du Dockerfile si l’archive utilise une autre version.

```bash
docker compose -p dolibarr-restore up -d mariadb
docker compose -p dolibarr-restore exec -T mariadb healthcheck.sh --connect --innodb_initialized
```

Attendre une sortie réussie du healthcheck. Importer ensuite le SQL dans la base neuve :

```bash
docker compose -p dolibarr-restore exec -T mariadb sh -c 'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb --user="$MARIADB_USER" "$MARIADB_DATABASE"' < backups/restored/database.sql
docker compose -p dolibarr-restore run --rm --no-deps --entrypoint bash -v "$PWD/backups/restored:/restore:ro" dolibarr -c 'mkdir -p /data/documents /data/custom /data/secrets; cp -a /restore/documents/. /data/documents/; cp -a /restore/custom/. /data/custom/; cp -a /restore/secrets/. /data/secrets/'
docker compose -p dolibarr-restore up --build -d
```

Ouvrir http://localhost:8080 en s’assurant qu’aucune autre installation locale n’utilise ce port. Se connecter avec un compte et mot de passe **de la sauvegarde**, pas le nouveau mot de passe initial du `.env`. Vérifier les tiers, le stock, les factures et leurs fichiers. Désactiver les e-mails sortants et les intégrations externes dans cette copie de test avant de l’utiliser.

### Restauration Railway

Pour des snapshots Railway, restaurer les deux volumes correspondant au même point métier, avec l’image et la clé correspondantes, selon le parcours de restauration de l’interface.

Pour une archive portable : créer un projet séparé, couper son accès public et cron, puis importer le SQL dans sa base MariaDB neuve et copier `documents`, `custom` et `secrets` sous `/data`. `railway ssh` et `scp` permettent ces transferts ; le service doit tourner pour être joignable. Si le template a déjà initialisé cette nouvelle base, utiliser une autre base vide ou la vider uniquement dans ce projet de restauration après vérification de son identité. Remettre la clé d’instance de la sauvegarde dans les variables, puis redéployer Dolibarr. La variable DB doit désigner le nouveau projet, jamais l’ancien.

Ne pas bricoler une restauration directement sur l’instance de production sans avoir validé l’archive et la procédure sur une copie.

## Surveillance automatique des versions

Le workflow **Check upstream versions** s’exécute chaque jour à **05:17 UTC**, à la demande depuis GitHub Actions et après un changement des sources surveillées sur `main`. Les pull requests exécutent uniquement les tests du moniteur, sans permission d’écrire des issues.

- **Dolibarr** : compare la version du Dockerfile aux tags numériques stables publiés dans l’image officielle. Les tags `develop`, RC et variantes PHP sont ignorés. Les versions sont comparées numériquement et l’image doit exister pour Linux amd64.
- **MariaDB** : consulte l’API de la Fondation MariaDB, sélectionne la branche LTS stable maintenue la plus récente et vérifie son image Docker. Une nouvelle branche ouvre une demande d’examen, même si `11.4` reste maintenue ; cela ne prouve pas qu’une migration est nécessaire ou compatible. Une fin de support est signalée dans l’issue.
- Le tag MariaDB `12.3.3` est fixé à un patch précis : les correctifs suivants nécessitent une PR et une validation explicites. Le moniteur compare ce patch au patch LTS disponible ; il ne connaît pas la version réellement exécutée par une instance Railway et ne détecte pas les seuls changements de digest d’un tag inchangé.

Une issue créée par `github-actions[bot]` est maintenue par composant. Une nouvelle version actualise son titre et son corps, sans commentaire quotidien ni nouvelle issue tant qu’elle reste ouverte. Les échanges doivent rester dans les commentaires. Une issue fermée comme **not planned** ignore cette version précise. Une version ultérieure peut déclencher une nouvelle issue. Lorsque le dépôt est à jour, l’issue ouverte est fermée automatiquement au prochain contrôle.

L’alerte contient les références actuelles et disponibles, les sources et une checklist : notes de version, compatibilité, sauvegarde, migration, CI puis mise à jour explicite du template. **Le moniteur ne met rien à jour et ne déploie aucun service.** Il n’utilise aucun secret Railway. Le jeton temporaire `GITHUB_TOKEN`, fourni par GitHub, reçoit uniquement `contents: read` et `issues: write` pour le job d’alerte.

Si une API est indisponible, le contrôle échoue ; les issues existantes restent inchangées. Le résumé de l’exécution affiche les versions trouvées. Une nouvelle version détectée fait réussir le contrôle et ouvre une issue : ce n’est pas une panne de l’application.

Pour vérifier sans écrire d’issue :

```bash
python3 scripts/check-versions.py
python3 -m unittest discover -s tests -p 'test_versions.py' -v
```

GitHub peut retarder une tâche planifiée et désactive les workflows planifiés des dépôts publics après 60 jours sans activité. Vérifier que ce workflow reste actif dans l’onglet Actions. Voir [les événements planifiés GitHub](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#schedule), [les permissions du jeton](https://docs.github.com/en/actions/tutorials/authenticate-with-github_token), [l’API MariaDB](https://mariadb.org/downloads-rest-api/) et [les tags Dolibarr](https://hub.docker.com/r/dolibarr/dolibarr/tags).

## Mise à jour de Dolibarr

Les deux occurrences `24.0.2` du Dockerfile sont liées : la source de l’image et la variable `DOLI_VERSION` doivent correspondre. Ce choix évite qu’un redémarrage récupère silencieusement une nouvelle version majeure. MariaDB est fixé à `12.3.3` ; ses correctifs suivants passent également par une PR. Une mise à jour du dépôt ne migre pas les instances ni le template publié. Voir la [migration MariaDB](MARIADB-UPGRADE.md) avant toute modification de base existante.

1. Lire les notes de version Dolibarr et vérifier les modules externes ; faire les sauts de versions selon la procédure officielle.
2. Faire une sauvegarde cohérente et externe des données, des fichiers et de la clé ; tester sa restauration.
3. Dans une copie de test, modifier l’image de base et `DOLI_VERSION`, puis vérifier l’installation et les migrations.
4. Pour autoriser la migration de l’installateur Docker officiel, supprimer le verrou **uniquement après les sauvegardes et dans l’instance concernée** :

   ```bash
   railway ssh --service Dolibarr -- rm -f /var/www/documents/install.lock
   ```

5. Déployer le commit contenant la nouvelle image. `DOLI_INSTALL_AUTO=1` laisse le script officiel migrer la base puis remettre le verrou.
6. Examiner les logs et `/data/documents/migration_error.html` si présent. Vérifier la version affichée dans Dolibarr, l’accès aux données, les PDF et les modules. Le seul healthcheck ne garantit pas la réussite de toutes les migrations métier.
7. Répéter en production pendant un créneau de maintenance après validation de la copie.

Un simple rollback d’image ne restaure pas une base migrée. Revenir à l’ancienne version peut demander de restaurer **base + fichiers + clé + ancienne image** ensemble. Ne pas mettre à jour MariaDB et Dolibarr simultanément.

## Planificateur facultatif

1. Activer le module **Travaux planifiés / Scheduled Jobs** dans Dolibarr.
2. Définir sa clé de sécurité avec la valeur `DOLI_CRON_KEY` du service.
3. Créer / activer les tâches souhaitées dans Dolibarr et vérifier leurs droits.
4. Définir `DOLI_RAILWAY_CRON=1`, conserver `DOLI_CRON=0` et redéployer.
5. Au bout de cinq minutes, vérifier les dernières exécutions dans Dolibarr et le fichier `/data/documents/railway-cron.log`.

La tâche utilise `firstadmin` par défaut. Préférer ensuite un utilisateur dédié avec les droits nécessaires via `DOLI_CRON_USER`. Une tâche longue empêche le lancement simultané d’une seconde grâce au verrou `flock`. Les logs de cron ne sont pas automatiquement purgés ; surveiller leur taille. Aucun cron ne s’exécute pendant une interruption ou un arrêt du service.

## E-mails sortants

Dans la configuration e-mail de Dolibarr, sélectionner la méthode SMTP et saisir l’hôte, le port, le chiffrement, l’utilisateur et le mot de passe fournis par votre prestataire. Configurer l’adresse d’expéditeur et les enregistrements SPF / DKIM / DMARC indiqués par ce prestataire, puis envoyer un message de test.

Les variables inventées `DOLI_SMTP_*` ne configurent pas automatiquement ce template : les réglages se font dans Dolibarr. Au 17 octobre 2026, la documentation Railway réserve SMTP aux plans Pro et supérieurs : SMTP est désactivé sur Free, Trial et Hobby. Voir [les restrictions officielles](https://docs.railway.com/networking/outbound-networking). Si SMTP n’est pas disponible, utiliser un plan compatible ou une intégration API e-mail explicitement compatible avec Dolibarr ; le template ne fournit pas ce pont.

## Dépannage

| Symptôme | Vérifications / action |
| --- | --- |
| `Database unavailable` | MariaDB déployée, volume monté, `DOLI_DB_HOST` résolu vers le domaine privé du bon service, port 3306, mêmes utilisateur / base / mot de passe |
| Installation très longue | Examiner d’abord les logs MariaDB puis Dolibarr. Le contrôle de déploiement attend jusqu’à 300 s ; ne pas effacer les volumes pour réessayer |
| Variable contenant `${{...}}` | Une fonction de template a été collée dans un projet courant ; la remplacer par un vrai secret, sans changer une clé ou un mot de passe de base déjà initialisés |
| Mot de passe DB changé mais accès refusé | Les variables de l’image MariaDB initialisent une base neuve ; elles ne font pas une rotation SQL sur une base existante. Revenir à la valeur d’origine puis suivre une vraie rotation SQL si nécessaire |
| Clé d’instance différente | Rétablir la clé d’origine de `/data/secrets/instance-id` / de la sauvegarde. Ne pas supprimer ce fichier pour forcer une nouvelle clé |
| Erreur 502 / Application failed to respond | Port cible 80, `PORT=80`, Start Command vide côté Dolibarr, service démarré et healthcheck valide |
| Boucle de connexion / problème HTTPS | `DOLI_URL_ROOT` égale au domaine HTTPS réellement utilisé, cookies du bon domaine ; tester une fenêtre privée après un changement de domaine |
| Fichiers ou modules perdus | Volume Dolibarr exactement à `/data` ; ne pas utiliser le stockage éphémère pour vos données |
| Cron actif mais aucune tâche | Module et tâche activés, clé identique, droits de l’utilisateur, `DOLI_RAILWAY_CRON=1`, service actif, dernier résultat dans Dolibarr |
| Admin inaccessible après modification des variables | Le compte existant ne se réinitialise pas. Utiliser la récupération Dolibarr ou une procédure d’administration validée |
| Facture / pièce jointe trop volumineuse | Ajuster les limites PHP en gardant POST plus grand que le fichier et vérifier aussi les limites de la plateforme |
| Installation incomplète mais verrou créé | Examiner `initdb.log`, corriger la cause et travailler sur une copie ; ne pas supprimer les volumes contenant des données réelles |

## Vérifications techniques

```bash
railway ssh --service Dolibarr -- curl -fsS http://localhost/railway-health.php
railway ssh --service Dolibarr -- ls -ld /data/documents /data/custom /data/sessions
railway ssh --service Dolibarr -- apache2ctl configtest
```

Le healthcheck HTTP ne renvoie aucun identifiant ni détail SQL. Il ne vérifie ni l’envoi SMTP, ni le bon résultat des tâches cron, ni la validité de chaque PDF. Configurer une surveillance externe si nécessaire.

Références : [sauvegardes Railway](https://docs.railway.com/volumes/backups), [SSH et transferts](https://docs.railway.com/cli/ssh), [Docker Dolibarr et migrations](https://github.com/Dolibarr/dolibarr-docker).
