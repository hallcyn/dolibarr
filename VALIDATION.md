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

## Vérifications restant à exécuter

- Déploiement réel sur Railway : le template doit être créé dans le compte Railway et testé selon la checklist de `docs/RAILWAY.md`. La réussite sous Docker Compose ne valide pas le domaine, les volumes et les variables du projet Railway.
- Restauration réelle d’une sauvegarde sur une instance indépendante : la procédure est fournie, mais n’a pas été exécutée par ce workflow.
- Connexion utilisateur complète, génération de PDF et installation d’un module tiers : à vérifier dans l’instance de validation Railway.
- Planificateur facultatif : activer une tâche et contrôler une exécution réelle si cette fonctionnalité est utilisée.

## Vérification continue

Le workflow GitHub Actions `Verify Dolibarr deployment` est lancé après les modifications de la branche principale. Son résultat est visible dans [l’onglet Actions](https://github.com/hallcyn/dolibarr/actions).

Effectuer le déploiement de validation Railway avant de publier le template au catalogue.
