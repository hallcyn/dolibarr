# Destinataire de facturation des PDF Indy

Sur la fiche du **tiers → Modifier**, renseigner **Destinataire de facturation** (code `destinataire_facturation`, texte facultatif de 255 caractères).

Exemple : conserver `ALBER Frédéric et Isabelle` comme nom de tiers pour le tri, puis saisir `Mme Isabelle ALBER et M. Frédéric ALBER` dans ce champ.

Les modèles **indy** (factures/avoirs) et **indyorder** (commandes) remplacent le nom des blocs de facturation et d’expédition par ce texte, sur chaque en-tête affiché. Un champ absent, vide ou composé d'espaces conserve le comportement natif. Les contacts associés, leurs noms, les adresses de facturation/livraison et le nom du tiers enregistré restent inchangés. Pour une livraison à un autre tiers, le bloc expédition utilise le destinataire Indy de ce tiers, ou son nom natif si le champ est vide. Un contact de facturation reste affiché sous le destinataire s'il a été associé au document ; ne pas en associer si cette ligne supplémentaire n'est pas souhaitée.

Enregistrer le tiers, puis **régénérer le document** avec le modèle Indy : les PDF déjà générés ne sont pas réécrits automatiquement. Les modèles standards ne sont pas modifiés. Le champ est dynamique : sa modification sera reprise lors des futures régénérations, y compris celles de documents existants.

Le champ natif est créé à l'activation du module et, pour les installations Railway où Indy est déjà actif, au prochain démarrage. Une définition ou une valeur existante n'est jamais écrasée. Désactiver le module conserve les données saisies.
