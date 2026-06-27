# ADR 0001 — SVG + Theatre.js plutôt que Rive

- **Statut :** Accepté
- **Date :** 2026-06-27
- **Décideurs :** porteur du projet
- **Références :** cahier des charges §4.3 (objectifs professionnels), §5.1
  (décision arrêtée), §15 (choix du système visuel), §17 (architecture
  d'animation)

## Contexte

Le Canard du Vendredi a besoin d'un personnage animé dont l'apparence (posture,
regard, vapeur, accessoires, incident de surcaféination) évolue **à partir de
données validées côté serveur** : l'énergie collective `0→100`, l'accessoire
gagnant du vote, et des animations ponctuelles (service d'un café, révélation
d'accessoire).

Au-delà du rendu, le projet est explicitement un **laboratoire technique de
démonstration**. Il doit pouvoir être présenté à un autre développeur, et l'un de
ses objectifs professionnels (§4.3) est de **savoir expliquer pourquoi
SVG + Theatre.js a été préféré à Rive**, comment l'animation est séparée de la
logique métier, et comment le backend reste la source de vérité.

Deux familles de solutions étaient envisageables :

1. **Rive** — outil de création d'animations interactives avec une excellente
   expérience d'édition et une *state machine* intégrée.
2. **SVG structuré animé par Theatre.js** — format graphique natif du web piloté
   par un moteur d'animation open source, séquences exportées en JSON versionné.

## Décision

Nous retenons la chaîne **SVG inline + Theatre.js** :

```text
Inkscape ou Figma
        ↓
SVG structuré (groupes + IDs stables)
        ↓
@theatre/studio  (édition, en développement uniquement)
        ↓
état d'animation JSON versionné dans le dépôt
        ↓
@theatre/core    (runtime de production)
        ↓
Stimulus + Mercure
```

Rive n'est **pas** retenu pour la première version.

## Justification

### Ouverture et absence de dépendance propriétaire (§5.1)

Rive offre une excellente expérience de création, mais son export de production
dépend d'une offre commerciale. Nous préférons une chaîne plus ouverte et plus
proche des technologies web natives : aucun fichier graphique propriétaire
obligatoire, et un état d'animation lisible et versionnable comme du code.

### Le SVG comme scène, pas comme image (§15.2)

Le SVG est vectoriel, natif du navigateur, responsive, accessible, manipulable par
JavaScript et CSS, structurable en groupes/identifiants et versionnable sous forme
de texte. Il se prête naturellement aux **accessoires interchangeables** manipulés
comme des éléments du DOM, et reste léger pour un personnage unique.

### Theatre.js : timeline pilotable et runtime séparé (§15.3)

Theatre.js apporte une timeline visuelle et un éditeur de motion design en
développement, tout en fournissant un **runtime de production (`@theatre/core`)
distinct de l'éditeur (`@theatre/studio`)**. N'importe quelle valeur JavaScript
peut être pilotée, ce qui permet d'animer le SVG à partir de l'état serveur.

### Valeur pédagogique et séparation des responsabilités (§4.3, §15.5)

La chaîne exige davantage de code, mais c'est précisément l'intérêt : elle force
un apprentissage direct de l'animation web et une frontière nette. Theatre.js **ne
décide jamais** du vendredi courant, de la valeur d'énergie, du quota de cafés, du
gagnant du vote ni du conseil actif : il **reçoit un état déjà validé et
l'anime**. Le backend demeure l'unique source de vérité.

### Réversibilité (§34)

Le personnage étant un SVG natif piloté via une couche d'adaptation isolée,
Theatre.js peut être remplacé sans redessiner le canard — ce qui couvre le risque
« dépendance Theatre.js abandonnée ».

## Conséquences

### Positives

- Aucun format propriétaire obligatoire ; SVG et état d'animation lisibles et
  versionnés.
- Contrôle fin de l'intégration ; accessoires manipulables dans le DOM.
- Frontière claire entre logique métier (serveur) et représentation (animation).
- Matière d'apprentissage réelle et réversibilité du moteur d'animation.

### Négatives / coûts

- Plus de code à écrire qu'avec une solution clé en main.
- Discipline requise sur la **structure du SVG** (IDs stables, pivots) — atténuée
  par une convention documentée et un prototype avant le dessin final (§16, §34).
- Nécessité d'une **politique de priorité d'animation** centralisée pour éviter
  les conflits entre séquences (§17.6).
- Vigilance CI : `@theatre/studio` ne doit **jamais** être livré dans le bundle
  public de production (§15.4, §31.2).

## Alternatives écartées

- **Rive** — écarté en raison de la dépendance de son export de production à une
  offre commerciale, et d'une moindre valeur d'apprentissage des technologies web
  natives (§5.1).
- **Moteurs de jeu / PixiJS / 3D** — hors périmètre du MVP (§35) :
  disproportionnés pour un personnage unique en 2D.
