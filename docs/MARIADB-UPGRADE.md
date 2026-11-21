# Migration MariaDB 11.4 → 12.3.3 LTS

Références vérifiées le **19 octobre 2026**. Ce changement concerne la spécification du dépôt et les nouvelles installations. Les instances existantes et le template publié dans Railway demandent une opération distincte et explicitement autorisée.

## Version et compatibilité

- Cible : image officielle `mariadb:12.3.3`, patch stable de la branche **12.3 LTS**. Les branches rolling, RC et preview sont exclues. Le tag est fixé pour examiner chaque correctif par PR.
- La politique MariaDB indique une maintenance communautaire de 12.3 jusqu’au **12 juin 2029** ; 11.4 reste maintenue jusqu’au **29 mai 2029**. Il ne s’agit donc pas d’une migration urgente imposée par la fin du support de 11.4.
- Dolibarr reste en **24.0.2** : aucun changement du Dockerfile, de `DOLI_VERSION`, du schéma applicatif ou des modules n’est requis par cette PR. La matrice officielle de Dolibarr 24 annonce MariaDB **10.3 minimum**, ce qui ne constitue pas une certification spécifique de 12.3 ni des modules tiers.
- Les options actuelles du serveur ont été comparées aux changements 11.4 → 11.8 → 12.3. Les anciennes options supprimées `wsrep_load_data_splitting`, `big_tables`, `storage_engine` et `large_page_size` ne sont pas utilisées. Vérifier les configurations et SQL des modules tiers, notamment les nouveaux mots réservés `CONVERSION` et `TO_DATE`.
- `innodb_snapshot_isolation` devient activé par défaut dans 12.3. Pour conserver le comportement antérieur des transactions, Compose et la commande de référence Railway imposent **`--innodb-snapshot-isolation=OFF`**. Le test CI reproduit une mise à jour concurrente en REPEATABLE READ sur les deux versions. Ce choix conserve la compatibilité ; ce n’est pas une preuve d’absence de conflit sur toute charge métier.

## Choix de migration : export logique, base et volumes neufs

**Ne pas remplacer simplement l’image d’une base 11.4 existante par 12.3.3.** La procédure retenue est un export SQL applicatif et une restauration dans une nouvelle base initialisée par 12.3.3. Les tables système et utilisateurs de la nouvelle version sont créés par son image ; ne pas importer les bases `mysql`, `sys`, `performance_schema` ou `information_schema` de 11.4.

Cette procédure évite de convertir sur place le répertoire de données d’origine. Ne pas ajouter `MARIADB_AUTO_UPGRADE` pour contourner la migration. Une migration physique sur place est un autre processus : lire les deux guides officiels, vérifier les options et exécuter `mariadb-upgrade` selon ces guides. Elle n’est pas validée par ce dépôt.

### Préparer et répéter sur une copie

1. Identifier l’image exacte et les versions réellement exécutées de MariaDB et Dolibarr, les modules tiers, les paramètres et les deux volumes. Conserver le commit et les réglages de l’ancienne instance. Le tag flottant `11.4` ne suffit pas à identifier son patch.
2. Couper les écritures : utilisateurs, API, imports, tâches planifiées et accès public. Faire une sauvegarde cohérente et hors instance avec `dolibarr-backup --stdout`, puis une sauvegarde des deux volumes selon [OPERATIONS.md](OPERATIONS.md). Conserver SQL + documents + modules + clé d’instance ensemble, vérifier l’archive, sa taille, son empreinte et tester une restauration.
3. Créer un projet de restauration indépendant, privé, avec MariaDB `12.3.3`, sa **nouvelle** base vide, ses utilisateurs propres et un **nouveau** volume SQL. Garder le même Dolibarr 24.0.2 et les mêmes modules ; ne pas combiner une migration applicative avec celle du serveur SQL. Configurer le réglage de concurrence ci-dessus.
4. Suivre la [restauration complète](OPERATIONS.md) : importer uniquement `database.sql` comme utilisateur applicatif dans la base vide, puis restaurer `documents`, `custom`, `secrets` dans le nouveau volume applicatif. Restaurer la clé d’instance d’origine selon ce guide, sans la publier. Les secrets SQL du nouveau projet doivent correspondre à sa propre base.
5. Vérifier que le nouveau projet ne vise jamais l’ancienne base. Ne pas laisser l’installation automatique remplir la base avant l’import. Si la cible contient déjà des tables, interrompre et recréer une cible de restauration vide ; ne pas vider une base dont l’identité n’a pas été confirmée.
6. Contrôler le résultat de l’import et `mariadb-check --check` pour la seule base applicative. Les tables système sont neuves : il ne faut pas y restaurer les anciennes tables de privilèges ni lancer une réparation à l’aveugle.
7. Vérifier les nombres de lignes et les données, accents, montants, tiers, produits, stocks, commandes, factures, pièces jointes et clé. Tester la connexion avec un compte existant, les droits, une création/validation de facture, les PDF, les transactions et les modules tiers. Recréer les conteneurs de test pour vérifier la persistance. Contrôler les logs ; un simple healthcheck ne suffit pas.

