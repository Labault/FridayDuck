# Système d'animation

> Stub — à compléter en Phase 0/2. Référence faisant foi :
> [cahier des charges](cdc_friday_duck.md) (§15–§19) et
> [ADR 0001](adr/0001-svg-theatrejs-vs-rive.md).

## Chaîne retenue

`Inkscape/Figma` → `SVG inline` → `@theatre/studio` (conception) → état JSON
versionné → `@theatre/core` (production) → `Stimulus` + `Mercure`.

## Séparation développement / production

- **Développement :** `@theatre/core` + `@theatre/studio` (éditeur visible).
- **Production :** `@theatre/core` uniquement. Le Studio **ne doit jamais** être
  livré dans le bundle public (contrôle automatique en CI, §31.2).

## État versionné

`assets/animation/theatre/duck-friday.state.json` est relu comme du code et chargé
par le runtime (§17.3).

## Principe fondamental

Theatre.js **ne décide de rien** (vendredi courant, énergie, gagnant…). Il reçoit
un état déjà validé par le serveur et l'anime (§15.5).

## Priorité des animations (§17.6)

1. Réduction de mouvement / état statique
2. Animation d'incident critique
3. Animation locale de café
4. Révélation d'accessoire
5. Transition d'énergie
6. Idle

## À documenter (§37.3)

- [ ] Ouvrir le Studio et modifier une séquence
- [ ] Exporter l'état et vérifier le bundle
- [ ] Ajouter un groupe SVG / créer un accessoire
- [ ] Tester `prefers-reduced-motion`
- [ ] Résoudre un conflit de priorité
