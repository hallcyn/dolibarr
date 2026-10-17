# Première configuration de Dolibarr

L’installation technique crée l’application et l’administrateur. Il reste à configurer l’entreprise et les modules métier. Les intitulés exacts peuvent varier selon la version et la langue.

## Configurer l’entreprise

Dans **Accueil → Configuration → Société / Organisation**, vérifier le nom, l’adresse, le pays, la devise, les identifiants administratifs et les coordonnées. Choisir le français dans les préférences de l’utilisateur et vérifier les dates et le fuseau horaire.

Les paramètres de facturation et de TVA doivent correspondre à votre situation réelle ; le template ne décide pas de votre régime fiscal. Faire valider la numérotation et les mentions nécessaires avant d’émettre les premières factures réelles.

## Activer seulement les modules utiles

Dans **Configuration → Modules / Applications**, commencer par :

| Module | Utilité |
| --- | --- |
| Tiers | Clients et fournisseurs |
| Produits | Les références vendues |
| Stocks | Entrepôts et mouvements |
| Factures clients | Facturation et paiements |
| Commandes clients | Suivi des commandes, si nécessaire |
| Fournisseurs / achats | Commandes et factures d’achat, si nécessaire |
| Banques / caisses | Comptes et règlements |

Les identifiants techniques d’activation automatique changent selon les modules ; ce template laisse cette sélection visible dans l’interface. Les paramètres pays et société sont renseignés avant l’activation.

## Exemple : dépôt avec moins de 20 références de vin

1. Créer un entrepôt nommé **Dépôt principal**.
2. Créer un produit par référence et millésime réellement distincts. Exemple : `VIN-ROUGE-DOMAINE-2024-75CL`.
3. Choisir la **bouteille** comme unité de stock si c’est l’unité physique suivie. Définir clairement qu’un carton contient six bouteilles si vous vendez aussi par carton.
4. Faire un inventaire de départ et saisir la quantité réellement présente pour chaque référence dans cet entrepôt. Ne pas recréer des mouvements d’achat fictifs pour forcer un stock.
5. Configurer le moment de décrémentation du stock selon le fonctionnement réel : commande, expédition ou facture, parmi les choix proposés. Vérifier le résultat sur une vente de test pour éviter un double mouvement.
6. Suivre les entrées de réapprovisionnement, les sorties, la casse et les corrections d’inventaire avec un motif.
7. Si la traçabilité par lot est nécessaire, activer et configurer le module correspondant avant de commencer à saisir des mouvements ; le simple nom du produit ne remplace pas un suivi des lots.

L’instance est indépendante de celle de votre frère : aucune connexion ou synchronisation avec son logiciel n’est configurée. Un même produit présent dans deux systèmes ne se synchronise pas automatiquement.

## Avant les premières données réelles

- Changer le mot de passe admin et créer un utilisateur quotidien avec les droits nécessaires.
- Configurer les modèles PDF, le logo et les coordonnées de règlement.
- Faire une facture de test dans une instance de test, générer son PDF et vérifier son apparence.
- Configurer et tester l’envoi d’e-mails avant d’envoyer des documents aux clients.
- Activer les sauvegardes des deux volumes et récupérer une première sauvegarde externe.
- Éviter de publier des données réelles dans les captures ou la démo du template.

Consulter [OPERATIONS.md](OPERATIONS.md) pour les sauvegardes, mises à jour et diagnostics.
