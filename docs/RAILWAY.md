# Créer le template Dolibarr sur Railway

Ce guide permet d’enregistrer un véritable template **Dolibarr + MariaDB**, puis de le déployer. Tout se fait depuis le navigateur. Les intitulés de l’interface Railway peuvent évoluer.

**Le template Hallcyn est déjà créé : [Déployer Dolibarr](https://railway.com/deploy/YXlVeG).** Pour l’utiliser, commencer à la section 5. Les sections 1 à 4 expliquent comment le reproduire ou le maintenir. Son [éditeur](https://railway.com/workspace/templates/1933a7e2-1c1e-4664-9dd3-92dc696d7645) est accessible aux membres autorisés du workspace. Le lien de partage fonctionne ; le référencement au marketplace attend la validation d’une instance réelle.

## 1. Ouvrir l’éditeur de template

1. Se connecter à [Railway](https://railway.com).
2. Ouvrir les paramètres du workspace, puis **Templates → New Template**.
3. Partir d’un template vide. **Créer le template ne nécessite pas de lancer un projet payant.** Un déploiement ultérieur peut entraîner une facturation.
4. Préparer deux services portant **exactement** les noms `MariaDB` et `Dolibarr`, car les références de variables utilisent ces noms.

Alternative si un projet de test existe déjà : **Project Settings → Generate Template from Project → Create Template**, puis vérifier toutes les variables et remplacer les secrets réels par des générateurs. Ne jamais publier les mots de passe du projet de test.

## 2. Ajouter MariaDB

Cliquer **Add New → Docker Image**.

| Champ | Valeur |
| --- | --- |
| Nom du service | `MariaDB` |
| Image | `mariadb:11.4` |
| Volume | `/var/lib/mysql` |
| Domaine HTTP | Aucun |
| TCP Proxy public | Désactivé |
| Replicas | `1` |
| Serverless / App Sleeping | Désactivé |
| Restart Policy | On Failure, 10 tentatives |

Pour attacher le volume : clic droit sur le service → **Attach Volume**, puis saisir `/var/lib/mysql`.

Dans **Settings → Start Command**, copier :

```text
docker-entrypoint.sh mariadbd --bind-address=0.0.0.0,:: --innodb-buffer-pool-size=128M --max-connections=30 --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
```

Cliquer **Save** après la saisie. Les réglages du service doivent ensuite être enregistrés avec **Apply** dans la barre des changements du template.

L’image conserve son entrypoint officiel. La commande explicite reste compatible avec Railway et active l’écoute sur IPv6 pour son réseau privé. Les 128 Mo sont la taille du cache InnoDB, pas une limite totale de mémoire.

Dans **Variables → Raw Editor**, copier le bloc **MariaDB** de [VARIABLES.md](VARIABLES.md). Ajouter aussi les descriptions des variables dans l’éditeur. Les deux mots de passe sont générés indépendamment ; ne jamais remplacer ces générateurs par `admin`, `root` ou un secret personnel.

## 3. Ajouter Dolibarr

Cliquer **Add New → GitHub Repo**, connecter GitHub à Railway si demandé et sélectionner **hallcyn/dolibarr**.

| Champ | Valeur |
| --- | --- |
| Nom du service | `Dolibarr` |
| Dépôt | `https://github.com/hallcyn/dolibarr` |
| Branche | `main` |
| Root Directory | `/` |
| Builder | Dockerfile, détecté depuis le dépôt |
| Build Command | Laisser vide |
| Start Command | **Laisser vide** pour conserver l’entrypoint et le CMD de l’image |
| Pre-deploy Command | Laisser vide : les volumes ne sont pas disponibles pour y initialiser les données |
| Healthcheck Path | `/railway-health.php` |
| Healthcheck Timeout | `300` secondes, valeur par défaut Railway |
| Volume | **`/data`** |
| Public Networking | HTTP activé, domaine généré |
| Target Port | **`80`** |
| Replicas | `1` |
| Serverless / App Sleeping | Désactivé |
| Restart Policy | On Failure, 10 tentatives |

Attacher le volume **avant le premier déploiement**. N’ajouter ni `/var/www/html`, ni `/var/www`, ni un second volume au même service.

Dans **Variables → Raw Editor**, copier le bloc **Dolibarr** de [VARIABLES.md](VARIABLES.md). Renseigner les descriptions de variables pour que les utilisateurs sachent quoi modifier. Les valeurs `secret(...)` sont des **fonctions de template** ; elles produisent de vrais secrets lorsque quelqu’un déploie ce template. Ne pas coller ces fonctions dans un projet déjà créé.

Les valeurs à personnaliser à la création sont principalement `DOLI_COMPANY_NAME` et `DOLI_COMPANY_COUNTRYCODE`. L’identifiant admin, le fuseau horaire et les limites de fichiers peuvent conserver leurs valeurs par défaut. Les références DB et les clés ne demandent aucune saisie à l’utilisateur.

## 4. Renseigner la fiche

- **Nom** : `Dolibarr`.
- **Description** : copier celle de [LISTING.md](LISTING.md).
- **Présentation / README** : copier le contenu de [TEMPLATE-README.md](TEMPLATE-README.md).
- **Catégorie** : `Other` dans l’éditeur actuel. Utiliser ERP, CRM, PHP et MariaDB dans les textes et les topics GitHub.
- **Source / site du projet** : `https://github.com/hallcyn/dolibarr`.
- **Icône** : utiliser le logo officiel Dolibarr en respectant son identité ; il ne faut pas laisser croire que le template est officiel.
- **Démo publique** : facultative. Ne jamais pointer une démo vers les données réelles de l’entreprise.

Dans **Settings**, cliquer **Stage changes** pour les informations de base. Dans **Marketplace**, choisir la catégorie et coller le README en conservant ses sections obligatoires. Cliquer **Apply** pour enregistrer les changements, puis **Share** pour obtenir le lien. À ce stade, le template peut être partagé sans être référencé au marketplace. Notre lien est `https://railway.com/deploy/YXlVeG`.

## 5. Déployer une instance de validation

1. Ouvrir l’URL du template et cliquer **Deploy** dans un nouveau projet.
2. Choisir le plan adéquat et la même région pour les deux services et leurs volumes, de préférence une région européenne pour un usage en France.
3. Renseigner le nom de l’entreprise ; vérifier le pays.
4. Vérifier dans l’aperçu qu’il existe **deux services et deux volumes**.
5. Déployer et attendre la fin du démarrage. MariaDB peut démarrer avant Dolibarr ; Dolibarr attend sa connexion jusqu’à 120 secondes avant de sortir en erreur et de pouvoir être redémarré.
6. Ouvrir **Dolibarr → Settings → Networking**. Vérifier que le domaine cible le port **80**. Si le domaine n’a pas été créé, cliquer **Generate Domain**.
7. Vérifier `DOLI_URL_ROOT` : la valeur résolue doit être l’URL HTTPS complète. Après ajout du domaine, redéployer si nécessaire pour actualiser la configuration.
8. Ouvrir le domaine. Se connecter avec `DOLI_ADMIN_LOGIN` et le mot de passe généré visible dans **Dolibarr → Variables → DOLI_ADMIN_PASSWORD**.
9. Changer ensuite le mot de passe depuis l’interface Dolibarr. La variable d’installation ne réinitialise pas un compte déjà créé.

## 6. Vérifier avant de publier

- [ ] MariaDB n’a ni domaine HTTP ni proxy TCP public.
- [ ] Chaque service possède son volume au chemin exact indiqué.
- [ ] L’URL HTTPS affiche la connexion et permet d’ouvrir la session admin.
- [ ] `/railway-health.php` renvoie `{"status":"ok"}`.
- [ ] La page d’installation est verrouillée après l’initialisation (`install.lock`).
- [ ] Créer un produit de test, joindre un fichier et générer un PDF.
- [ ] Redéployer Dolibarr : produit, PDF et pièce jointe sont toujours présents.
- [ ] Vérifier la conservation d’un module de test dans `/data/custom` si des modules externes sont utilisés.
- [ ] Exécuter et récupérer une sauvegarde ; vérifier son contenu, puis tester sa restauration sur une installation séparée.
- [ ] Si cron est annoncé actif, effectuer les étapes d’activation et contrôler une exécution réelle.
- [ ] Déployer une **seconde instance du template**, avec des volumes neufs : elle démarre et reçoit de nouveaux secrets sans reprendre ceux de la première.
- [ ] Examiner la consommation et les alertes budgétaires.

Le workflow GitHub vérifie l’image et Compose ; les étapes ci-dessus vérifient le paramétrage propre à Railway. Ne présenter le template comme validé sur Railway qu’après ce test réel.

## 7. Publier et ajouter le bouton GitHub

Après la validation réelle, ouvrir **Templates → Dolibarr → Marketplace → Publish to marketplace**. Les exigences de fiche sont déjà remplies. Cette action ajoute le template au catalogue ; elle est distincte de son partage par lien, déjà disponible.

Le bouton suivant, avec le vrai code, est déjà ajouté en haut du README :

```markdown
[![Deploy on Railway](https://railway.com/button.svg)](https://railway.com/deploy/YXlVeG)
```

Ce lien ouvre le template complet avec MariaDB et les deux volumes. Il n’engage aucune consommation tant que le déploiement n’est pas lancé.

## Domaine personnel

Dans Dolibarr → Networking, ajouter par exemple `gestion.example.com`, créer l’enregistrement DNS demandé et attendre le certificat. Définir ensuite `DOLI_URL_ROOT=https://gestion.example.com`, puis redéployer Dolibarr. Ne modifier ni les références privées MariaDB ni les volumes.

## Configuration comme code

La référence lisible [railway/template-spec.json](../railway/template-spec.json) regroupe les choix de ce template ; **Railway ne l’importe pas directement**. Les blocs de variables sont générés à partir de ce fichier.

Le parcours navigateur ci-dessus n’exige pas de `railway.json`. La documentation Railway consultée en octobre 2026 marque l’ancien Config as Code comme déprécié au profit de l’[Infrastructure as Code](https://docs.railway.com/infrastructure-as-code). Pour gérer ensuite un projet existant par CLI, utiliser `railway config pull`, puis `railway config plan`, inspecter le résultat et n’appliquer que les changements voulus. Ne pas ajouter un exemple incomplet qui pourrait supprimer un volume.

## Sources officielles

- [Création des templates](https://docs.railway.com/templates/create) : services, volumes, variables, générateurs et branches.
- [Publication et bouton](https://docs.railway.com/templates/publish-and-share).
- [Bonnes pratiques](https://docs.railway.com/templates/best-practices).
- [Volumes et restrictions](https://docs.railway.com/volumes/reference).
- [Image officielle Dolibarr](https://github.com/Dolibarr/dolibarr-docker).
