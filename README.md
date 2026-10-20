# Dolibarr sur Railway

[![Deploy on Railway](https://railway.com/button.svg)](https://railway.com/deploy/dolibarr-up-to-date-2026?referralCode=0fPTYP&utm_medium=integration&utm_source=template&utm_campaign=generic)

[![Vérification Docker](https://github.com/hallcyn/dolibarr/actions/workflows/verify.yml/badge.svg)](https://github.com/hallcyn/dolibarr/actions/workflows/verify.yml)

Déployez **Dolibarr ERP & CRM** avec une base **MariaDB privée**, une installation automatique et des fichiers conservés après redéploiement. Guide en français, textes du catalogue en anglais.

**Commencer : [Déployer le template](https://railway.com/deploy/YXlVeG) · [Guide complet](docs/RAILWAY.md).**

Pour un champ calculé de tiers indiquant sa dernière commande, avec une date historique manuelle en secours, voir [la formule et sa configuration](docs/COMPUTED-FIELDS.md).

> Statut : template créé dans le workspace Hallcyn, partageable par lien et prêt à déployer. Les tests Docker ont réussi ; aucune instance Railway n’a encore été lancée. Le template n’est pas encore référencé dans le marketplace. Voir [VALIDATION.md](VALIDATION.md).

## Ce qui est inclus

- Image officielle `dolibarr/dolibarr:24.0.2`, adaptée sans recopier le code de Dolibarr.
- MariaDB `11.4` LTS, utilisateur applicatif dédié, réseau privé et réglages sobres.
- [Thème Indy](theme/README.md) embarqué : menu crème, espace blanc et boutons roses, cartes et formulaires modernisés, activation dans les réglages d’affichage.
- [Documents Indy](modules/indyinvoice/README.md) : modèles PDF de factures et commandes avec palettes, bannière image, logo Dolibarr, polices, couleurs de texte et tableau configurables ; RIB et mentions issus des réglages natifs, sans conversion ODT.
- Secrets différents générés pour chaque déploiement du template ; aucun mot de passe universel.
- Documents, PDF, pièces jointes, modules externes et sessions PHP persistants.
- Configuration reconstruite depuis les variables ; clé d’instance stable et protégée contre une modification accidentelle.
- Contrôle de disponibilité vérifiant l’installation, la base et les répertoires de données.
- Planificateur facultatif toutes les cinq minutes, sans troisième service.
- Script de sauvegarde portable et procédures de restauration / mise à jour.
- Docker Compose pour tester sur son ordinateur et workflow de vérification sur GitHub.

Le logiciel conserve ses fonctionnalités natives : tiers, devis, factures, commandes, produits, stocks, achats, banques, projets, etc. Vous activez les modules utiles dans Dolibarr ; les modules tiers payants ne sont pas fournis.

## Les deux services

| Service | Source | Volume | Accès |
| --- | --- | --- | --- |
| **Dolibarr** | Dockerfile de ce dépôt | `/data` | HTTPS public, cible interne **80** |
| **MariaDB** | Image officielle `mariadb:11.4` | `/var/lib/mysql` | Réseau privé uniquement, **3306** |

Railway permet un seul volume par service. Le démarrage relie `/var/www/documents` à `/data/documents` et `/var/www/html/custom` à `/data/custom`. Les sessions sont dans `/data/sessions`, la clé dans `/data/secrets`. Le code de l’application reste dans l’image pour permettre les mises à jour. Ne monter aucun volume sur `/var/www/html` ou `/var/www`.

## Faut-il un dépôt GitHub ?

**Non.** Railway peut créer un template à partir d’images Docker publiques. Un dépôt est utile ici pour adapter la persistance au volume unique de Railway, conserver la configuration et publier une documentation maintenable. Il ne s’agit pas d’un fork complet de Dolibarr. Les personnes déployant le template n’ont pas besoin de cloner le dépôt sur leur ordinateur.

Un dépôt contenant un Dockerfile ne constitue pas, à lui seul, un template multi-service : il faut enregistrer les services, volumes, variables et réseau dans l’éditeur de templates Railway. [La procédure complète est ici](docs/RAILWAY.md).

## Guide

| Besoin | Document |
| --- | --- |
| Créer, publier puis utiliser le template | [RAILWAY.md](docs/RAILWAY.md) |
| Copier les variables et leurs descriptions | [VARIABLES.md](docs/VARIABLES.md) |
| Nom, description, présentation et tags du catalogue | [LISTING.md](docs/LISTING.md) |
| README anglais à coller sur la page Railway | [TEMPLATE-README.md](docs/TEMPLATE-README.md) |
| Sauvegarder, restaurer, mettre à jour, activer cron / e-mails | [OPERATIONS.md](docs/OPERATIONS.md) |
| Activer ou mettre à jour le thème Indy | [theme/README.md](theme/README.md) |
| Personnaliser les factures PDF et comprendre les modèles ODT | [modules/indyinvoice/README.md](modules/indyinvoice/README.md) |
| Configurer une petite activité et le stock de vin | [PREMIERS-PAS.md](docs/PREMIERS-PAS.md) |

## Surveillance des versions

Le workflow [Check upstream versions](https://github.com/hallcyn/dolibarr/actions/workflows/check-versions.yml) vérifie chaque jour à **05:17 UTC** les images stables Dolibarr et les versions MariaDB LTS encore maintenues. Il crée une issue par composant à examiner, actualise l’issue existante et la ferme lorsque les références du dépôt sont à jour. Il peut aussi être lancé manuellement depuis **Actions → Check upstream versions → Run workflow**.

Les mises à jour restent manuelles : aucune version du dépôt ni aucun service Railway n’est changé par cette CI. [Fonctionnement et limites](docs/OPERATIONS.md#surveillance-automatique-des-versions).

## Combien coûte Railway ?

Dolibarr et ce template sont gratuits ; **l’hébergement ne l’est pas nécessairement**. Au 17 octobre 2026, Railway annonce Hobby à **5 $/mois minimum** avec 5 $ d’usage inclus et Pro à **20 $/mois minimum** avec 20 $ inclus. Vérifier le plan adapté à votre usage, les taxes et les tarifs actuels avant de souscrire.

L’usage annoncé est notamment de 10 $/Go/mois de RAM, 20 $/vCPU/mois de CPU utilisé et 0,15 $/Go/mois de volume utilisé. Exemple purement indicatif : **0,60 Go de RAM moyenne au total + 0,03 vCPU moyen + 1 Go stocké ≈ 6,75 $/mois d’usage**, hors trafic, sauvegardes et taxes. Ce n’est pas une mesure de cette installation ni un plafond. Sur Hobby, cet exemple donnerait environ 6,75 $ au total, pas 5 $ + 6,75 $.

Le plan Free n’est pas une base pour cette architecture : elle demande **deux volumes**, tandis que Free est limité à un volume par projet selon la documentation consultée. L’essai est temporaire. Surveiller l’usage et configurer des alertes budgétaires ; une limite bloquante peut interrompre l’application.

**E-mails :** Railway réserve actuellement SMTP aux plans Pro et supérieurs. Sur Hobby, l’envoi direct des factures par SMTP nécessite une autre solution compatible ; ce template ne fournit pas de pont API e-mail. [Restrictions officielles](https://docs.railway.com/networking/outbound-networking).

Sources : [plans et tarifs Railway](https://docs.railway.com/pricing/plans), [volumes](https://docs.railway.com/volumes/reference).

## Test local facultatif

Prérequis : Docker avec Compose v2, Python 3.

```bash
git clone https://github.com/hallcyn/dolibarr.git
cd dolibarr
python3 scripts/setup-local.py
docker compose up --build -d
docker compose logs -f dolibarr
```

Attendre la fin de l’installation, puis ouvrir **http://localhost:8080**. Identifiant : `admin`. Lire le mot de passe dans le fichier `.env` sur votre ordinateur. Ne pas publier ce fichier.

```bash
docker compose ps
docker compose exec -T dolibarr curl -fsS http://localhost/railway-health.php
```

Les volumes nommés restent après `docker compose down`. L’option `down -v` les détruit : ne pas l’utiliser pour arrêter une installation contenant des données.

## Limites et validation

Une instance et une base, sans haute disponibilité : un redéploiement avec volume implique une courte interruption. Le contrôle HTTP Railway sert à valider le déploiement ; ajouter un moniteur externe si une surveillance continue est nécessaire. Aucun serveur SMTP, fournisseur d’e-mails, sauvegarde hors site automatique ou module commercial n’est installé implicitement. Voir [les opérations](docs/OPERATIONS.md).

Le [workflow réussi](https://github.com/hallcyn/dolibarr/actions/runs/39063382223) vérifie le démarrage avec MariaDB, l’installation, la persistance après recréation, les secrets de configuration et la sauvegarde. L’aperçu de déploiement Railway affiche les deux services, leurs volumes et zéro valeur manquante. La validation d’une instance réelle doit encore être effectuée avec la checklist du guide avant publication au marketplace. Une CI réussie ne prouve pas le fonctionnement d’une instance Railway.

## Sources et licences

- [Dolibarr](https://github.com/Dolibarr/dolibarr) et [image Docker officielle](https://github.com/Dolibarr/dolibarr-docker).
- [Créer un template Railway](https://docs.railway.com/templates/create), [bonnes pratiques](https://docs.railway.com/templates/best-practices), [publication](https://docs.railway.com/templates/publish-and-share).
- [Variables Railway](https://docs.railway.com/variables/reference), [réseau privé](https://docs.railway.com/networking/private-networking).

Sauf mention contraire, les scripts et documents du template sont sous licence MIT, voir [LICENSE](LICENSE). Les fichiers du thème Indy et ses tests importés dans `tests/indy/` sont sous GPL v3 ou ultérieure, voir [theme/LICENSE](theme/LICENSE). Le module `modules/indyinvoice/` est aussi sous GPL v3 ou ultérieure ; les polices Inter conservent leur licence SIL OFL. Dolibarr est un logiciel distinct sous GPL v3 ou ultérieure ; MariaDB et les composants de l’image conservent leurs licences. Ce template communautaire n’est pas une publication officielle de Dolibarr ou de Railway.

- [Destinataire de facturation personnalisé dans les PDF Indy](docs/BILLING-RECIPIENT.md)

- [Étiquettes enveloppes Indy : A4, 2 × 8 étiquettes de 105 × 37 mm](docs/ENVELOPE-LABELS.md)
