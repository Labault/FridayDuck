# Tests

Suites alignées sur la stratégie de tests du cahier des charges (§29) et
l'organisation §30.

| Dossier      | Portée (§29)                                                                 |
| ------------ | ---------------------------------------------------------------------------- |
| `Unit/`      | Logique métier pure : dates/fuseau, quota café, énergie, clôture vote, etc.  |
| `Integration/` | Transactions PostgreSQL, contraintes d'unicité, idempotence, Messenger, Mercure. |
| `Functional/`  | Parcours HTTP de bout en bout côté serveur (endpoints, rendu).            |
| `JavaScript/`  | Mapping énergie → propriétés visuelles, priorité d'animation, Stimulus.   |
| `EndToEnd/`    | Scénarios Playwright (hors vendredi, vendredi, temps réel, vote, a11y).   |

> Aucun test n'est encore écrit : la structure est posée pour les phases
> ultérieures (§32).
