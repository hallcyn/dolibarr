# Contenance et litres vendus sur une facture Indy

Le modèle de facture `indy` hérite de Sponge et conserve ses colonnes natives d’attributs supplémentaires des **lignes de facture** (`facturedet`). Il ne faut pas créer ces champs sur l’en-tête de facture ni sur les lignes de commande pour les retrouver automatiquement ici. La mise en forme Indy (couleurs, typographie et tableau) s’applique aux colonnes supplémentaires.

## Configuration dans Dolibarr 24.0.2

1. Dans chaque fiche produit, renseigner le **Volume** en **litres** : `0,75 L` pour une bouteille de 75 cl. La formule ci-dessous ne convertit pas les unités. Ne pas utiliser le champ Surface.
2. Ouvrir **Configuration → Modules/Applications → Factures et avoirs → Configuration → Attributs supplémentaires des lignes**.
3. Créer le champ suivant :

| Réglage | Valeur |
| --- | --- |
| Libellé | Litres vendus |
| Code | `litres_vendus` |
| Type | Nombre décimal (`double`) |
| Taille / précision | `24,8` si demandée ; le résultat est arrondi à deux décimales |
| Position | `52`, par exemple, avant les totaux monétaires |
| Visibilité | `1` |
| Affichage sur PDF | **`1`** pour une colonne native |
| Obligatoire | Non |
| Champ calculé | Formule ci-dessous, sans balises PHP |

```php
(
    ($objectoffield->fk_product > 0)
    && ($reloadedobj = new Product($db))
    && ($reloadedobj->fetchNoCompute($objectoffield->fk_product) > 0)
)
? round($reloadedobj->volume * $objectoffield->qty, 2)
: 0
```

Pour une seconde colonne **Contenance (L)**, créer `contenance_l`, de même type, position `50`, avec l’affichage PDF `1` et retirer `* $objectoffield->qty` de la formule.

`12 × 0,75 L = 9 L`. Une ligne libre sans produit associé retourne `0`. L’affichage numérique peut supprimer les zéros finaux : `9` et `9,00` représentent la même quantité.

## Génération et réglages PDF

Sur la facture, choisir le modèle **indy** dans le bloc de génération des documents, puis régénérer le PDF. Un PDF déjà généré ne change pas tout seul. Les détails des lignes doivent être visibles : l’option native de masquage des détails supprime aussi les colonnes supplémentaires.

Le champ « Affichage sur PDF » n’est pas une simple case oui/non dans cette version : `0` masque le champ ; `1`/`2` activent la colonne native ; `3`/`4` l’affichent dans la description au lieu d’une colonne. Choisir `1` pour ce guide. Les attributs non imprimables restent masqués par Indy.

Ces colonnes n’ajoutent pas automatiquement un total général de litres en bas de facture et ne changent ni les montants ni la TVA.

## Calcul dynamique et historique

Le champ calculé relit la fiche produit. Si sa contenance passe de `0,75 L` à `1 L`, les 12 unités donnent `12 L` lors d’une nouvelle lecture ou génération. Pour figer la contenance au moment de la vente, il faut un champ de ligne enregistré et alimenté à ce moment-là ; ce guide ne met pas ce mécanisme en place.

Aucun champ n’est créé automatiquement sur une installation de production. Cette configuration reste un choix de l’administrateur dans Dolibarr.

## Vérification automatisée

La CI génère réellement les PDF Indy avec des attributs calculés natifs : contenance `0,75`, quantité `12`, résultat `9`, ligne libre `0`, champ non imprimable, tableaux clair et quadrillé et régénération après changement de contenance. Elle vérifie les chiffres dans leurs colonnes et la conservation des totaux monétaires.

Références : [champs calculés Dolibarr](https://wiki.dolibarr.org/index.php/Extrafield_Champ_Calcul%C3%A9) et implémentation `CommonDocGenerator::defineColumnExtrafield()` / `pdf_sponge` de la version utilisée par le template.