### Bascule ultérieure, après autorisation distincte

Prévoir une fenêtre de maintenance. Refaire une sauvegarde finale cohérente après arrêt des écritures, répéter la restauration sur la cible, refaire les contrôles et basculer l’accès applicatif vers cette cible seulement après validation. Conserver l’ancienne instance arrêtée et intacte pendant la période de retour arrière. Ne pas laisser deux instances écrire après la bascule.

Aucun service, secret ou volume de production n’est modifié par cette PR ou par les tests. Une fusion GitHub ne met pas à jour l’image de base de données enregistrée dans un projet Railway existant, ni la fiche du template publié.

### Retour arrière

Avant toute nouvelle écriture sur la cible, le retour à l’ancienne instance 11.4 intacte reste possible, avec ses fichiers et sa clé cohérents. Après de nouvelles écritures sur 12.3, revenir à l’ancienne sauvegarde les perd : arrêter les écritures et traiter explicitement la récupération de ces opérations. Ne jamais démarrer 11.4 sur un volume ouvert par 12.3, ni considérer un changement de tag comme une restauration. La restauration doit réunir l’ancienne image, la base, les fichiers et la clé sauvegardés.

## Validation automatisée et limites

Le job **mariadb-migration** de `Verify Dolibarr deployment` utilise un projet Compose isolé et des secrets jetables, exclusivement sur GitHub Actions :

1. Installation réelle de Dolibarr 24.0.2 sous MariaDB **11.4.13**, puis création de tiers, factures, lignes, produit, stock et commande par les classes natives Dolibarr.
2. Test de concurrence REPEATABLE READ, arrêt du service applicatif, empreintes de toutes les lignes SQL et sauvegarde par le véritable outil du template.
3. Démarrage de la cible dans deux nouveaux volumes, refus d’importer une base non vide, import de la base applicative et restauration des fichiers.
4. Comparaison de chaque table (nombre et empreinte de toutes ses valeurs, sans conversion flottante), des empreintes des fichiers et de la clé ; contrôle des tables et nouveau test de concurrence.
5. Reprise de Dolibarr, génération native des scénarios de factures/commandes PDF puis recréation et contrôle de disponibilité. Seul un résumé sans données ni secrets est publié en artefact ; les volumes de test sont supprimés.

Le job **integration** teste séparément une installation neuve sous 12.3.3, la persistance, les écrans Indy et les PDF. Ces tests ne couvrent pas tous les modules tiers, toutes les anciennes versions 11.4 ni une charge concurrente de production. Une restauration de la vraie sauvegarde sur une copie et une recette métier restent nécessaires avant la bascule.

## Sources officielles

- [Versions MariaDB et correctifs stables](https://mariadb.org/mariadb/all-releases/).
- [Politique LTS et dates de maintenance](https://mariadb.org/about/).
- [Annonce 12.3 LTS et changement de concurrence](https://mariadb.org/mariadb-server-12-3-lts-released/).
- [Guide 11.4 → 11.8](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/upgrading/mariadb-community-server-upgrade-paths/upgrading-from-mariadb-11-4-to-mariadb-11-8).
- [Guide 11.8 → 12.3](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/upgrading/mariadb-community-server-upgrade-paths/upgrading-from-mariadb-11-8-to-mariadb-12-3).
- [Compatibilité des versions Dolibarr](https://wiki.dolibarr.org/index.php/Releases).
