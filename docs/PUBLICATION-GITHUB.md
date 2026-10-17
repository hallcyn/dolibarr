# Maintenir le dépôt GitHub

Le dépôt du template est [hallcyn/dolibarr](https://github.com/hallcyn/dolibarr).

## Modifier et vérifier

```bash
git clone https://github.com/hallcyn/dolibarr.git
cd dolibarr
git switch -c ma-modification
```

Modifier les fichiers nécessaires, puis exécuter les vérifications locales :

```bash
python3 scripts/render-template.py
python3 tests/check_repository.py
```

Le contrôle de dépôt utilise PyYAML. Avec Docker disponible, tester aussi le démarrage avec Compose, comme indiqué dans le README.

```bash
git add .
git commit -m "Describe the change"
git push -u origin ma-modification
```

Ouvrir ensuite une pull request vers `main`, vérifier le résultat du workflow **Verify Dolibarr deployment** et relire les changements avant fusion. Aucun mot de passe ou jeton ne doit être inscrit dans les fichiers.

## Publier le template

La publication GitHub ne crée pas automatiquement un template dans Railway. Suivre [RAILWAY.md](RAILWAY.md) pour enregistrer les deux services, leurs volumes et leurs variables, puis tester le déploiement avant publication. Les limites de validation sont consignées dans [VALIDATION.md](../VALIDATION.md).
