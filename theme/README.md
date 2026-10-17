# Thème Indy

Cette image embarque le thème communautaire [Indy pour Dolibarr](https://github.com/hallcyn/dolibarr-indy-theme) : navigation prune, accents violets, fond lavande, cartes et formulaires modernisés. Le code se trouve dans [indy/](indy/), sous licence [GPL v3 ou ultérieure](LICENSE).

## Activer

Après déploiement d'une image contenant ce dossier : **Accueil → Configuration → Affichage → Thème → Modifier → indy → Enregistrer**. Recharger la page, éventuellement avec un rechargement forcé. Le choix individuel d'un utilisateur, dans sa fiche onglet Affichage, est prioritaire sur ce choix global. Pour revenir au thème standard, sélectionner `eldy`.

L'ajout du thème ne change pas automatiquement une préférence existante. Le réglage est stocké en base et persiste après redéploiement. Sur une instance déjà déployée, reconstruire/déployer la nouvelle version du dépôt avant de chercher `indy` dans les réglages.

Le Dockerfile installe les fichiers dans `/var/www/html/theme/indy` et copie les images et configurations d'éditeurs depuis l'Eldy de la version Dolibarr embarquée. Le thème fait partie du code de l'image : aucun volume supplémentaire et aucun téléchargement GitHub au démarrage. Ne pas monter un volume sur le dossier des thèmes.

## Portée

Thème clair de l'interface web, prévu pour Dolibarr 24.0.2. Il conserve les fonctions, la navigation et les statuts de Dolibarr. Il ne modifie pas les modèles PDF des factures/devis et n'ajoute pas les services d'Indy. Des modules tiers peuvent nécessiter des ajustements CSS. Aucun logo, visuel propriétaire ni connexion au service Indy n'est inclus ; projet indépendant sans affiliation.

Inter est embarquée et servie par Dolibarr, sous licence [SIL Open Font License](indy/fonts/OFL.txt). La [CI du template a réussi](https://github.com/hallcyn/dolibarr/actions/runs/39075557178) : construction Docker, installation avec MariaDB 11.4, persistance et tests des vrais écrans du thème. Les captures Docker sont dans l'artefact `indy-screenshots` de cette exécution. Les [captures locales et résultats détaillés](https://github.com/hallcyn/dolibarr-indy-theme/blob/main/VALIDATION.md) sont aussi dans le dépôt source. Le workflow de ce dépôt source privé reste bloqué avant démarrage par la facturation ou le plafond GitHub Actions ; ce blocage n'affecte pas la validation publique du template.

## Mettre à jour la copie

[UPSTREAM.json](UPSTREAM.json) identifie le commit source et les empreintes SHA-256 des fichiers embarqués, contrôlées par `tests/check_repository.py`. Pour importer une version après examen du dépôt source :

```bash
python3 scripts/sync-indy-theme.py --ref COMMIT_COMPLET_DE_40_CARACTERES
python3 tests/check_repository.py
```

Le script ne change que les fichiers du thème, son installateur, sa licence et ses tests. Ouvrir une PR puis laisser la CI construire l'image, vérifier l'installation et produire les captures des vrais écrans Dolibarr avec des données fictives.

Prérequis : Git et Python 3. Le dépôt source étant privé, Git doit déjà disposer d'un accès de lecture à celui-ci. Les déploiements du template utilisent uniquement la copie embarquée et ne demandent aucun accès à ce dépôt privé.
