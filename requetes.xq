(: ═══════════════════════════════════════════════════════════
   FICHIER   : requetes.xq
   PROJET    : Club Info_Tech — TP XML / XSD / XQuery
   MOTEUR    : BaseX 10+  (XQuery 3.1)
   USAGE     : Ouvrir BaseX GUI → File → Open → club.xml
               puis exécuter chaque requête séparément.
   ═══════════════════════════════════════════════════════════ :)

(: ─────────────────────────────────────────────────────────
   Q1 — Liste complète des membres  (1 pt)
   Affiche id, nom complet, email et libellé de catégorie
   pour chaque membre.
───────────────────────────────────────────────────────── :)
<membres>
  {
    (: Parcourir tous les membres du document :)
    for $m in doc("club.xml")//membre

      (: Jointure : récupérer la catégorie correspondant à categorieRef :)
      let $cat := doc("club.xml")//categorie[@id = $m/@categorieRef]

    return
      <membre id="{string($m/@id)}">
        (: Concaténer prénom + nom :)
        <nomComplet>{ string($m/prenom) || " " || string($m/nom) }</nomComplet>
        <email>{ string($m/email) }</email>
        <categorie>{ string($cat/@libelle) }</categorie>
      </membre>
  }
</membres>


(: ─────────────────────────────────────────────────────────
   Q2 — Liste des concours organisés  (1 pt)
   Affiche titre, date, coefficient et libellé de catégorie,
   trié par date croissante.
───────────────────────────────────────────────────────── :)
<listeConcours>
  {
    (: Parcourir tous les concours individuels (ceux avec attribut @id) :)
    for $c in doc("club.xml")//concours[@id]

      (: Jointure : récupérer le libellé de la catégorie concernée :)
      let $cat := doc("club.xml")//categorie[@id = $c/@categorieRef]

    (: Trier par date croissante (xs:date pour un tri chronologique correct) :)
    order by xs:date($c/@date) ascending

    return
      <concours id="{string($c/@id)}">
        <titre>{ string($c/titre) }</titre>
        <date>{ string($c/@date) }</date>
        <coefficient>{ string($c/@coefficient) }</coefficient>
        <categorie>{ string($cat/@libelle) }</categorie>
      </concours>
  }
</listeConcours>


(: ─────────────────────────────────────────────────────────
   Q3 — Calcul du score de chaque participant  (2 pts)
   Formule : score = (complexite + tempsExecution) × coefficient
   Score arrondi à 2 décimales.
───────────────────────────────────────────────────────── :)
<resultats>
  {
    (: Parcourir chaque concours :)
    for $c in doc("club.xml")//concours[@id]
      let $coeff := xs:decimal($c/@coefficient)

    return
      <concours titre="{string($c/titre)}">
        {
          (: Parcourir chaque participant du concours :)
          for $p in $c/participants/participant

            (: Récupérer les valeurs numériques du participant :)
            let $complexite := xs:integer($p/complexite)
            let $temps      := xs:integer($p/tempsExecution)

            (: Appliquer la formule et arrondir à 2 décimales :)
            let $score := round(($complexite + $temps) * $coeff * 100) div 100

            (: Jointure pour afficher le nom du membre :)
            let $m := doc("club.xml")//membre[@id = $p/@membreRef]

          return
            <participant>
              <nom>{ string($m/nom) || " " || string($m/prenom) }</nom>
              <complexite>{ $complexite }</complexite>
              <tempsExecution>{ $temps }</tempsExecution>
              <score>{ $score }</score>
            </participant>
        }
      </concours>
  }
</resultats>


(: ─────────────────────────────────────────────────────────
   Q4 — Vainqueur de chaque concours  (2 pts)
   Participant ayant le score maximum.
   En cas d'égalité, tous les ex-aequo sont affichés.
───────────────────────────────────────────────────────── :)
<vainqueurs>
  {
    (: Parcourir chaque concours :)
    for $c in doc("club.xml")//concours[@id]
      let $coeff := xs:decimal($c/@coefficient)

      (: Calculer tous les scores du concours pour trouver le maximum :)
      let $tousLesScores :=
        for $p in $c/participants/participant
        return (xs:integer($p/complexite) + xs:integer($p/tempsExecution)) * $coeff

      (: Extraire le score maximum :)
      let $maxScore := max($tousLesScores)

    return
      <concours titre="{string($c/titre)}" scoreMax="{$maxScore}">
        {
          (: Filtrer les participants dont le score = score maximum (gestion des ex-aequo) :)
          for $p in $c/participants/participant
            let $score := (xs:integer($p/complexite) + xs:integer($p/tempsExecution)) * $coeff
            where $score = $maxScore
            let $m := doc("club.xml")//membre[@id = $p/@membreRef]
          return
            <gagnant>
              <nom>{ string($m/nom) }</nom>
              <prenom>{ string($m/prenom) }</prenom>
              <score>{ $score }</score>
            </gagnant>
        }
      </concours>
  }
</vainqueurs>


(: ─────────────────────────────────────────────────────────
   Q5 — Membres d'une catégorie donnée  (2 pts)
   Variable $categorie : changer le libellé pour filtrer.
   Résultat trié alphabétiquement par nom, puis par prénom.
───────────────────────────────────────────────────────── :)

(: ← Modifier cette valeur pour changer la catégorie cible :)
let $categorie := "Intelligence Artificielle"

(: Retrouver l'id de la catégorie correspondant au libellé :)
let $catId := doc("club.xml")//categorie[@libelle = $categorie]/@id

return
  <membres categorie="{$categorie}">
    {
      (: Filtrer les membres appartenant à la catégorie cible :)
      for $m in doc("club.xml")//membre[@categorieRef = $catId]

      (: Trier d'abord par nom, ensuite par prénom (ordre alphabétique) :)
      order by string($m/nom) ascending, string($m/prenom) ascending

      return
        <membre id="{string($m/@id)}">
          <nom>{ string($m/nom) }</nom>
          <prenom>{ string($m/prenom) }</prenom>
          <email>{ string($m/email) }</email>
        </membre>
    }
  </membres>
