# Étiquettes enveloppes Indy

Module **Étiquettes Indy**, livré dans le template, pour des planches A4 de **16 étiquettes de 105 × 37 mm** : 2 colonnes × 8 lignes (référence indiquée : 143.355). L’image du paquet ne permet pas de vérifier les tolérances du fabricant : tester une feuille ordinaire devant la planche avant un tirage complet.

## Utilisation

1. Ouvrir **Tiers → Étiquettes enveloppes**. Si nécessaire, activer **Étiquettes Indy** dans Configuration → Modules/Applications. Un administrateur y a accès ; pour les autres utilisateurs, attribuer le droit du module ainsi que la lecture des tiers. Les restrictions commerciales et d’entité sont conservées. Les utilisateurs externes n’y ont pas accès.
2. Rechercher et sélectionner un ou plusieurs tiers. **Tout sélectionner** choisit tous les tiers accessibles de la liste ; **Vider la sélection** les retire. Jusqu’à 500 tiers par téléchargement.
3. Choisir l’emplacement de départ (1 à 16) pour une planche entamée.
4. **Vérifier les adresses**, puis **Télécharger le PDF**.
5. Imprimer sur A4 en **taille réelle / 100 %**, sans « ajuster », sans mise à l’échelle ni en-têtes ajoutés par le navigateur.

## Contenu et mise en page

- Ligne 1 : attribut du tiers `destinataire_facturation` (nom Indy), ou nom du tiers si vide.
- Ligne 2 : numéro et rue (adresse du tiers ; retours à la ligne ramenés à des espaces).
- Ligne 3 : code postal et ville du tiers.

Une étiquette par tiers, sans doublons, triées par nom du tiers. Les contacts ne créent pas d’étiquettes supplémentaires. Les adresses proviennent de la fiche tiers, pas des contacts de livraison particuliers aux commandes. Aucune civilité n’est inventée.

Format PDF : 210 × 297 mm, colonnes de 105 mm, lignes de 37 mm, marge haute/basse de 0,5 mm, marge gauche/droite de 0 mm. Le texte bénéficie de 5 mm de marge interne horizontale et est placé au centre en hauteur. Remplissage de gauche à droite, puis de haut en bas ; page suivante après 16 emplacements. Les emplacements précédant le départ restent vierges sur la première feuille seulement.

Le texte est réduit si nécessaire pour tenir sur trois lignes (minimum 6 pt). Une adresse incomplète ou une ligne encore trop longue bloque le téléchargement avec un message, plutôt que de tronquer silencieusement ou de sauter un destinataire. Corriger la fiche ou retirer le tiers de la sélection. Le PDF est téléchargé directement : aucun fichier client ni document métier n’est enregistré ou modifié.

## Installation

Le module indépendant `indylabels` utilise le même champ natif que Documents Indy, dont le code est livré avec le template. Il est activé une fois au démarrage des installations où Documents Indy est déjà actif. Une désactivation ultérieure est respectée (`INDYLABELS_PROVISIONED`). Aucun secret, volume ou document existant n’est modifié.

## Vérifications

Les tests créent des tiers fictifs dans une base jetable, génèrent et extraient les PDF de 1, 16, 17 et 33 destinataires ainsi qu’une planche entamée, mesurent le format A4 et les limites de chaque étiquette, puis vérifient la sélection, l’aperçu, le téléchargement et le mobile dans un vrai navigateur. Contrôles des droits, entités, doublons, champs vides et protection CSRF inclus.
