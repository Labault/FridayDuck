# Couche `Application`

Orchestration des cas d'usage. Fait le lien entre la `Presentation` et le
`Domain`, sans contenir de règle métier officielle.

## Frontières

- ✅ Commandes / Requêtes (CQRS léger), Handlers, et modèles de lecture (`View`)
  destinés à Twig / aux endpoints.
- ✅ Peut dépendre du `Domain` et des **interfaces** (ports) qu'il expose.
- ⚠️ Peut s'appuyer sur des contrats Symfony (Messenger, bus) mais ne doit pas
  porter la logique de calcul métier : elle reste dans le `Domain`.
- ❌ Pas d'accès direct à Doctrine, Mercure ou HTTP : ces détails passent par
  l'`Infrastructure` via injection d'interfaces.

## Sous-dossiers (§30)

`Command`, `Query`, `Handler`, `View`.

> Rien n'est implémenté à ce stade.
