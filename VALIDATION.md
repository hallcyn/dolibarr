# Validation de la livraison

État au 17 octobre 2026.

## Vérifications réalisées

- Lecture de l’image Docker officielle Dolibarr et de son entrypoint pour la version 24.0.2.
- Vérification des procédures de création / publication des templates et des contraintes de volumes Railway.
- Validation du JSON de référence, des références entre services, des versions et des chemins de volumes.
- Vérification de la syntaxe Bash et Python, des liens Markdown locaux et du document de variables généré.
- Contrôle des restrictions SMTP documentées par Railway et de l’écoute IPv4 / IPv6 de MariaDB.

## Vérification Docker réussie sur GitHub Actions

Le [workflow de validation](https://github.com/hallcyn/dolibarr/actions/runs/39063382223) a réussi pour le commit `3f3d07056095450384550828fb0ff044850da795`.

Il a vérifié :

- La construction de l’image depuis la source officielle Dolibarr.
- La syntaxe des fichiers PHP et la configuration Apache.
- L’installation automatique sur une base MariaDB neuve et l’affichage de la page de connexion.
- Le contrôle HTTP de disponibilité et la présence d’un administrateur actif.
- La conservation des données SQL, documents, fichiers de modules et sessions après recréation des conteneurs.
- L’absence de volumes anonymes hérités de l’image officielle.
- Le contenu de l’archive de sauvegarde : export SQL, documents, modules, version et clé d’instance.
- Le retour HTTP 503 lorsque MariaDB est indisponible, puis la reprise après son redémarrage.

## Template Railway créé et vérifié

- Template **Dolibarr** créé dans le workspace Hallcyn : https://railway.com/deploy/YXlVeG.
- Identifiant : `1933a7e2-1c1e-4664-9dd3-92dc696d7645` ; code : `YXlVeG`.
- Deux sources : `hallcyn/dolibarr` et `mariadb:11.4`.
- Deux volumes : `/data` et `/var/lib/mysql`.
- Références DB privées, domaine HTTP généré sur le port 80 et contrôle `/railway-health.php` enregistrés.
- 27 variables préconfigurées, toutes décrites. L’écran de déploiement affiche **0 valeur manquante** pour chaque service.
- Les quatre recommandations de l’éditeur Railway sont satisfaites : icônes, contrôle de santé, descriptions et réseau privé.
- Nom, description courte de 73 caractères, icône, catégorie Other et présentation complète enregistrés.
- Lien de partage ouvert et formulaire de déploiement inspecté sans cliquer sur Deploy.
- Aucun projet ni service facturé créé. Le template est partageable par lien, mais **non référencé au marketplace**.

## Vérifications restant à exécuter

- Déploiement réel sur Railway : utiliser le template créé et suivre la checklist de `docs/RAILWAY.md`. La réussite sous Docker Compose et l’inspection du formulaire ne valident pas le fonctionnement d’une instance Railway.
- Restauration réelle d’une sauvegarde sur une instance indépendante : la procédure est fournie, mais n’a pas été exécutée par ce workflow.
- Connexion utilisateur complète, génération de PDF et installation d’un module tiers : à vérifier dans l’instance de validation Railway.
- Planificateur facultatif : activer une tâche et contrôler une exécution réelle si cette fonctionnalité est utilisée.

## Vérification continue

Le workflow GitHub Actions `Verify Dolibarr deployment` est lancé après les modifications de la branche principale. Son résultat est visible dans [l’onglet Actions](https://github.com/hallcyn/dolibarr/actions).

Effectuer le déploiement de validation Railway avant de publier le template au catalogue.
