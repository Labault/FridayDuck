export type WinnerSource = 'initial' | 'live';

/**
 * Décide si la PROCLAMATION transitoire d'un accessoire gagnant (§10.5) doit se
 * jouer, selon la PROVENANCE du gagnant. Deux choses DISTINCTES (régression 4b) :
 *
 *  - MONTAGE de l'accessoire = état PERSISTANT, assuré ailleurs (duck_controller,
 *    qui possède le SVG) : se produit dès qu'un gagnant existe, y compris au
 *    late-join (gagnant déjà présent dans l'état initial Twig), et survit jusqu'à
 *    minuit. Il n'est JAMAIS conditionné par la proclamation ni par l'animation.
 *  - PROCLAMATION = TRANSITOIRE : ne se joue QUE sur la transition pas-de-gagnant
 *    → gagnant observée EN DIRECT (ACCESSORY_WINNER_SELECTED reçu via Mercure),
 *    jamais sur l'état-au-chargement. C'est le pendant exact de « coffee_receive
 *    ne joue pas sur l'état initial » : on distingue l'état-au-chargement de
 *    l'événement temps-réel.
 */
export function shouldProclaim(source: WinnerSource): boolean {
  return source === 'live';
}
