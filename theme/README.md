# Thème Indy

Cette image embarque le thème communautaire [Indy pour Dolibarr](https://github.com/hallcyn/dolibarr-indy-theme) : menu vertical crème, fond blanc, boutons roses en pilule, cartes et formulaires aux contours fins. Le code se trouve dans [indy/](indy/), sous licence [GPL v3 ou ultérieure](LICENSE).

## Activer

Après déploiement d'une image contenant ce dossier : **Accueil → Configuration → Affichage → Thème → Modifier → indy → Enregistrer**. Recharger la page. Le template active le JavaScript natif du thème et renouvelle automatiquement la révision CSS quand sa version change. Pour renouveler le cache manuellement, réenregistrer `indy` au même endroit. Le choix individuel d'un utilisateur, dans sa fiche onglet Affichage, est prioritaire sur ce choix global. Pour revenir au thème standard, sélectionner `eldy`.

L'ajout du thème ne change pas automatiquement une préférence existante. Le réglage est stocké en base et persiste après redéploiement. Sur une instance déjà déployée, reconstruire/déployer la nouvelle version du dépôt avant de chercher `indy` dans les réglages.

Le Dockerfile installe les fichiers dans `/var/www/html/theme/indy` et copie les images et configurations d'éditeurs depuis l'Eldy de la version Dolibarr embarquée. Le thème fait partie du code de l'image : aucun volume supplémentaire et aucun téléchargement GitHub au démarrage. Ne pas monter un volume sur le dossier des thèmes.

## Portée

Version **1.5.3**. Sur ordinateur à partir de 1100 px, le menu principal standard de Dolibarr est présenté dans une colonne crème et les sous-menus dans une colonne dédiée, organisée par groupes et défilante indépendamment du contenu. Sur téléphone et tablette, une barre compacte ouvre les liens de modules, la recherche et les sous-menus natifs dans un tiroir. Le contenu utilise toute la largeur. Cette disposition concerne aussi les grandes tablettes tactiles jusqu’à 1366 px.

Cartes et listes entièrement encadrées, avec un contour fixe pendant le défilement horizontal ; filtres dans un panneau fermé et titres de widgets pouvant revenir à la ligne. Les filtres utilisent une grille de champs libellés et conservent les contrôles natifs. Sur téléphone, leur panneau est replié au chargement pour laisser voir les résultats ; toucher « Filtres de la liste » pour l’ouvrir. Les formulaires empilent les libellés et les champs. Les options Select2 et leurs descriptions de stock gardent un contraste lisible.

Thème clair de l'interface web, prévu pour Dolibarr 24.0.2. Il conserve les fonctions, la navigation et les statuts de Dolibarr. Il ne modifie pas les modèles PDF des factures/devis et n'ajoute pas les services d'Indy. Des modules tiers peuvent nécessiter des ajustements CSS. Aucun logo, visuel propriétaire ni connexion au service Indy n'est inclus ; projet indépendant sans affiliation.

Inter est embarquée et servie par Dolibarr, sous licence [SIL Open Font License](indy/fonts/OFL.txt). La [CI du template](https://github.com/hallcyn/dolibarr/actions/workflows/verify.yml) construit l'image Docker, vérifie l'installation avec MariaDB 11.4, la persistance et les vrais écrans du thème : 47 vues sur ordinateur, 12 cas sur tablette, 13 vues mobiles redimensionnées et 68 cas tactiles (iPhone/Android émulés dans Chromium, de 320 à 390 px et en paysage), filtres visibles, onglets compacts, compte utilisateur et tiroir mobile. Un audit complémentaire parcourt 30 vues à 1440/768/390 px et soumet réellement les recherches, dates et réinitialisations. Les captures sont dans l'artefact `indy-screenshots` des exécutions. Les [captures locales et résultats détaillés](https://github.com/hallcyn/dolibarr-indy-theme/blob/main/VALIDATION.md) sont aussi dans le dépôt source.

## Mettre à jour la copie

[UPSTREAM.json](UPSTREAM.json) identifie le commit source et les empreintes SHA-256 des fichiers embarqués, contrôlées par `tests/check_repository.py`. Pour importer une version après examen du dépôt source :

```bash
python3 scripts/sync-indy-theme.py --ref COMMIT_COMPLET_DE_40_CARACTERES
python3 tests/check_repository.py
```

Le script ne change que les fichiers du thème, son installateur, sa licence et ses tests. Ouvrir une PR puis laisser la CI construire l'image, vérifier l'installation et produire les captures des vrais écrans Dolibarr avec des données fictives.

Prérequis : Git et Python 3. Le dépôt source étant privé, Git doit déjà disposer d'un accès de lecture à celui-ci. Les déploiements du template utilisent uniquement la copie embarquée et ne demandent aucun accès à ce dépôt privé.

Les infobulles natives, leurs tableaux et leurs liens utilisent une surface claire lisible depuis la version 1.4.1. Leur largeur est contrôlée à 320/390 px ; l’aide ouverte au toucher reste native.


Depuis la version 1.5, les listes compatibles deviennent des cartes sur téléphone, avec tri, sélection et champs complémentaires dépliables. Le choix des colonnes reste accessible. Les champs tactiles font au moins 44 px, avec une police de 16 px ; l’ajout de lignes est séparé du tableau horizontal sur téléphone, tout en conservant le formulaire et les calculs natifs. Les tableaux techniques conservent un défilement interne indiqué à l’écran.

La CI ajoute une passe sur les inventaires de pages existants à 320, 390, 768, 844, 1024 et 1366 px dans des contextes tactiles. Elle vérifie la recherche, les filtres, le compte, la navigation, le tri, la sélection et une vraie soumission de ligne dans les données fictives. Six vues ordinateur sont comparées aux sources acceptées 1.4.1 à 1280, 1440 et 1920 px ; seuls les bitmaps animés des graphiques sont masqués. La restauration des éléments natifs après changement de format est aussi contrôlée. Les rapports distinguent les pages indisponibles ; il ne s’agit pas de tests sur Safari ou appareils physiques.

La comparaison du bureau conserve les mêmes dimensions et tolère uniquement un très faible bruit d’anticrénelage de Chromium : au maximum 200 pixels, avec un écart de 2 niveaux sur 255 par canal. Les métriques sont enregistrées avec les captures ; les changements plus importants font échouer le test.

Connexion mobile : champs et bouton en pleine largeur, icônes intégrées et commande de visibilité du mot de passe agrandie. Les tuiles du menu gardent une taille d’icône fixe et affichent les libellés dessous. Ces corrections restent limitées au responsive ; la connexion ordinateur rejoint les comparaisons visuelles de régression.

Compte mobile et tablette : bouton d’avatar centré, panneau lisible avec profil, carte de visite et déconnexion nommés, informations d’entreprise et de session repliables. Les actions et les dialogues natifs sont conservés ; le menu respecte la hauteur disponible et dispose d’une fermeture explicite.

Le compagnon natif IndyUI est installé et activé au démarrage pour charger un JavaScript identifié par son empreinte, évitant l’association d’un nouveau CSS à un ancien script en cache. La fiche utilisateur dispose d’un sélecteur de rubriques sur petit écran. La carte virtuelle reçoit une fenêtre adaptée, une copie du lien, des options lisibles et un aperçu agrandi ; les formulaires et les choix de visibilité restent natifs.
