# Validation de la livraison

État au 17 octobre 2026.

## Vérifications réalisées

- Lecture de l’image Docker officielle Dolibarr et de son entrypoint pour la version 24.0.2.
- Vérification des procédures de création / publication des templates et des contraintes de volumes Railway.
- Validation du JSON de référence, des références entre services, de la correspondance des versions et des chemins de volumes.
- Vérification de la syntaxe Bash et Python, des liens Markdown locaux et du document de variables généré.
- Contrôle des restrictions SMTP documentées par Railway et de l’écoute IPv4 / IPv6 de MariaDB.

## Vérifications restant à exécuter

- Build Docker, analyse PHP et démarrage réel avec MariaDB : Docker et PHP ne sont pas disponibles dans l’environnement de préparation. Le workflow `.github/workflows/verify.yml` exécute ces vérifications sur un runner GitHub après publication du code.
- Vérification du template dans Railway : le template n’a pas encore été créé dans un compte Railway et aucun déploiement payant n’a été lancé. Suivre la checklist dans `docs/RAILWAY.md`.
- Restauration réelle d’une sauvegarde sur une instance indépendante : la procédure est fournie, mais n’a pas été exécutée ici.

## Vérification continue

Le workflow GitHub Actions `Verify Dolibarr deployment` est lancé après les modifications de la branche principale. Son résultat est visible dans [l’onglet Actions](https://github.com/hallcyn/dolibarr/actions). Une réussite valide l’image et la persistance sous Docker Compose, pas le paramétrage d’un projet Railway.

Effectuer ensuite le déploiement de validation Railway avant de publier le template au catalogue.
