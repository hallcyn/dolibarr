# Date de dernière commande d’un tiers

Le template charge automatiquement la fonction `indyLastOrderDate` depuis sa configuration générée. Elle seule est ajoutée à la liste des fonctions de calcul autorisées de Dolibarr 24. Les méthodes SQL génériques, les concaténations et les autres protections du validateur restent restreintes.

## Configuration des attributs supplémentaires des tiers

1. Conserver/créer un champ **Date** non calculé, code `date_derniere_commande_manuelle`. Y saisir les dates historiques ; ne pas transformer ce champ en champ calculé.
2. Créer un second champ **Date**, par exemple `date_derniere_commande`, avec cette formule, sans balises PHP :

```php
indyLastOrderDate($objectoffield->id)
```

Si le champ manuel existant porte un autre code, le conserver et utiliser ce code en second argument, sans le préfixe `options_` :

```php
indyLastOrderDate($objectoffield->id, 'mon_code_manuel')
```

La fonction prend la plus récente **date de commande** (`date_commande`), pas la date de création ou de validation. Les commandes validées, en cours et clôturées sont retenues ; brouillons et commandes annulées sont exclus. La recherche concerne les commandes clients, avec les périmètres d’entités natifs de Dolibarr.

Sans commande éligible, elle reprend la date historique du même tiers. Sans date historique, elle renvoie une valeur vide (`null`). Elle ne modifie ni la date manuelle ni les commandes. Le résultat est recalculé aux prochains chargements Dolibarr, pas actualisé en direct dans une page déjà ouverte.

Le helper est disponible après déploiement du code et régénération normale de la configuration au démarrage. Ne pas modifier `conf.php` manuellement : le template le génère. Les installations du thème seul, hors de ce template, ne chargent pas ce helper automatiquement.

## Vérifications

La CI teste l’expression avec le véritable `dol_eval` et un attribut Date calculé persisté sur un tiers. Elle couvre la date historique, l’absence de date, les statuts de commande, la priorité de la date sur l’identifiant, l’isolation des entités/tiers externes et le rafraîchissement après changement de date. Les protections contre `??`, la lecture de fichiers et l’autorisation SQL générique restent actives.
