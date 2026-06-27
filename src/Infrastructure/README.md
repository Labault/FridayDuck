# Couche `Infrastructure`

Implémentations techniques des ports définis par le `Domain` / l'`Application`.
C'est **ici, et uniquement ici**, que vivent Doctrine, Mercure, Messenger et les
détails d'observabilité.

## Frontières

- ✅ Implémente les interfaces du domaine (repositories Doctrine, `SystemClock`,
  publisher Mercure, transports Messenger, instrumentation OpenTelemetry).
- ✅ Mapping ORM, migrations, adaptateurs externes.
- ❌ Ne contient aucune règle métier : elle adapte, elle ne décide pas.

## Sous-dossiers (§30)

`Persistence` (Doctrine — entités et mappings ORM), `Messaging` (Messenger),
`Mercure`, `Clock` (`SystemClock`, `ConfigurableClock`), `Observability`.

> Le mapping Doctrine (`config/packages/doctrine.yaml`) pointe sur
> `Persistence/` : les entités y seront ajoutées en Phase 2 (§32). Rien n'est
> implémenté à ce stade.
