# Thème Indy

Cette image embarque le thème communautaire [Indy pour Dolibarr](https://github.com/hallcyn/dolibarr-indy-theme) : menu vertical crème, fond blanc, boutons roses en pilule, cartes et formulaires aux contours fins. Le code se trouve dans [indy/](indy/), sous licence [GPL v3 ou ultérieure](LICENSE).

## Activer

Après déploiement d'une image contenant ce dossier : **Accueil → Configuration → Affichage → Thème → Modifier → indy → Enregistrer**. Recharger la page. Après une mise à jour du thème déjà sélectionné, réenregistrer `indy` au même endroit : cela renouvelle la révision CSS pour tous les utilisateurs. Sans cette étape, le CSS précédent peut rester en cache pendant trois heures. Le choix individuel d'un utilisateur, dans sa fiche onglet Affichage, est prioritaire sur ce choix global. Pour revenir au thème standard, sélectionner `eldy`.

L'ajout du thème ne change pas automatiquement une préférence existante. Le réglage est stocké en base et persiste après redéploiement. Sur une instance déjà déployée, reconstruire/déployer la nouvelle version du dépôt avant de chercher `indy` dans les réglages.

Le Dockerfile installe les fichiers dans `/var/www/html/theme/indy` et copie les images et configurations d'éditeurs depuis l'Eldy de la version Dolibarr embarquée. Le thème fait partie du code de l'image : aucun volume supplémentaire et aucun téléchargement GitHub au démarrage. Ne pas monter un volume sur le dossier des thèmes.

## Portée

Version **1.3.0**. Sur ordinateur à partir de 1100 px, le menu principal standard de Dolibarr est présenté dans une colonne crème et les sous-menus dans une colonne dédiée, organisée par groupes et défilante indépendamment du contenu. Les écrans plus petits utilisent la navigation native.

Cartes et listes entièrement encadrées, avec un contour fixe pendant le défilement horizontal ; filtres dans un panneau fermé et titres de widgets pouvant revenir à la ligne. Sur téléphone, les formulaires facture et commande affichent les libellés au-dessus des champs.

Thème clair de l'interface web, prévu pour Dolibarr 24.0.2. Il conserve les fonctions, la navigation et les statuts de Dolibarr. Il ne modifie pas les modèles PDF des factures/devis et n'ajoute pas les services d'Indy. Des modules tiers peuvent nécessiter des ajustements CSS. Aucun logo, visuel propriétaire ni connexion au service Indy n'est inclus ; projet indépendant sans affiliation.

Inter est embarquée et servie par Dolibarr, sous licence [SIL Open Font License](indy/fonts/OFL.txt). La [CI du template](https://github.com/hallcyn/dolibarr/actions/workflows/verify.yml) construit l'image Docker, vérifie l'installation avec MariaDB 11.4, la persistance et les vrais écrans du thème : 47 vues sur ordinateur, 12 cas sur tablette, 13 vues mobiles redimensionnées et 68 cas tactiles (iPhone/Android émulés dans Chromium, de 320 à 390 px et en paysage), filtres visibles, onglets compacts, compte utilisateur et tiroir mobile. Les captures sont dans l'artefact `indy-screenshots` des exécutions. Les [captures locales et résultats détaillés](https://github.com/hallcyn/dolibarr-indy-theme/blob/main/VALIDATION.md) sont aussi dans le dépôt source.

## Mettre à jour la copie

[UPSTREAM.json](UPSTREAM.json) identifie le commit source et les empreintes SHA-256 des fichiers embarqués, contrôlées par `tests/check_repository.py`. Pour importer une version après examen du dépôt source :

```bash
python3 scripts/sync-indy-theme.py --ref COMMIT_COMPLET_DE_40_CARACTERES
python3 tests/check_repository.py
```

Le script ne change que les fichiers du thème, son installateur, sa licence et ses tests. Ouvrir une PR puis laisser la CI construire l'image, vérifier l'installation et produire les captures des vrais écrans Dolibarr avec des données fictives.

Prérequis : Git et Python 3. Le dépôt source étant privé, Git doit déjà disposer d'un accès de lecture à celui-ci. Les déploiements du template utilisent uniquement la copie embarquée et ne demandent aucun accès à ce dépôt privé.
