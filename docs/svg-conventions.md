# Conventions SVG

> Stub — à compléter en Phase 0. Référence faisant foi :
> [cahier des charges](cdc_friday_duck.md) (§16).

## SVG inline

Le canard est injecté en SVG inline dans le DOM (pas `<img>`) afin de cibler ses
groupes, changer les accessoires, animer les transformations et conserver une
alternative accessible (§16.1).

## Structure et IDs

Identifiants stables et explicites, groupés par articulation (`duck-root`,
`duck-head`, `duck-left-wing`, slots d'accessoires…). Voir l'arborescence de
référence en §16.2.

## Pivots (§16.4)

Chaque articulation a un pivot documenté (tête : base du cou ; aile : attache au
corps ; etc.) cohérent avec `transform-origin`.

## Accessoires (§16.5)

Chaque accessoire dispose d'un id, d'une catégorie d'attache (slot), d'une
position/échelle par défaut, d'une animation d'apparition et d'une description
textuelle.

## Optimisation (§16.6)

Une étape de build produit un SVG optimisé **sans** supprimer les IDs utilisés par
JS, les groupes nécessaires, les attributs d'accessibilité ni le `viewBox`. La
config de l'optimiseur est versionnée. Source éditable conservée séparément
(`design/`).

## À compléter

- [ ] Liste exhaustive des IDs de groupes et des slots
- [ ] Table des pivots et `transform-origin`
- [ ] Procédure d'export source → SVG optimisé
