(: ═══════════════════════════════════════════════════════════
   FICHIER   : updates.xq
   PROJET    : Club Info_Tech — TP XML / XSD / XQuery
   MOTEUR    : BaseX 10+  (XQuery Update)
   USAGE     : Ouvrir BaseX GUI → Database → Open & Manage → TP1
               puis exécuter chaque requête séparément (une à la fois).
   ═══════════════════════════════════════════════════════════ :)


(: ─────────────────────────────────────────────────────────
   U1 — Ajouter un nouveau participant à un concours  (2 pts)
   Insère le membre M009 (Mansouri Tarek) dans le concours CO1.
   Formule : (88 + 95) × 1.5 = 274.50
───────────────────────────────────────────────────────── :)
let $concours := db:get("TP1", "club.xml")//concours[@id = "CO1"]
return
  insert node
    <participant membreRef="M009">
      <complexite>88</complexite>
      <tempsExecution>95</tempsExecution>
    </participant>
  as last into $concours/participants


(: ─────────────────────────────────────────────────────────
   U2 — Modifier le coefficient d'un concours  (1 pt)
   Met à jour le coefficient du concours CO2 : 1.8 → 2.0
───────────────────────────────────────────────────────── :)
let $concours := db:get("TP1", "club.xml")//concours[@id = "CO2"]
return
  replace value of node $concours/@coefficient with "2.0"


(: ─────────────────────────────────────────────────────────
   U3 — Supprimer un participant d'un concours  (1 pt)
   Supprime la participation du membre M001 dans le concours CO1.
───────────────────────────────────────────────────────── :)
let $participant :=
  db:get("TP1", "club.xml")//concours[@id = "CO1"]
    /participants/participant[@membreRef = "M001"]
return
  delete node $participant


(: ─────────────────────────────────────────────────────────
   U4 — Ajouter un nouveau membre au club  (2 pts)
   Ajoute le membre M010 dans la catégorie C2 (Développement Web).
───────────────────────────────────────────────────────── :)
let $membres := db:get("TP1", "club.xml")//membres
return
  insert node
    <membre id="M010" categorieRef="C2">
      <nom>Djouadi</nom>
      <prenom>Fouad</prenom>
      <email>f.djouadi@club.dz</email>
    </membre>
  as last into $membres
