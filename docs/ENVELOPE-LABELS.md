# Étiquettes enveloppes Indy

Module **Étiquettes Indy**, livré dans le template, pour des planches A4 de **16 étiquettes de 105 × 37 mm** : 2 colonnes × 8 lignes (référence indiquée : 143.355). L’image du paquet ne permet pas de vérifier les tolérances du fabricant : tester une feuille ordinaire devant la planche avant un tirage complet.

## Utilisation

1. Ouvrir **Tiers → Étiquettes enveloppes**. Si nécessaire, activer **Étiquettes Indy** dans Configuration → Modules/Applications. Un administrateur y a accès ; pour les autres utilisateurs, attribuer le droit du module ainsi que la lecture des tiers. Les restrictions commerciales et d’entité sont conservées. Les utilisateurs externes n’y ont pas accès.
2. Rechercher et sélectionner un ou plusieurs tiers. **Tout sélectionner** choisit tous les tiers accessibles de la liste ; **Vider la sélection** les retire. Jusqu’à 5 000 tiers par téléchargement. La sélection de groupes évite les clics individuels : choisir une année et éventuellement une catégorie, puis **Remplacer la sélection par ce groupe** ou **Ajouter ce groupe à la sélection**.
3. Choisir l’emplacement de départ (1 à 16) pour une planche entamée.
4. **Vérifier les adresses**, puis **Télécharger le PDF**.
5. Imprimer sur A4 en **taille réelle / 100 %**, sans « ajuster », sans mise à l’échelle ni en-têtes ajoutés par le navigateur.

## Contenu et mise en page

- Ligne 1 : attribut du tiers `destinataire_facturation` (nom Indy), ou nom du tiers si vide.
- Ligne 2 : numéro et rue (adresse du tiers ; retours à la ligne ramenés à des espaces).
- Ligne 3 : code postal et ville du tiers.

Une étiquette par tiers, sans doublons, triées par nom du tiers. Les contacts ne créent pas d’étiquettes supplémentaires. Les adresses proviennent de la fiche tiers, pas des contacts de livraison particuliers aux commandes. Aucune civilité n’est inventée.

Format PDF : 210 × 297 mm, colonnes de 105 mm, lignes de 37 mm, marge haute/basse de 0,5 mm, marge gauche/droite de 0 mm. Le texte bénéficie de **10 mm de marge intérieure à gauche par défaut** (réglable entre 5 et 20 mm) et de 5 mm à droite et est placé au centre en hauteur. Remplissage de gauche à droite, puis de haut en bas ; page suivante après 16 emplacements. Les emplacements précédant le départ restent vierges sur la première feuille seulement.

Les sauts de ligne de l’adresse (retours à la ligne, `<br>`, `<br/>` ou `<br />`) sont conservés, y compris les balises encodées lors d’un import. Le destinataire reste en gras et le code postal/ville sur la dernière ligne. Le bloc accepte jusqu’à six lignes au total et se recentre verticalement dans l’étiquette. Au-delà, un message demande de raccourcir l’adresse. Le texte est réduit si nécessaire en largeur (minimum 6 pt). Une adresse incomplète ou une ligne encore trop longue bloque le téléchargement avec un message, plutôt que de tronquer silencieusement ou de sauter un destinataire. L’aperçu signale toutes les adresses incomplètes et fournit des liens vers les fiches à corriger. Il est aussi possible de cocher explicitement **Exclure les tiers dont l’adresse est incomplète** ; aucun destinataire n’est écarté par défaut. Le PDF est téléchargé directement : aucun fichier client ni document métier n’est enregistré ou modifié.

## Installation

Le module indépendant `indylabels` utilise le même champ natif que Documents Indy, dont le code est livré avec le template. Il est activé une fois au démarrage des installations où Documents Indy est déjà actif. Une désactivation ultérieure est respectée (`INDYLABELS_PROVISIONED`). Aucun secret, volume ou document existant n’est modifié.

## Vérifications

Les tests créent des tiers fictifs dans une base jetable, génèrent et extraient les PDF de 1, 16, 17, 33 et **800 destinataires (50 pages)** ainsi qu’une planche entamée, mesurent le format A4 et les limites de chaque étiquette, puis vérifient la sélection, l’aperçu, le téléchargement et le mobile dans un vrai navigateur. Contrôles des droits, entités, doublons, champs vides et protection CSRF inclus.

## Sélections par groupe

- **Dernière commande sur l’année (historique inclus)** : année de la dernière commande réelle datée et validée/en cours/clôturée. Si aucune commande réelle datée n’existe, utilise le champ manuel `date_derniere_commande_manuelle` : année entière ou champ date/date-heure. Une commande réelle plus récente l’emporte toujours sur l’historique manuel. Un champ manuel calculé n’est pas utilisé.
- **Ayant une commande sur l’année** : au moins une commande réelle enregistrée dans cette année, même si une commande plus récente existe. L’historique importé n’est pas pris en compte dans ce mode, car une année de dernière commande ne décrit pas toutes les commandes passées.
- **Tous les tiers accessibles** : aucun filtre de commande.

Dans chaque mode, une catégorie de tiers peut être ajoutée si le module Catégories est activé. Il s’agit d’une catégorie exacte, sans inclure automatiquement ses sous-catégories. Les droits commerciaux et les entités s’appliquent à chaque groupe. Les commandes annulées et brouillons ne sont jamais comptées. L’année proposée par défaut est l’année précédente.

Les groupes peuvent remplacer ou compléter la sélection ; les doublons sont supprimés. Les tiers sont ensuite ajustables individuellement. Le navigateur transmet les identifiants dans un champ compact pour éviter la limite PHP sur le nombre de variables de formulaire ; 800 ou plusieurs milliers de tiers ne sont pas tronqués à 1 000 champs. Les adresses sont lues par une requête groupée, sans évaluer les autres champs calculés du tiers à chaque étiquette.

Pour déplacer davantage le texte vers la droite, augmenter **Marge intérieure gauche de chaque étiquette (mm)**. La géométrie de la planche ne change pas. Ce réglage vaut pour les deux colonnes et réduit la largeur de texte disponible. Toujours imprimer à 100 %.
