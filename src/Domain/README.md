# Couche `Domain`

Cœur métier du Canard du Vendredi. **Code PHP pur, sans dépendance technique.**

## Frontières (non négociables)

- ❌ Aucun `use Symfony\...`, aucun `use Doctrine\...`, aucune annotation/attribut
  d'ORM, aucun appel HTTP, aucun accès base de données.
- ❌ Aucun appel direct à `new \DateTimeImmutable()` / `new \DateTime()` dans un
  service de domaine : le temps passe **exclusivement** par
  `App\Domain\Shared\Clock\ClockInterface` (voir §7.3 du cahier des charges).
- ✅ Entités métier, objets-valeur, événements de domaine, interfaces de ports
  (repositories, horloge) et règles de calcul (énergie, quota, clôture du vote).
- ✅ Le fuseau métier de référence est `Europe/Paris` (§7.2).

Le domaine définit les **interfaces** ; l'`Infrastructure` fournit les
implémentations (Doctrine, horloge système, Mercure…).

## Modules (§30)

`Friday`, `Duck`, `Coffee`, `Accessory`, `Advice`, `Visitor`, `Shared/Clock`.

> Rien n'est implémenté à ce stade — la structure est posée pour la **Phase 1**
> (domaine temporel) de la feuille de route (§32).
