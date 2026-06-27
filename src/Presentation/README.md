# Couche `Presentation`

Points d'entrée de l'application : HTTP (contrôleurs, endpoints, rendu Twig) et
console (commandes Symfony).

## Frontières

- ✅ Contrôleurs HTTP fins : ils valident l'entrée, délèguent à l'`Application`
  et renvoient une réponse (HTML/JSON). Voir les contrats d'API en §24.
- ✅ Commandes console (`Presentation/Console`) déclenchées par le Scheduler (§25).
- ❌ Aucune règle métier, aucun accès Doctrine direct : tout passe par
  l'`Application` et le `Domain`.
- 🔒 Le serveur reste l'unique source de vérité : le navigateur (Stimulus,
  Theatre.js) ne décide de rien (§7.1, §15.5).

## Sous-dossiers (§30)

`Http`, `Console`.

> Rien n'est implémenté à ce stade.
