# Cahier des charges fonctionnel et technique  
## Le Canard du Vendredi

> **Version :** 2.0 — Concept fonctionnel et système d’animation arrêtés  
> **Date de référence :** 27 juin 2026  
> **Nature :** application web expérimentale, humoristique et open source  
> **Public visé :** développeur backend ou full-stack, intégrateur, motion designer, contributeur, CTO ou recruteur technique  
> **Statut :** document de cadrage prêt à être transformé en backlog

---

## Sommaire

1. [Résumé exécutif](#1-résumé-exécutif)
2. [Vision et positionnement](#2-vision-et-positionnement)
3. [Concept fonctionnel retenu](#3-concept-fonctionnel-retenu)
4. [Objectifs du projet](#4-objectifs-du-projet)
5. [Décisions désormais arrêtées](#5-décisions-désormais-arrêtées)
6. [Périmètre du MVP](#6-périmètre-du-mvp)
7. [Règles temporelles](#7-règles-temporelles)
8. [Mécanique collective du café](#8-mécanique-collective-du-café)
9. [Niveaux d’énergie du canard](#9-niveaux-dénergie-du-canard)
10. [Vote hebdomadaire de l’accessoire](#10-vote-hebdomadaire-de-laccessoire)
11. [Conseil catastrophique du vendredi](#11-conseil-catastrophique-du-vendredi)
12. [Déroulement complet d’un vendredi](#12-déroulement-complet-dun-vendredi)
13. [Parcours utilisateurs](#13-parcours-utilisateurs)
14. [Expérience visuelle et éditoriale](#14-expérience-visuelle-et-éditoriale)
15. [Choix du système visuel](#15-choix-du-système-visuel)
16. [Construction du SVG du canard](#16-construction-du-svg-du-canard)
17. [Architecture d’animation avec Theatrejs](#17-architecture-danimation-avec-theatrejs)
18. [Catalogue des animations](#18-catalogue-des-animations)
19. [Pilotage par Stimulus](#19-pilotage-par-stimulus)
20. [Synchronisation temps réel avec Mercure](#20-synchronisation-temps-réel-avec-mercure)
21. [Architecture technique globale](#21-architecture-technique-globale)
22. [Choix technologiques et justifications](#22-choix-technologiques-et-justifications)
23. [Modèle de données](#23-modèle-de-données)
24. [API et contrats d’événements](#24-api-et-contrats-dévénements)
25. [Messenger et Scheduler](#25-messenger-et-scheduler)
26. [Observabilité](#26-observabilité)
27. [Sécurité et respect de la vie privée](#27-sécurité-et-respect-de-la-vie-privée)
28. [Accessibilité et performances visuelles](#28-accessibilité-et-performances-visuelles)
29. [Stratégie de tests](#29-stratégie-de-tests)
30. [Organisation du code et des assets](#30-organisation-du-code-et-des-assets)
31. [Intégration et déploiement continus](#31-intégration-et-déploiement-continus)
32. [Feuille de route](#32-feuille-de-route)
33. [Critères de recette](#33-critères-de-recette)
34. [Risques et mesures de maîtrise](#34-risques-et-mesures-de-maîtrise)
35. [Fonctionnalités hors périmètre](#35-fonctionnalités-hors-périmètre)
36. [Évolutions possibles](#36-évolutions-possibles)
37. [Livrables attendus](#37-livrables-attendus)
38. [Références officielles](#38-références-officielles)
39. [Conclusion](#39-conclusion)

---

# 1. Résumé exécutif

**Le Canard du Vendredi** est une application web qui n’est réellement active que le vendredi.

Du samedi au jeudi, le canard dort. Le vendredi, il se réveille dans un état de fatigue avancé et les visiteurs doivent collectivement lui servir du café afin de faire progresser sa jauge d’énergie.

En parallèle, les visiteurs peuvent :

- voter pour l’accessoire qu’il portera pendant la seconde partie de la journée ;
- découvrir un conseil professionnel catastrophique, renouvelé chaque vendredi ;
- réagir à ce conseil ;
- observer en temps réel l’évolution de l’énergie, des votes et des statistiques collectives.

Le concept est volontairement inutile. Son exécution doit être sérieuse.

Le projet constitue un laboratoire technique permettant de pratiquer :

- Symfony et une architecture métier propre ;
- FrankenPHP en mode worker ;
- PostgreSQL ;
- Symfony Messenger et Scheduler ;
- Mercure pour le temps réel ;
- OpenTelemetry et Grafana pour l’observabilité ;
- SVG comme format graphique natif du web ;
- Theatre.js comme moteur et éditeur d’animation ;
- Stimulus comme couche de connexion légère entre le backend et le visuel ;
- Playwright pour les tests navigateur ;
- Docker et GitHub Actions pour l’industrialisation.

La promesse peut se résumer ainsi :

> **Chaque vendredi, Internet tente de rendre un canard suffisamment caféiné pour travailler, choisit comment l’habiller et reçoit en échange un conseil qu’il ne faut surtout pas suivre.**

---

# 2. Vision et positionnement

## 2.1 Une idée volontairement absurde

L’application ne cherche pas à résoudre un problème important.

Elle ne vise pas à :

- améliorer la productivité ;
- devenir une plateforme incontournable ;
- remplacer un outil métier ;
- créer un abonnement ;
- lever des fonds ;
- exploiter des données personnelles.

Sa valeur vient de trois éléments :

1. un concept compris immédiatement ;
2. une expérience drôle et collective ;
3. une réalisation technique disproportionnellement sérieuse.

## 2.2 Un projet de démonstration

Le projet doit pouvoir être présenté à un autre développeur comme un cas concret de :

- logique métier dépendante du temps ;
- architecture testable ;
- idempotence ;
- temps réel ;
- processus PHP persistant ;
- animation pilotée par les données ;
- observabilité technique et métier ;
- accessibilité d’une interface animée ;
- déploiement reproductible.

## 2.3 Principe directeur

> **L’humour est dans le produit. La rigueur est dans le code.**

Le projet peut plaisanter sur les déploiements du vendredi. Il ne doit pas être réellement déployé sans tests, sans rollback ni supervision.

---

# 3. Concept fonctionnel retenu

Le produit repose sur trois mécaniques complémentaires.

## 3.1 Le café

Chaque vendredi, le canard commence la journée à faible énergie.

Les visiteurs lui servent collectivement du café. Chaque contribution validée augmente une jauge mondiale de `0` à `100`.

L’apparence, la posture, la vitesse, les expressions et les animations du canard évoluent avec cette énergie.

## 3.2 L’accessoire

Trois accessoires sont proposés chaque vendredi.

Chaque visiteur peut voter une fois. Le vote est clôturé à une heure définie. L’accessoire gagnant est ensuite porté par le canard jusqu’à son endormissement.

## 3.3 Le conseil catastrophique

Un conseil unique est publié chaque vendredi.

Exemple :

> « Déploie à 16 h 58. Ça crée des souvenirs avec l’équipe. »

Le conseil est court, partageable, volontairement mauvais et accompagné de réactions collectives.

## 3.4 Complémentarité

Les trois mécaniques ne poursuivent pas le même objectif :

- le café produit une progression collective ;
- l’accessoire crée une décision communautaire ;
- le conseil renouvelle le contenu chaque semaine.

Elles doivent rester visibles sur une seule expérience cohérente, sans transformer l’application en tableau de bord surchargé.

---

# 4. Objectifs du projet

## 4.1 Objectifs produit

- Faire comprendre le concept en moins de dix secondes.
- Donner une raison de revenir chaque vendredi.
- Montrer une évolution visible pendant la journée.
- Créer un personnage attachant sans narration complexe.
- Permettre une participation sans compte.
- Générer des résultats hebdomadaires partageables.
- Rester amusant même avec peu de visiteurs.

## 4.2 Objectifs techniques

- Isoler la logique temporelle du framework.
- Utiliser une horloge injectable.
- Garantir l’idempotence des interactions.
- Diffuser les changements collectifs en temps réel.
- Animer un SVG à partir de données serveur.
- Construire un système visuel gratuit et majoritairement open source.
- Apprendre Theatre.js dans un cas réel.
- Observer les workers FrankenPHP et Messenger.
- Corréler logs, métriques et traces.
- Automatiser les tests et le déploiement.

## 4.3 Objectifs professionnels

Le projet doit permettre d’expliquer clairement :

- pourquoi SVG + Theatre.js a été préféré à Rive ;
- comment le backend reste la source de vérité ;
- comment une animation est séparée de la logique métier ;
- comment une mise à jour temps réel est propagée ;
- comment un vote est clôturé de manière fiable ;
- comment éviter le double comptage ;
- comment tester une application dépendante du vendredi ;
- comment préserver l’accessibilité malgré les animations.

---

# 5. Décisions désormais arrêtées

Les décisions suivantes sont considérées comme acquises pour la première version.

| Sujet | Décision |
|---|---|
| Framework backend | Symfony |
| Serveur applicatif | FrankenPHP |
| Architecture | Monolithe modulaire |
| Rendu initial | Twig, rendu côté serveur |
| Base de données | PostgreSQL |
| Identité utilisateur | Cookie anonyme, sans compte |
| Temps réel | Mercure |
| Animation principale | SVG inline + Theatre.js |
| Création du dessin | Inkscape recommandé, Figma accepté |
| Intégration JavaScript | Stimulus |
| Interface périphérique | HTML, CSS et animations CSS limitées |
| État métier | Calculé et validé côté serveur |
| Fuseau de référence | Europe/Paris |
| Interaction principale | Café collectif |
| Interaction secondaire | Vote de trois accessoires |
| Contenu hebdomadaire | Un conseil catastrophique |
| Observabilité | OpenTelemetry + pile Grafana |
| Tâches asynchrones | Messenger |
| Planification | Symfony Scheduler |
| Tests navigateur | Playwright |
| Analyse statique | PHPStan |
| Conteneurisation | Docker Compose |
| CI | GitHub Actions |

## 5.1 Pourquoi Rive n’est pas retenu

Rive offre une excellente expérience de création, mais son export de production dépend d’une offre commerciale.

Le projet choisit une chaîne plus ouverte et plus proche des technologies web natives :

```text
Inkscape ou Figma
        ↓
SVG structuré
        ↓
Theatre.js Studio en développement
        ↓
État d’animation JSON versionné
        ↓
@theatre/core en production
        ↓
Stimulus + Mercure
```

Cette solution exige davantage de code, mais elle présente plusieurs avantages :

- aucun fichier graphique propriétaire obligatoire ;
- SVG lisible et versionnable ;
- contrôle précis de l’intégration ;
- apprentissage direct de l’animation web ;
- accessoires manipulables comme éléments du DOM ;
- possibilité de remplacer Theatre.js sans redessiner le personnage.

---

# 6. Périmètre du MVP

## 6.1 Fonctionnalités obligatoires

Le MVP doit inclure :

1. une page principale publique ;
2. un état dormant hors vendredi ;
3. un état réveillé le vendredi ;
4. une jauge collective de `0` à `100` ;
5. un bouton permettant de servir un café ;
6. une limite de cafés par visiteur ;
7. cinq états visuels d’énergie au minimum ;
8. trois accessoires soumis au vote ;
9. un vote maximum par visiteur et par vendredi ;
10. une clôture automatique du vote ;
11. l’application visuelle de l’accessoire gagnant ;
12. un conseil catastrophique unique ;
13. trois réactions possibles au conseil ;
14. une réaction maximum par visiteur ;
15. une identité anonyme persistante ;
16. une diffusion temps réel de la jauge et du vote ;
17. un historique minimal des vendredis ;
18. une observabilité minimale ;
19. des tests de la logique métier et des parcours principaux.

## 6.2 Paramètres initiaux

Les valeurs suivantes constituent les paramètres par défaut du MVP. Elles doivent rester configurables.

```text
Cafés maximum par visiteur et par vendredi : 3
Gain métier d’un café : calculé par le serveur
Nombre d’accessoires proposés : 3
Votes maximum : 1 par visiteur
Clôture du vote : vendredi à 14 h 00
Application du gagnant : immédiatement après clôture
Conseils publiés : 1 par vendredi
Réactions maximum : 1 par visiteur
Fuseau horaire : Europe/Paris
```

## 6.3 Ce que le MVP ne contient pas

- plusieurs types de café avec des puissances différentes ;
- compte utilisateur ;
- classement public ;
- achat d’accessoires ;
- IA générative ;
- scène complète de jeu ;
- déplacements libres du canard ;
- application mobile native ;
- création d’accessoires par les utilisateurs.

---

# 7. Règles temporelles

## 7.1 Source de vérité

La date et l’heure serveur sont la seule source de vérité.

Le navigateur ne décide pas :

- si nous sommes vendredi ;
- si le vote est ouvert ;
- si le conseil est actif ;
- si un café peut être servi ;
- quel accessoire a gagné.

## 7.2 Fuseau horaire

Le fuseau métier initial est :

```text
Europe/Paris
```

L’état bascule :

- en vendredi actif à `00:00:00` ;
- en état dormant le samedi à `00:00:00`.

## 7.3 Horloge injectable

La logique métier utilise une abstraction.

```php
interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
```

Implémentations :

- `SystemClock` en production ;
- `FrozenClock` dans les tests ;
- `ConfigurableClock` en environnement local.

Aucun service métier ne doit disperser des appels directs à `new DateTimeImmutable()`.

## 7.4 Mode de simulation

En développement et en préproduction, il doit être possible de simuler un vendredi.

Exemple :

```text
APP_FAKE_NOW=2026-07-03T10:30:00+02:00
```

Cette possibilité doit être :

- interdite ou explicitement neutralisée en production ;
- visible dans la barre de debug ;
- journalisée ;
- testée.

---

# 8. Mécanique collective du café

## 8.1 Principe

Le café est la mécanique centrale.

Un visiteur clique sur le bouton **« Offrir un café »**. Le serveur valide l’action, l’enregistre et recalcule l’énergie collective.

La valeur officielle de l’énergie est toujours retournée par le backend.

## 8.2 Limite initiale

Chaque identité anonyme peut servir au maximum trois cafés par vendredi.

Cette limite :

- évite le spam ;
- donne une petite valeur à chaque action ;
- permet plusieurs visites dans la journée ;
- reste simple à expliquer.

## 8.3 Progression

L’énergie est exposée sous forme normalisée :

```text
0 ≤ energy ≤ 100
```

Le nombre brut de cafés peut dépasser le nombre nécessaire pour atteindre `100`.

Une fois la jauge pleine :

- l’énergie reste à `100` ;
- les cafés supplémentaires alimentent un compteur de surcaféination ;
- le canard peut produire des réactions spéciales ;
- les contributions continuent à être visibles dans les statistiques.

## 8.4 Calcul

Le calcul doit être contrôlé côté serveur.

Approche initiale possible :

```text
energy = min(100, floor(validatedCoffeeCount / targetCoffeeCount * 100))
```

Le `targetCoffeeCount` peut être :

- fixe au démarrage ;
- configuré par environnement ;
- ajusté plus tard selon la fréquentation historique.

Le calcul ne doit jamais dépendre d’une valeur uniquement présente dans le navigateur.

## 8.5 Réponse à une contribution

Une contribution acceptée retourne au minimum :

```json
{
  "accepted": true,
  "coffeeContributionId": "uuid",
  "previousEnergy": 41,
  "currentEnergy": 42,
  "remainingCoffeesForVisitor": 1,
  "reachedThreshold": false,
  "serverTime": "2026-07-03T11:18:00+02:00"
}
```

## 8.6 Idempotence

Chaque contribution utilise une clé d’idempotence générée côté client et validée côté serveur.

```text
coffee:{visitor-id}:{friday}:{client-action-id}
```

Un double clic, une reconnexion ou un rejeu réseau ne doit pas compter deux cafés.

## 8.7 Comportements humoristiques

Selon le contexte, le canard peut réagir :

- premier café : « Enfin une décision raisonnable. »
- troisième café du visiteur : « Votre quota de responsabilité est épuisé. »
- jauge pleine : « Il vient de proposer douze microservices. »
- café après 18 h : « Il ne dormira plus avant mardi. »

Les textes sont décoratifs. Ils ne modifient pas les règles métier.

---

# 9. Niveaux d’énergie du canard

Le visuel doit associer une progression continue à des états lisibles.

## 9.1 États du MVP

| Énergie | Code | État | Comportement principal |
|---:|---|---|---|
| 0–10 | `COMATOSE` | Comateux | Dort presque debout |
| 11–30 | `PHYSICALLY_PRESENT` | Présent physiquement | Un œil ouvert |
| 31–50 | `BARELY_FUNCTIONAL` | Presque fonctionnel | Commence à comprendre |
| 51–70 | `PERFORMING_PRODUCTIVITY` | Productif en apparence | Tape sur un clavier |
| 71–90 | `OVERMOTIVATED` | Beaucoup trop motivé | Veut tout refactorer |
| 91–100 | `CAFFEINE_INCIDENT` | Incident caféiné | Prépare un déploiement à 16 h 58 |

## 9.2 Paramètres visuels continus

La valeur d’énergie doit piloter progressivement :

- ouverture des paupières ;
- inclinaison de la tête ;
- verticalité du corps ;
- fréquence des clignements ;
- vitesse de respiration ;
- vitesse des mouvements ;
- quantité de vapeur ;
- intensité des tremblements ;
- agitation des ailes ;
- fréquence des animations secondaires.

## 9.3 Paramètres discrets

Certains changements se déclenchent à un seuil :

- apparition du clavier ;
- allumage du terminal ;
- plumes dressées ;
- particules de café ;
- ouverture d’un second œil ;
- alerte visuelle de surcaféination.

## 9.4 Transitions

Le passage entre deux états ne doit pas être brutal.

Theatre.js doit animer :

- la posture ;
- le regard ;
- la vitesse ;
- la vapeur ;
- les objets secondaires.

Durée indicative :

```text
Petite mise à jour d’énergie : 300 à 600 ms
Passage de seuil : 800 à 1 400 ms
Animation spéciale : 1 500 à 3 000 ms
```

---

# 10. Vote hebdomadaire de l’accessoire

## 10.1 Règles

Chaque vendredi :

- trois accessoires sont proposés ;
- un visiteur peut voter une fois ;
- le vote est validé côté serveur ;
- le vote ferme à `14:00` heure de Paris ;
- l’accessoire gagnant est appliqué après clôture ;
- le résultat ne peut plus être modifié après validation.

## 10.2 Exemples d’accessoires

- lunettes de soleil de CTO ;
- cravate beaucoup trop sérieuse ;
- cape de production ;
- casque « Ça marche chez moi » ;
- badge « Senior depuis mardi » ;
- bonnet Kubernetes ;
- gilet d’astreinte ;
- petit extincteur ;
- casque antibruit de réunion ;
- écharpe « Vendredi en prod ».

## 10.3 Affichage avant clôture

Le MVP peut afficher :

- le nombre de votes ;
- le pourcentage par option ;
- l’option en tête ;
- le temps restant.

Pour préserver le suspense, les pourcentages peuvent être masqués lors d’une évolution future.

## 10.4 Égalité

En cas d’égalité, le choix doit être déterministe.

Stratégie initiale :

1. nombre de votes ;
2. ordre pseudo-aléatoire dérivé de l’identifiant du vendredi ;
3. résultat persisté définitivement.

Le tirage ne doit pas changer à chaque rechargement.

## 10.5 Application visuelle

L’accessoire gagnant doit :

- apparaître avec une animation courte ;
- être attaché au bon groupe SVG ;
- suivre les transformations du corps ou de la tête ;
- rester visible jusqu’à minuit ;
- disposer d’une alternative textuelle.

## 10.6 Vote fermé

Après `14:00`, toute tentative doit retourner un refus métier clair :

```json
{
  "accepted": false,
  "reason": "VOTE_CLOSED",
  "winner": "CTO_GLASSES"
}
```

---

# 11. Conseil catastrophique du vendredi

## 11.1 Principe

Un seul conseil est actif par vendredi.

Il est préparé à l’avance et associé à la date métier.

Exemple de référence :

> **« Déploie à 16 h 58. Ça crée des souvenirs avec l’équipe. »**

## 11.2 Contraintes éditoriales

Le conseil doit être :

- court ;
- compréhensible ;
- lié au développement, au travail ou à la vie de bureau ;
- manifestement second degré ;
- partageable ;
- non discriminatoire ;
- non dangereux dans le monde réel ;
- sans incitation à une action illégale ou dommageable.

## 11.3 Réactions du MVP

Trois réactions sont retenues :

```text
CONCERNING       → « Conseil inquiétant »
ALREADY_DONE     → « Déjà fait »
TAKING_NOTES     → « Je prends note »
```

Un visiteur peut sélectionner une seule réaction par vendredi.

Une évolution pourra autoriser le changement de réaction, avec mise à jour atomique.

## 11.4 Exemples

> « Si les tests échouent uniquement en CI, c’est probablement la CI qui a tort. »

> « Un bug qui ne se reproduit plus depuis dix minutes est techniquement corrigé. »

> « Quand personne ne comprend le problème, accuse le cache. »

> « Ne corrige jamais un vendredi ce que tu peux surveiller tout le week-end. »

> « Le meilleur moment pour mettre à jour toutes les dépendances, c’est juste avant la démonstration. »

> « Tant que personne n’ouvre la console, il n’y a aucune erreur JavaScript. »

> « Mets “temporaire” dans le nom du fichier. Personne n’osera le supprimer. »

## 11.5 Pas d’IA dans le MVP

Les conseils sont écrits et validés manuellement.

Raisons :

- contrôle de la qualité ;
- cohérence éditoriale ;
- absence de coût externe ;
- absence de risque de contenu imprévisible ;
- simplicité.

Une IA pourra plus tard proposer des brouillons, sans publication automatique.

---

# 12. Déroulement complet d’un vendredi

## 12.1 À minuit

- le vendredi devient actif ;
- l’énergie commence à `0` ;
- le conseil hebdomadaire est publié ;
- les trois accessoires sont révélés ;
- le vote s’ouvre ;
- les compteurs hebdomadaires sont initialisés ;
- un événement `FridayOpened` est émis.

## 12.2 Pendant la matinée

Les visiteurs :

- servent du café ;
- font monter l’énergie ;
- voient le canard changer de comportement ;
- votent ;
- réagissent au conseil ;
- observent les données évoluer en direct.

## 12.3 À 14 h 00

- le vote est fermé ;
- le gagnant est calculé ;
- le résultat est persisté ;
- l’accessoire est appliqué ;
- un événement temps réel est diffusé ;
- une animation de proclamation se joue.

## 12.4 À 100 % d’énergie

- l’état devient `CAFFEINE_INCIDENT` ;
- une animation spéciale se déclenche ;
- la jauge reste pleine ;
- les cafés supplémentaires sont comptés comme surcaféination ;
- une métrique métier et un événement sont produits.

Message possible :

> **ALERTE — SURCAFÉINATION DÉTECTÉE**  
> Le canard vient de demander une réunion pour discuter d’une migration vers douze microservices.

## 12.5 À minuit le samedi

- les interactions ferment ;
- le canard retourne à l’état dormant ;
- le résultat final est figé ;
- un bilan hebdomadaire est généré ;
- un événement `FridayClosed` est émis.

---

# 13. Parcours utilisateurs

## 13.1 Première visite hors vendredi

1. Le serveur crée une identité anonyme.
2. L’état dormant est calculé.
3. Le canard endormi est rendu sous forme de SVG.
4. Le prochain vendredi est affiché.
5. Les interactions sont désactivées.
6. Le conseil précédent peut être consulté uniquement si cette décision est ajoutée plus tard.

## 13.2 Première visite un vendredi

1. Le serveur résout ou crée le visiteur.
2. Le vendredi courant est chargé.
3. L’énergie, les accessoires et le conseil sont rendus.
4. Stimulus initialise le système d’animation.
5. Mercure est connecté.
6. Le visiteur peut servir un café, voter et réagir.

## 13.3 Service d’un café

1. Le visiteur clique.
2. L’interface joue une anticipation courte, sans modifier la valeur officielle.
3. La requête est envoyée avec une clé d’idempotence.
4. Symfony valide le vendredi, le quota et l’identité.
5. PostgreSQL enregistre la contribution.
6. L’énergie est recalculée.
7. La réponse confirme la valeur.
8. Une mise à jour Mercure est publiée.
9. L’auteur voit l’animation complète du café.
10. Les autres visiteurs voient une réaction agrégée et la progression.

## 13.4 Vote

1. Le visiteur choisit un accessoire.
2. Symfony vérifie que le vote est ouvert.
3. Le vote est enregistré.
4. Les totaux sont recalculés.
5. Mercure diffuse les résultats.
6. L’interface confirme le choix.

## 13.5 Réaction au conseil

1. Le visiteur choisit une réaction.
2. Le serveur valide l’unicité.
3. Les compteurs sont mis à jour.
4. L’affichage évolue sans rechargement.

## 13.6 Mode dégradé

Si PostgreSQL est indisponible :

- l’état temporel reste affiché ;
- le canard reste animé à partir d’un état de secours ;
- les interactions sont temporairement désactivées ;
- un message non technique est affiché ;
- les erreurs sont journalisées ;
- une alerte est déclenchée.

---

# 14. Expérience visuelle et éditoriale

## 14.1 Direction générale

Le visuel doit être :

- cartoon ;
- simple ;
- chaleureux ;
- lisible ;
- expressif ;
- peu chargé ;
- cohérent avec une mascotte web ;
- suffisamment modulaire pour accepter des accessoires.

## 14.2 Hiérarchie de la page

Ordre recommandé :

1. conseil catastrophique ;
2. canard animé ;
3. jauge d’énergie ;
4. bouton café ;
5. vote de l’accessoire ;
6. statistiques et réactions.

Sur mobile, le canard et l’action café restent prioritaires.

## 14.3 Ton

Le texte utilise un sérieux administratif disproportionné.

Exemples :

- « Niveau de caféine conforme aux attentes minimales. »
- « Le service de motivation a cessé de répondre. »
- « Une refonte complète a été proposée sans ticket. »
- « Le vote est désormais clos. La démocratie a fait ce qu’elle pouvait. »

## 14.4 États hors vendredi

L’état dormant doit rester vivant :

- respiration lente ;
- petit mouvement de plume ;
- bulle de sommeil ;
- tasse vide ;
- message lié au jour ;
- compte à rebours.

Il ne doit pas ressembler à une page d’erreur.

---

# 15. Choix du système visuel

## 15.1 Chaîne retenue

```text
Création vectorielle     Inkscape
Alternative              Figma
Format maître            SVG
Animation en conception  @theatre/studio
Animation en production  @theatre/core
Intégration               Stimulus
Données collectives       Mercure
Interface                  Twig + CSS
```

## 15.2 Pourquoi le SVG

Le SVG est retenu car il est :

- vectoriel ;
- natif du navigateur ;
- responsive ;
- accessible ;
- manipulable par JavaScript et CSS ;
- structurable avec des groupes et des identifiants ;
- versionnable sous forme de texte ;
- adapté aux accessoires interchangeables ;
- léger pour un personnage unique.

## 15.3 Pourquoi Theatre.js

Theatre.js apporte :

- une timeline visuelle ;
- un éditeur de motion design en développement ;
- des propriétés pilotables ;
- une séquence exportable ;
- une utilisation avec HTML et SVG ;
- un runtime de production séparé de l’éditeur ;
- la possibilité de piloter n’importe quelle valeur JavaScript.

## 15.4 Licences

La politique de licence de Theatre.js doit être documentée dans le dépôt :

- `@theatre/core` est utilisé dans le bundle de production ;
- `@theatre/studio` est réservé à la conception et au développement ;
- les notices de licence nécessaires sont conservées.

Le Studio ne doit pas être chargé dans le bundle public de production.

## 15.5 Ce que Theatre.js ne décide pas

Theatre.js ne doit jamais décider :

- du vendredi courant ;
- de la valeur officielle de l’énergie ;
- du quota de cafés ;
- du gagnant du vote ;
- du conseil actif ;
- des réactions enregistrées.

Il reçoit un état et l’anime.

---

# 16. Construction du SVG du canard

## 16.1 SVG inline

Le canard doit être injecté directement dans le DOM sous forme de SVG inline.

Cela permet :

- de cibler ses groupes ;
- de changer les accessoires ;
- d’animer les transformations ;
- de modifier des couleurs ;
- de conserver une alternative accessible.

Une balise `<img src="duck.svg">` ne permet pas le même contrôle fin.

## 16.2 Structure recommandée

```xml
<svg
  id="duck-scene"
  viewBox="0 0 800 800"
  role="img"
  aria-labelledby="duck-title duck-description"
>
  <title id="duck-title">Le canard du vendredi</title>
  <desc id="duck-description">
    Un canard fatigué dont l'énergie augmente grâce aux cafés des visiteurs.
  </desc>

  <g id="scene-background"></g>
  <g id="duck-shadow"></g>

  <g id="duck-root">
    <g id="duck-body"></g>
    <g id="duck-left-wing"></g>
    <g id="duck-right-wing"></g>

    <g id="duck-head">
      <g id="duck-left-eye">
        <g id="duck-left-pupil"></g>
        <g id="duck-left-eyelid"></g>
      </g>

      <g id="duck-right-eye">
        <g id="duck-right-pupil"></g>
        <g id="duck-right-eyelid"></g>
      </g>

      <g id="duck-beak"></g>
      <g id="duck-head-accessory-slot"></g>
    </g>

    <g id="duck-body-accessory-slot"></g>
    <g id="duck-hand-accessory-slot"></g>
  </g>

  <g id="coffee-cup"></g>
  <g id="coffee-steam"></g>
  <g id="keyboard"></g>
  <g id="terminal"></g>
  <g id="particles"></g>
</svg>
```

## 16.3 Règles de dessin

- Utiliser des identifiants stables et explicites.
- Grouper les formes selon leur articulation.
- Éviter les masques et filtres excessivement coûteux.
- Définir des pivots cohérents.
- Nettoyer les métadonnées inutiles d’Inkscape.
- Convertir les textes graphiques en chemins si la police n’est pas garantie.
- Conserver une source éditable séparée du SVG optimisé.
- Ne pas aplatir les accessoires dans le corps principal.

## 16.4 Pivots

Chaque articulation doit avoir un pivot documenté :

- tête : base du cou ;
- aile : attache au corps ;
- bec : articulation arrière ;
- tasse : centre de la poignée ou point de préhension ;
- paupière : centre de l’œil ;
- accessoire de tête : repère lié à la tête.

Les transformations SVG doivent tenir compte de `transform-origin`.

## 16.5 Accessoires

Chaque accessoire doit disposer :

- d’un identifiant ;
- d’une catégorie d’attache ;
- d’une position par défaut ;
- d’une échelle ;
- d’une animation d’apparition ;
- d’une description textuelle.

Exemple de manifeste :

```json
{
  "id": "cto_glasses",
  "label": "Lunettes de soleil de CTO",
  "slot": "head",
  "svgGroupId": "accessory-cto-glasses",
  "entranceSequence": "accessory_glasses_enter"
}
```

## 16.6 Optimisation

Une étape de build doit produire une version optimisée du SVG, sans supprimer :

- les identifiants utilisés par JavaScript ;
- les groupes nécessaires ;
- les attributs d’accessibilité ;
- les `viewBox` ;
- les repères attendus.

La configuration de l’optimiseur doit être versionnée.

---

# 17. Architecture d’animation avec Theatre.js

## 17.1 Séparation développement / production

### Développement

```text
@theatre/core
@theatre/studio
SVG inline
Éditeur de séquence visible
État du projet exportable
```

### Production

```text
@theatre/core uniquement
SVG inline
Fichier d’état JSON exporté
Aucune interface Studio
```

## 17.2 Projet Theatre.js

Organisation indicative :

```text
Project: DuckFriday
├── Sheet: DuckIdle
│   ├── Object: Body
│   ├── Object: Head
│   ├── Object: Eyes
│   ├── Object: Wings
│   └── Object: Steam
├── Sheet: CoffeeAction
│   ├── Object: Cup
│   ├── Object: Head
│   ├── Object: Beak
│   └── Object: Reaction
├── Sheet: AccessoryReveal
└── Sheet: CaffeineIncident
```

## 17.3 État versionné

Le fichier exporté par Theatre.js est stocké dans le dépôt.

```text
assets/animation/theatre/duck-friday.state.json
```

Il doit être :

- relu comme du code ;
- associé à une modification visuelle documentée ;
- chargé par le runtime de production ;
- testé au minimum par initialisation.

## 17.4 Propriétés pilotées

Exemple de modèle visuel :

```ts
type DuckVisualProperties = {
  energy: number;
  bodyY: number;
  bodyRotation: number;
  headRotation: number;
  eyeOpen: number;
  blinkSpeed: number;
  wingActivity: number;
  steamOpacity: number;
  steamSpeed: number;
  shakeIntensity: number;
  keyboardVisible: boolean;
  terminalVisible: boolean;
  incidentParticles: number;
};
```

## 17.5 Résolution de l’état visuel

La logique visuelle peut convertir l’énergie métier en cible d’animation.

```ts
function resolveVisualTargets(energy: number): DuckVisualProperties {
  const normalized = Math.max(0, Math.min(100, energy)) / 100;

  return {
    energy,
    bodyY: interpolate(10, 0, normalized),
    bodyRotation: interpolate(8, -1, normalized),
    headRotation: interpolate(14, -2, normalized),
    eyeOpen: easeInOut(normalized),
    blinkSpeed: interpolate(0.4, 2.4, normalized),
    wingActivity: interpolate(0.1, 1.8, normalized),
    steamOpacity: interpolate(0.05, 1, normalized),
    steamSpeed: interpolate(0.2, 2.2, normalized),
    shakeIntensity: energy >= 91 ? (energy - 90) / 10 : 0,
    keyboardVisible: energy >= 51,
    terminalVisible: energy >= 71,
    incidentParticles: energy >= 91 ? Math.round((energy - 90) * 2) : 0,
  };
}
```

Cette fonction ne remplace pas le domaine. Elle convertit une valeur déjà validée en représentation.

## 17.6 Transitions concurrentes

Une seule autorité doit piloter chaque propriété à un instant donné.

Il faut éviter que :

- l’animation idle déplace la tête ;
- l’animation café déplace aussi la tête ;
- la transition d’énergie écrase les deux sans priorité.

Une politique de priorité est requise :

```text
1. Réduction de mouvement / état statique
2. Animation d’incident critique
3. Animation locale de café
4. Animation de révélation d’accessoire
5. Transition d’énergie
6. Idle
```

## 17.7 Interruption

Une animation spéciale doit pouvoir :

- interrompre l’idle ;
- se jouer ;
- revenir vers l’état correspondant à l’énergie courante ;
- ne pas revenir vers une ancienne valeur.

---

# 18. Catalogue des animations

## 18.1 Animations de base

### `idle_sleeping`

- respiration lente ;
- tête penchée ;
- yeux fermés ;
- mouvement minimal.

### `idle_groggy`

- un œil légèrement ouvert ;
- clignement lent ;
- tasse tenue faiblement.

### `idle_awake`

- posture plus droite ;
- regard mobile ;
- petite gorgée occasionnelle.

### `idle_productive`

- frappe légère au clavier ;
- regard vers le terminal ;
- hochement de tête sérieux.

### `idle_overmotivated`

- ailes actives ;
- gestes rapides ;
- alternance entre plusieurs objets.

### `idle_incident`

- tremblement ;
- yeux ouverts ;
- particules ;
- agitation du terminal.

## 18.2 Animation de café

### `coffee_receive`

Déroulement :

1. tasse ou café apparaît ;
2. regard du canard vers la tasse ;
3. aile saisit la tasse ;
4. bec s’ouvre ;
5. le canard boit ;
6. réaction énergétique ;
7. retour à l’état courant.

## 18.3 Animation distante agrégée

### `coffee_global_pulse`

Pour les cafés servis par d’autres visiteurs :

- petit halo ;
- hausse fluide de jauge ;
- courte réaction du canard ;
- aucun défilé de tasses.

Cette distinction évite qu’un pic de contributions déclenche des dizaines d’animations longues simultanément.

## 18.4 Animations d’accessoire

- `accessory_vote_select`
- `accessory_winner_reveal`
- `accessory_tie_break`
- `accessory_remove_at_midnight`

## 18.5 Animations du conseil

Le conseil appartient au DOM, pas au SVG principal.

Animations possibles :

- apparition de la carte ;
- léger tampon « conseil officiel » ;
- réaction du compteur ;
- copie réussie.

Ces animations sont réalisées en CSS ou avec la Web Animations API, pas nécessairement avec Theatre.js.

## 18.6 Animations aléatoires

Exemples :

- regarder l’heure ;
- bâiller ;
- remettre la tasse droite ;
- soupirer ;
- fixer le bouton café ;
- ajuster l’accessoire ;
- taper une commande ;
- observer un log avec inquiétude.

Règles :

- fréquence dépendante de l’énergie ;
- pas de répétition immédiate ;
- pas de déclenchement pendant une animation prioritaire ;
- désactivation ou simplification en mode réduction de mouvement.

---

# 19. Pilotage par Stimulus

## 19.1 Responsabilité

Stimulus fait le pont entre :

- les données rendues par Twig ;
- les événements utilisateur ;
- les réponses HTTP ;
- Mercure ;
- Theatre.js ;
- le SVG.

Il ne contient pas la logique métier officielle.

## 19.2 Contrôleur principal

Nom proposé :

```text
duck_controller.ts
```

Responsabilités :

- récupérer les targets SVG ;
- initialiser Theatre.js ;
- charger l’état JSON ;
- appliquer l’état initial ;
- écouter les événements Mercure ;
- déclencher les animations ;
- respecter `prefers-reduced-motion` ;
- nettoyer les abonnements lors de `disconnect()`.

## 19.3 Exemple Twig

```twig
<section
    {{ stimulus_controller('duck', {
        energy: friday.energy,
        state: friday.duckState,
        accessory: friday.winningAccessory,
        voteOpen: friday.voteOpen,
        mercureUrl: mercure(friday.topic),
        reducedMotion: false,
    }) }}
>
    <div data-duck-target="scene">
        {% include 'duck/_scene.svg.twig' %}
    </div>

    <progress
        data-duck-target="energyBar"
        value="{{ friday.energy }}"
        max="100"
        aria-label="Énergie actuelle du canard"
    >
        {{ friday.energy }} %
    </progress>
</section>
```

## 19.4 Événements internes

Le contrôleur peut normaliser les événements :

```ts
type DuckClientEvent =
  | { type: 'ENERGY_UPDATED'; energy: number; source: 'local' | 'remote' }
  | { type: 'COFFEE_ACCEPTED'; contributionId: string; energy: number }
  | { type: 'ACCESSORY_RESULTS_UPDATED'; results: VoteResult[] }
  | { type: 'ACCESSORY_WINNER_SELECTED'; accessory: string }
  | { type: 'FRIDAY_CLOSED' };
```

## 19.5 Connexion et nettoyage

Lors de la connexion :

- initialiser le SVG ;
- charger le projet Theatre.js ;
- appliquer l’état serveur ;
- ouvrir `EventSource`.

Lors de la déconnexion :

- fermer `EventSource` ;
- arrêter les séquences ;
- supprimer les timers ;
- libérer les références DOM ;
- éviter les doubles abonnements avec Turbo.

## 19.6 Turbo

Si Symfony UX Turbo est utilisé, le contrôleur doit être testé lors :

- d’une navigation Turbo ;
- d’un retour arrière ;
- d’un remplacement de frame ;
- d’une reconnexion.

---

# 20. Synchronisation temps réel avec Mercure

## 20.1 Usage

Mercure diffuse :

- nouvelle énergie ;
- total de cafés ;
- passage d’un seuil ;
- résultats du vote ;
- clôture du vote ;
- accessoire gagnant ;
- compteurs de réactions ;
- fermeture du vendredi.

## 20.2 Topic

Exemple :

```text
https://duck-friday.example/fridays/2026-07-03
```

Les événements d’administration ou techniques peuvent utiliser des topics privés distincts.

## 20.3 Format d’événement

```json
{
  "eventId": "uuid",
  "type": "ENERGY_UPDATED",
  "friday": "2026-07-03",
  "occurredAt": "2026-07-03T11:18:00+02:00",
  "version": 37,
  "payload": {
    "previousEnergy": 41,
    "currentEnergy": 42,
    "coffeeCount": 210
  }
}
```

## 20.4 Version d’état

Chaque mise à jour collective porte un numéro de version croissant.

Le navigateur ignore un événement dont la version est inférieure ou égale à la dernière version appliquée.

Cela limite les incohérences lors :

- d’une reconnexion ;
- d’une récupération d’événements ;
- d’un ordre d’arrivée inhabituel.

## 20.5 Reconnexion

Après reconnexion :

1. Mercure récupère les événements disponibles lorsque possible ;
2. le client compare les versions ;
3. en cas de doute, une requête d’état complet est effectuée ;
4. le SVG rejoint l’état officiel avec une transition courte.

## 20.6 Dégradation

Si Mercure est indisponible :

- le bouton café continue à fonctionner ;
- sa réponse HTTP met à jour le navigateur local ;
- un rafraîchissement manuel récupère l’état ;
- un message discret indique que les données en direct sont momentanément indisponibles ;
- aucune interaction métier n’est perdue.

---

# 21. Architecture technique globale

```text
┌──────────────────────────────────────────┐
│                Navigateur                │
├──────────────────────────────────────────┤
│ Twig / HTML                              │
│ SVG inline                               │
│ Stimulus                                 │
│ Theatre.js Core                          │
│ CSS                                      │
│ EventSource Mercure                      │
└────────────────────┬─────────────────────┘
                     │ HTTPS
┌────────────────────▼─────────────────────┐
│          FrankenPHP / Caddy              │
└────────────────────┬─────────────────────┘
                     │
┌────────────────────▼─────────────────────┐
│             Application Symfony          │
├──────────────────────────────────────────┤
│ Présentation HTTP                        │
│ Domaine Friday / Duck / Visitor          │
│ Services d’application                   │
│ Doctrine                                 │
│ Messenger / Scheduler                    │
│ Mercure Publisher                        │
│ Instrumentation OpenTelemetry            │
└───────────┬───────────────┬──────────────┘
            │               │
┌───────────▼────────┐  ┌───▼───────────────────┐
│ PostgreSQL         │  │ Mercure Hub           │
└────────────────────┘  └───────────────────────┘
            │
┌───────────▼──────────────────────────────┐
│ OpenTelemetry Collector / Grafana Alloy  │
├──────────────────────────────────────────┤
│ Métriques                                │
│ Traces Tempo                             │
│ Logs Loki                                │
│ Dashboards et alertes Grafana            │
└──────────────────────────────────────────┘
```

---

# 22. Choix technologiques et justifications

## 22.1 Symfony

Utilisé pour :

- routage ;
- contrôleurs ;
- injection de dépendances ;
- validation ;
- Doctrine ;
- Messenger ;
- Scheduler ;
- Mercure ;
- sécurité ;
- console ;
- tests.

Raison : cohérence avec le parcours professionnel et qualité de structuration.

## 22.2 FrankenPHP

Utilisé comme serveur applicatif.

Objectifs :

- expérimenter le mode worker ;
- réduire le coût de démarrage ;
- étudier la persistance mémoire ;
- servir une application Symfony moderne.

Contrainte majeure : aucun état visiteur ne doit rester dans un service partagé entre deux requêtes.

## 22.3 Twig

Le rendu serveur est privilégié.

Raisons :

- première page rapide ;
- contenu compréhensible sans JavaScript ;
- SEO non critique mais naturel ;
- accessibilité facilitée ;
- absence de besoin pour une SPA.

## 22.4 PostgreSQL

Utilisé pour :

- vendredis ;
- visiteurs anonymes ;
- cafés ;
- votes ;
- conseils ;
- réactions ;
- résultats archivés.

Les contraintes d’unicité garantissent une partie de l’idempotence au niveau base.

## 22.5 SVG

Format maître du personnage et des accessoires.

Le SVG ne doit pas être considéré comme un simple fichier image, mais comme une scène graphique structurée.

## 22.6 Theatre.js

Utilisé pour :

- créer les séquences ;
- interpoler les transformations ;
- piloter les propriétés visuelles ;
- jouer les animations spéciales ;
- produire un état versionné.

## 22.7 Stimulus

Utilisé pour :

- initialiser Theatre.js ;
- appeler les endpoints ;
- écouter Mercure ;
- synchroniser le DOM ;
- gérer le cycle de vie.

## 22.8 Mercure

Utilisé lorsque plusieurs visiteurs doivent voir immédiatement le même changement.

Il évite un polling permanent et s’intègre à Symfony.

## 22.9 Messenger

Utilisé pour les effets secondaires et traitements rejouables :

- publication différée ;
- bilan ;
- notifications ;
- agrégations ;
- traitement d’événements.

## 22.10 Scheduler

Utilisé pour :

- ouvrir et fermer les étapes hebdomadaires ;
- clôturer le vote ;
- produire le bilan ;
- préparer le vendredi suivant.

La disponibilité du canard ne dépend jamais uniquement d’un déclenchement Scheduler.

## 22.11 OpenTelemetry et Grafana

Utilisés pour rendre le projet réellement observable.

## 22.12 Playwright

Utilisé pour tester :

- les parcours ;
- le temps ;
- l’état SVG ;
- les interactions ;
- les changements visuels ;
- le responsive ;
- la réduction de mouvement.

---

# 23. Modèle de données

## 23.1 FridayEdition

```text
id
friday_date
timezone
status
energy
energy_version
coffee_target
coffee_count
overcaffeination_count
vote_opens_at
vote_closes_at
winning_accessory_id
advice_id
created_at
closed_at
```

Contrainte :

```text
UNIQUE(friday_date, timezone)
```

## 23.2 AnonymousVisitor

```text
id
anonymous_identifier_hash
created_at
last_seen_at
total_visits
```

L’identifiant brut du cookie ne doit pas nécessairement être stocké en clair.

## 23.3 CoffeeContribution

```text
id
friday_edition_id
visitor_id
idempotency_key
energy_before
energy_after
created_at
```

Contraintes :

```text
UNIQUE(idempotency_key)
INDEX(friday_edition_id, visitor_id)
```

Le quota est vérifié dans une transaction.

## 23.4 Accessory

```text
id
code
label
description
slot
svg_group_id
entrance_sequence
active
created_at
```

## 23.5 FridayAccessoryOption

```text
id
friday_edition_id
accessory_id
display_order
vote_count
```

## 23.6 AccessoryVote

```text
id
friday_edition_id
visitor_id
accessory_id
created_at
```

Contrainte :

```text
UNIQUE(friday_edition_id, visitor_id)
```

## 23.7 Advice

```text
id
text
slug
active
created_at
```

## 23.8 AdviceReaction

```text
id
friday_edition_id
visitor_id
reaction
created_at
updated_at
```

Contrainte :

```text
UNIQUE(friday_edition_id, visitor_id)
```

## 23.9 FridayVisit

```text
id
friday_edition_id
visitor_id
first_seen_at
last_seen_at
```

Contrainte :

```text
UNIQUE(friday_edition_id, visitor_id)
```

---

# 24. API et contrats d’événements

## 24.1 Lecture de l’état

```http
GET /api/friday/current
```

Réponse :

```json
{
  "active": true,
  "date": "2026-07-03",
  "timezone": "Europe/Paris",
  "energy": 42,
  "energyState": "BARELY_FUNCTIONAL",
  "energyVersion": 37,
  "coffeeCount": 210,
  "overcaffeinationCount": 0,
  "visitor": {
    "remainingCoffees": 1,
    "hasVoted": true,
    "adviceReaction": "CONCERNING"
  },
  "vote": {
    "open": true,
    "closesAt": "2026-07-03T14:00:00+02:00",
    "options": []
  },
  "advice": {
    "text": "Déploie à 16 h 58. Ça crée des souvenirs avec l’équipe.",
    "reactions": {}
  }
}
```

## 24.2 Servir un café

```http
POST /api/friday/current/coffees
Idempotency-Key: <uuid>
```

Erreurs métier possibles :

```text
NOT_FRIDAY
COFFEE_LIMIT_REACHED
INVALID_IDEMPOTENCY_KEY
FRIDAY_CLOSED
TEMPORARILY_UNAVAILABLE
```

## 24.3 Voter

```http
POST /api/friday/current/accessory-votes
```

Payload :

```json
{
  "accessory": "cto_glasses"
}
```

Erreurs :

```text
NOT_FRIDAY
VOTE_CLOSED
ALREADY_VOTED
INVALID_ACCESSORY
```

## 24.4 Réagir

```http
PUT /api/friday/current/advice-reaction
```

Payload :

```json
{
  "reaction": "CONCERNING"
}
```

## 24.5 Événements domaine

- `FridayOpened`
- `CoffeeServed`
- `EnergyChanged`
- `EnergyThresholdReached`
- `CaffeineIncidentStarted`
- `AccessoryVoteCast`
- `AccessoryVoteClosed`
- `AccessoryWinnerSelected`
- `AdviceReactionChanged`
- `FridayClosed`

---

# 25. Messenger et Scheduler

## 25.1 Planification proposée

```text
Jeudi 23:55    Préparer l’édition
Vendredi 00:00 Publier FridayOpened
Vendredi 14:00 Clôturer le vote
Vendredi 14:01 Publier le gagnant
Vendredi 23:55 Préparer le bilan
Samedi 00:00   Fermer l’édition
Samedi 00:05   Générer le rapport
```

## 25.2 Principe de fiabilité

Le Scheduler déclenche des commandes. Il ne constitue pas l’unique preuve de l’état.

Exemple :

- même si `FridayOpened` n’a pas été exécuté, une requête le vendredi doit pouvoir créer ou réparer l’édition ;
- même si la clôture a été retardée, une tentative de vote après 14 h doit être refusée par la règle métier ;
- un traitement de rattrapage doit être possible.

## 25.3 Idempotence des messages

Clés proposées :

```text
friday-open:2026-07-03
accessory-close:2026-07-03
accessory-winner:2026-07-03
friday-close:2026-07-03
weekly-report:2026-W27
```

## 25.4 File d’échec

Les messages non traités après plusieurs tentatives sont placés dans une file d’échec.

Un runbook doit expliquer :

- comment les inspecter ;
- comment identifier la cause ;
- comment les rejouer ;
- comment éviter un doublon.

---

# 26. Observabilité

## 26.1 Objectif

Le projet doit permettre de diagnostiquer :

- une énergie incohérente ;
- un café refusé ;
- un vote non clôturé ;
- un accessoire non appliqué ;
- une fuite mémoire worker ;
- une latence inhabituelle ;
- un événement Mercure non publié ;
- une séquence visuelle non initialisée.

## 26.2 Traces

Spans métier recommandés :

```text
friday.current.resolve
visitor.resolve
coffee.contribution.validate
coffee.contribution.persist
energy.recalculate
accessory.vote.cast
accessory.vote.close
accessory.winner.resolve
advice.reaction.update
mercure.update.publish
```

## 26.3 Métriques techniques

```text
http.server.request.duration
http.server.request.count
http.server.error.count
db.client.operation.duration
messenger.message.processed
messenger.message.failed
worker.memory.bytes
mercure.publish.count
mercure.publish.failure
```

## 26.4 Métriques métier

```text
duck.energy
duck.energy.state
duck.coffee.total
duck.coffee.rejected
duck.overcaffeination.total
duck.accessory.vote.total
duck.accessory.winner
duck.advice.reaction.total
duck.friday.unique_visitors
```

## 26.5 Métriques front-end

Une télémétrie front minimale peut suivre :

```text
duck.animation.init.duration
duck.animation.init.failure
duck.animation.sequence.failure
duck.mercure.connection.state
duck.mercure.reconnect.count
duck.svg.missing_target
```

Elle ne doit pas envoyer de données personnelles inutiles.

## 26.6 Dashboard

Le dashboard principal affiche :

- vendredi actif ou non ;
- énergie ;
- état du canard ;
- cafés ;
- vitesse de progression ;
- visiteurs uniques ;
- résultats du vote ;
- conseil actif ;
- réactions ;
- erreurs HTTP ;
- latence ;
- mémoire worker ;
- messages en échec ;
- publications Mercure ;
- version déployée.

## 26.7 Alertes

### Critique

```text
Nous sommes vendredi, mais le canard est DORMANT.
```

### Énergie incohérente

```text
L’énergie sort de l’intervalle 0–100.
```

### Vote

```text
Le vote est toujours ouvert après l’heure de clôture.
```

### Temps réel

```text
Le taux d’échec de publication Mercure dépasse le seuil.
```

### Worker

```text
La mémoire d’un worker augmente durablement.
```

### Animation

```text
Le taux d’échec d’initialisation Theatre.js dépasse le seuil.
```

---

# 27. Sécurité et respect de la vie privée

## 27.1 Identité anonyme

L’identité est générée aléatoirement.

Elle ne contient :

- ni nom ;
- ni e-mail ;
- ni information de navigateur ;
- ni adresse précise ;
- ni donnée professionnelle.

## 27.2 Cookie

Configuration recommandée :

```text
HttpOnly
Secure en production
SameSite=Lax
Path=/
Durée documentée
```

## 27.3 Validation serveur

Toutes les actions sont revalidées côté serveur.

Le SVG, Stimulus et Theatre.js n’ont aucune autorité de sécurité.

## 27.4 Rate limiting

Limiter :

- cafés par visiteur ;
- fréquence brute par IP ou session ;
- votes ;
- réactions ;
- endpoints publics.

L’adresse IP ne doit pas devenir l’identité principale.

## 27.5 CSRF

Les actions modifiant l’état doivent être protégées selon l’architecture HTTP retenue.

## 27.6 Secrets

Aucun secret dans Git.

Le dépôt inclut :

```text
.env.example
```

Une détection de secrets est exécutée en CI.

## 27.7 Contenu SVG

Les SVG du projet sont produits et contrôlés en interne.

Aucun SVG utilisateur non nettoyé ne doit être injecté dans le DOM.

---

# 28. Accessibilité et performances visuelles

## 28.1 Réduction de mouvement

Le projet respecte :

```css
@media (prefers-reduced-motion: reduce)
```

En mode réduit :

- les animations idle continues sont désactivées ;
- les tremblements sont supprimés ;
- les transitions sont raccourcies ou remplacées par des fondus ;
- les changements d’état restent compréhensibles ;
- l’énergie est toujours visible textuellement.

## 28.2 Contrôle utilisateur

Une option locale peut permettre de :

- mettre le canard en pause ;
- réduire les animations ;
- réactiver les animations.

Cette préférence peut être stockée localement sans compte.

## 28.3 Alternative textuelle

Le SVG contient un titre et une description.

Un texte adjacent décrit l’état :

```text
Le canard est presque fonctionnel. Énergie : 42 %.
```

Les accessoires ne sont pas communiqués uniquement par l’image :

```text
Accessoire porté : lunettes de soleil de CTO.
```

## 28.4 Couleurs

L’énergie ne doit pas être distinguée uniquement par une couleur.

Utiliser :

- valeur numérique ;
- libellé ;
- changement de posture ;
- motif ou icône.

## 28.5 Clavier

Toutes les interactions sont accessibles au clavier.

Le SVG décoratif ne doit pas capturer le focus inutilement.

## 28.6 Chargement

Objectifs :

- SVG principal optimisé ;
- état Theatre.js chargé avec le bundle ;
- aucun Studio en production ;
- chargement différé des assets non visibles ;
- pas de vidéo lourde ;
- pas de canvas plein écran nécessaire.

## 28.7 Onglet en arrière-plan

Lorsque la page est cachée :

- suspendre les animations non essentielles ;
- conserver la connexion temps réel si raisonnable ;
- rattraper l’état officiel au retour ;
- ne pas tenter de rejouer toutes les animations manquées.

## 28.8 Absence de JavaScript

Sans JavaScript :

- le canard statique reste visible ;
- l’état serveur reste exact ;
- l’énergie et l’accessoire sont lisibles ;
- les formulaires peuvent fonctionner par soumission classique si retenu ;
- le concept reste compréhensible.

---

# 29. Stratégie de tests

## 29.1 Tests unitaires métier

Couvrir :

- les sept jours ;
- les changements de date ;
- les changements d’année ;
- le fuseau ;
- les passages d’heure ;
- le quota de cafés ;
- l’énergie maximale ;
- la surcaféination ;
- l’unicité du vote ;
- la clôture à 14 h ;
- les égalités ;
- l’unicité de réaction ;
- la résolution des états d’énergie.

## 29.2 Tests d’intégration

Couvrir :

- transactions PostgreSQL ;
- contraintes uniques ;
- concurrence de deux cafés ;
- double requête avec la même clé ;
- publication Mercure ;
- message Messenger ;
- nouvelle tentative ;
- clôture idempotente.

## 29.3 Tests JavaScript

Couvrir :

- mapping énergie → propriétés visuelles ;
- priorité des animations ;
- événements Mercure obsolètes ;
- reconnexion ;
- nettoyage Stimulus ;
- mode reduced motion ;
- absence d’un groupe SVG attendu.

## 29.4 Tests Playwright

### Hors vendredi

- canard dormant ;
- bouton café indisponible ;
- prochain vendredi visible.

### Vendredi

- bouton disponible ;
- café accepté ;
- jauge mise à jour ;
- animation locale lancée ;
- quota affiché.

### Temps réel

- deux contextes navigateur ;
- un café dans le premier ;
- mise à jour du second ;
- pas d’animation longue du café chez le second.

### Vote

- vote accepté avant 14 h ;
- vote refusé après 14 h ;
- accessoire gagnant affiché.

### Conseil

- conseil visible ;
- réaction enregistrée ;
- compteur mis à jour.

### Accessibilité

- navigation clavier ;
- libellés ;
- mode réduction de mouvement ;
- aucun mouvement continu essentiel.

## 29.5 Régression visuelle

Des captures de référence peuvent être conservées pour :

- chaque état d’énergie ;
- chaque emplacement d’accessoire ;
- mobile ;
- desktop ;
- mode reduced motion.

Les tests doivent accepter les variations mineures d’anti-aliasing et cibler les régressions utiles.

## 29.6 Tests de performance

Mesurer :

- temps d’initialisation Theatre.js ;
- taille du SVG ;
- taille de l’état JSON ;
- mémoire navigateur ;
- fluidité sur appareil moyen ;
- mémoire FrankenPHP ;
- comportement sous rafale de cafés.

---

# 30. Organisation du code et des assets

```text
assets/
├── controllers/
│   ├── duck_controller.ts
│   ├── coffee_controller.ts
│   ├── accessory_vote_controller.ts
│   └── advice_reaction_controller.ts
├── animation/
│   ├── duck/
│   │   ├── theatre-project.ts
│   │   ├── duck-friday.state.json
│   │   ├── visual-state.ts
│   │   ├── animation-priority.ts
│   │   └── sequences.ts
│   └── accessibility/
│       └── reduced-motion.ts
├── styles/
└── bootstrap.ts

design/
├── duck/
│   ├── duck-source.svg
│   ├── accessories-source.svg
│   └── README.md
└── exports/

templates/
├── home/
├── duck/
│   ├── _scene.svg.twig
│   ├── _energy.html.twig
│   ├── _coffee_action.html.twig
│   ├── _accessory_vote.html.twig
│   └── _advice.html.twig
└── components/

src/
├── Domain/
│   ├── Friday/
│   ├── Duck/
│   ├── Coffee/
│   ├── Accessory/
│   ├── Advice/
│   ├── Visitor/
│   └── Shared/Clock/
├── Application/
│   ├── Command/
│   ├── Query/
│   ├── Handler/
│   └── View/
├── Infrastructure/
│   ├── Persistence/
│   ├── Messaging/
│   ├── Mercure/
│   ├── Clock/
│   └── Observability/
└── Presentation/
    ├── Http/
    └── Console/

tests/
├── Unit/
├── Integration/
├── Functional/
├── JavaScript/
└── EndToEnd/
```

## 30.1 Source et export

Deux fichiers sont conservés :

- source graphique riche pour l’édition ;
- export web optimisé pour le runtime.

La procédure de mise à jour doit être documentée afin d’éviter qu’un export manuel non reproductible devienne la seule version exploitable.

---

# 31. Intégration et déploiement continus

## 31.1 Pipeline backend

- Composer validate ;
- installation ;
- formatage ;
- PHPStan ;
- tests unitaires ;
- tests d’intégration ;
- audit ;
- migrations de test.

## 31.2 Pipeline front-end

- installation des dépendances ;
- TypeScript ;
- lint ;
- tests unitaires ;
- vérification de l’import de l’état Theatre.js ;
- optimisation SVG ;
- validation des IDs obligatoires ;
- build de production ;
- contrôle de l’absence de `@theatre/studio` dans le bundle public.

## 31.3 Tests end-to-end

- démarrage Docker Compose ;
- horloge simulée ;
- Playwright ;
- captures ;
- arrêt et nettoyage.

## 31.4 Image

L’image de production doit :

- contenir les assets compilés ;
- utiliser FrankenPHP ;
- ne pas contenir les outils de développement inutiles ;
- exposer la version ;
- disposer d’un health check.

## 31.5 Déploiement

Après déploiement :

- smoke test ;
- état courant contrôlé ;
- endpoint santé vérifié ;
- version annotée dans Grafana ;
- initialisation de l’animation vérifiée ;
- rollback disponible.

---

# 32. Feuille de route

## Phase 0 — Cadrage visuel

- définir le style final du canard ;
- dessiner les poses principales ;
- définir les points de pivot ;
- produire trois accessoires ;
- valider la structure SVG ;
- créer un prototype Theatre.js hors Symfony.

**Critère de sortie :** l’énergie peut faire passer le canard de fatigué à surcaféiné dans une page de démonstration.

## Phase 1 — Domaine temporel

- Symfony ;
- horloge injectable ;
- vendredi actif ;
- édition hebdomadaire ;
- tests des dates.

**Critère de sortie :** le backend expose un état fiable sans base complexe.

## Phase 2 — Café

- identité anonyme ;
- PostgreSQL ;
- contribution ;
- quota ;
- idempotence ;
- calcul d’énergie ;
- intégration SVG/Theatre.js.

**Critère de sortie :** un café modifie l’état officiel et l’animation locale.

## Phase 3 — Temps réel

- Mercure ;
- version d’état ;
- deux navigateurs ;
- réaction distante agrégée ;
- reconnexion.

**Critère de sortie :** deux utilisateurs voient la même énergie sans recharger.

## Phase 4 — Accessoires

- catalogue ;
- trois options ;
- vote ;
- clôture ;
- égalité ;
- application au SVG ;
- animation de révélation.

**Critère de sortie :** le gagnant est déterminé et porté de manière fiable.

## Phase 5 — Conseil

- catalogue ;
- sélection hebdomadaire ;
- réactions ;
- partage ;
- compteurs en temps réel.

**Critère de sortie :** le vendredi contient son conseil unique et ses réactions.

## Phase 6 — Asynchrone

- Messenger ;
- Scheduler ;
- clôture ;
- bilan ;
- file d’échec ;
- runbook.

## Phase 7 — Observabilité

- OpenTelemetry ;
- métriques ;
- traces ;
- logs ;
- dashboard ;
- alertes.

## Phase 8 — Qualité complète

- Playwright ;
- reduced motion ;
- régression visuelle ;
- charge ;
- mémoire worker ;
- CI complète.

## Phase 9 — Production

- hébergement ;
- HTTPS ;
- sauvegardes ;
- secrets ;
- déploiement ;
- rollback ;
- supervision.

---

# 33. Critères de recette

## 33.1 Produit

- [ ] Le concept est compris en moins de dix secondes.
- [ ] Le canard dort hors vendredi.
- [ ] Le canard se réveille le vendredi.
- [ ] Un café fait progresser l’énergie officielle.
- [ ] L’énergie modifie visuellement le canard.
- [ ] Le visiteur est limité à trois cafés.
- [ ] Trois accessoires sont proposés.
- [ ] Un seul vote est accepté.
- [ ] Le vote ferme à 14 h.
- [ ] Le gagnant est porté par le canard.
- [ ] Un conseil unique est affiché.
- [ ] Une réaction peut être enregistrée.
- [ ] L’application reste amusante avec un seul visiteur.

## 33.2 Visuel

- [ ] Le canard est un SVG inline structuré.
- [ ] Les groupes ont des IDs stables.
- [ ] Theatre.js Studio n’est pas livré en production.
- [ ] L’état JSON est versionné.
- [ ] Les transitions entre niveaux sont fluides.
- [ ] L’animation café revient vers l’énergie courante.
- [ ] Les accessoires suivent correctement la tête ou le corps.
- [ ] Le mode reduced motion est fonctionnel.
- [ ] L’état reste compréhensible sans animation.

## 33.3 Technique

- [ ] Symfony reste la source de vérité.
- [ ] Les doubles clics ne doublent pas les cafés.
- [ ] Deux requêtes concurrentes respectent le quota.
- [ ] Les événements Mercure sont versionnés.
- [ ] Une reconnexion récupère l’état correct.
- [ ] Le vote reste fermé même si Scheduler a du retard.
- [ ] Aucun état visiteur ne fuit entre les workers.
- [ ] PostgreSQL indisponible n’empêche pas l’affichage du jour.
- [ ] La télémétrie indisponible ne bloque pas l’application.

## 33.4 Qualité

- [ ] PHPStan passe.
- [ ] TypeScript passe.
- [ ] Les tests unitaires passent.
- [ ] Les tests Playwright passent.
- [ ] Le build ne contient pas Theatre.js Studio.
- [ ] Les assets sont optimisés.
- [ ] Les dépendances sont auditées.
- [ ] Le README explique le développement des animations.

---

# 34. Risques et mesures de maîtrise

| Risque | Impact | Mesure |
|---|---:|---|
| SVG mal structuré | Élevé | Convention d’IDs et prototype avant le dessin final |
| Conflits entre animations | Élevé | Politique de priorité centralisée |
| Studio Theatre.js livré en production | Moyen | Contrôle automatique du bundle |
| Animation devenue logique métier | Élevé | Valeurs officielles uniquement côté serveur |
| Rafale de cafés visuellement illisible | Moyen | Animation complète locale, agrégation distante |
| Événements Mercure désordonnés | Moyen | Numéro de version d’état |
| Fuite d’état FrankenPHP | Élevé | Services stateless et tests |
| Double vote ou double café | Élevé | Transactions, contraintes et idempotence |
| Projet trop ambitieux | Élevé | Phases et MVP strict |
| Accessoires difficiles à aligner | Moyen | Slots SVG et repères documentés |
| Performance mobile insuffisante | Moyen | SVG simple, pas de Studio, mesures |
| Animation inconfortable | Élevé | Reduced motion et bouton pause |
| Peu de visiteurs | Faible | Progression adaptée et contenu autonome |
| Conseil mal interprété | Moyen | Ton explicite et sélection manuelle |
| Dépendance Theatre.js abandonnée | Moyen | SVG natif, couche d’adaptation isolée |

---

# 35. Fonctionnalités hors périmètre

- Rive ;
- moteur de jeu ;
- Godot ;
- PixiJS pour le MVP ;
- 3D ;
- compte utilisateur ;
- paiement ;
- boutique d’accessoires ;
- accessoires soumis par le public ;
- IA publiant automatiquement ;
- messagerie ;
- classement compétitif ;
- application native ;
- blockchain ;
- Kafka ;
- Kubernetes au démarrage ;
- microservices au démarrage.

---

# 36. Évolutions possibles

## 36.1 Types de café

- café de machine ;
- espresso ;
- double espresso ;
- décaféiné sans effet ;
- eau chaude déclenchant une réaction offensée.

Cette évolution nécessite un équilibrage et ne fait pas partie du MVP.

## 36.2 Succès

- premier café ;
- cinq vendredis ;
- visite à minuit ;
- visite à 16 h 58 ;
- participation à une surcaféination ;
- vote pour cinq gagnants.

## 36.3 Historique visuel

Une galerie peut montrer :

- accessoire gagnant ;
- énergie finale ;
- conseil ;
- nombre de visiteurs ;
- bilan.

## 36.4 Mercure enrichi

- présence en direct ;
- compteur de visiteurs connectés ;
- célébration collective ;
- réaction lors des seuils.

## 36.5 Scène plus riche

Le canard pourrait disposer d’un bureau interactif.

Cette évolution doit rester en SVG/DOM tant que la complexité ne justifie pas un moteur de rendu plus lourd.

## 36.6 Service Go expérimental

Un petit service peut être ajouté uniquement pour apprendre :

- contrat d’API ;
- propagation de trace ;
- communication réseau ;
- déploiement séparé.

## 36.7 Kubernetes

Kubernetes peut constituer un exercice final indépendant. Il ne doit pas devenir une condition pour afficher un canard.

---

# 37. Livrables attendus

## 37.1 Produit

- application Symfony ;
- SVG principal ;
- trois accessoires minimum ;
- animations Theatre.js ;
- état JSON ;
- pages Twig ;
- contrôleurs Stimulus ;
- endpoints ;
- temps réel ;
- base et migrations.

## 37.2 Documentation

- `README.md` ;
- `CONTRIBUTING.md` ;
- `docs/product.md` ;
- `docs/architecture.md` ;
- `docs/animation-system.md` ;
- `docs/svg-conventions.md` ;
- `docs/observability.md` ;
- `docs/deployment.md` ;
- `docs/runbook.md` ;
- `docs/adr/` ;
- `.env.example`.

## 37.3 Animation

La documentation d’animation doit expliquer :

- comment ouvrir le Studio ;
- comment modifier une séquence ;
- comment exporter l’état ;
- comment vérifier le bundle ;
- comment ajouter un groupe SVG ;
- comment créer un accessoire ;
- comment tester reduced motion ;
- comment résoudre un conflit de priorité.

## 37.4 Exploitation

- dashboard Grafana ;
- règles d’alerte ;
- health checks ;
- procédure de rollback ;
- procédure de rejeu Messenger ;
- sauvegardes ;
- version déployée.

---

# 38. Références officielles

- [Theatre.js — Documentation](https://www.theatrejs.com/docs/latest/)
- [Theatre.js — HTML et SVG](https://www.theatrejs.com/docs/latest/getting-started/with-html-svg)
- [Theatre.js — API Core](https://www.theatrejs.com/docs/latest/api/core)
- [Theatre.js — Projets et état exporté](https://www.theatrejs.com/docs/latest/manual/projects)
- [Theatre.js — Dépôt GitHub et licences](https://github.com/theatre-js/theatre)
- [Symfony StimulusBundle](https://symfony.com/bundles/StimulusBundle/current/index.html)
- [Symfony Mercure](https://symfony.com/doc/current/mercure.html)
- [Symfony Messenger](https://symfony.com/doc/current/messenger.html)
- [Symfony Scheduler](https://symfony.com/doc/current/scheduler.html)
- [FrankenPHP](https://frankenphp.dev/docs/)
- [OpenTelemetry PHP](https://opentelemetry.io/docs/languages/php/)
- [Grafana](https://grafana.com/docs/)
- [Playwright](https://playwright.dev/docs/intro)
- [MDN — SVG transform](https://developer.mozilla.org/en-US/docs/Web/SVG/Reference/Attribute/transform)
- [MDN — SVG transform-origin](https://developer.mozilla.org/en-US/docs/Web/SVG/Reference/Attribute/transform-origin)
- [MDN — prefers-reduced-motion](https://developer.mozilla.org/fr/docs/Web/CSS/Reference/At-rules/prefers-reduced-motion)
- [Inkscape](https://inkscape.org/)

Les versions exactes des dépendances seront verrouillées lors de l’initialisation du dépôt. Le présent document fixe les responsabilités et les choix d’architecture, pas un numéro de version définitif.

---

# 39. Conclusion

**Le Canard du Vendredi** doit rester un produit volontairement inutile, mais techniquement défendable.

La boucle choisie est claire :

1. le canard se réveille épuisé ;
2. les visiteurs le caféinent collectivement ;
3. son apparence évolue ;
4. la communauté choisit son accessoire ;
5. il délivre un conseil professionnel catastrophique ;
6. il s’effondre à minuit ;
7. le système produit un rapport beaucoup trop sérieux.

Le choix **SVG + Theatre.js** donne au projet une identité technique forte :

- format web natif ;
- contrôle complet ;
- solution ouverte ;
- animation pilotée par les données ;
- intégration naturelle à Symfony ;
- vraie matière d’apprentissage.

Le succès du projet ne se mesurera pas à son utilité.

Il se mesurera à la qualité de cette réponse :

> « Pourquoi avez-vous construit une infrastructure observable, temps réel et animée pour donner du café à un canard ? »

> « Parce que c’était le meilleur endroit pour apprendre à le faire correctement. »
