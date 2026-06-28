import { describe, expect, it } from 'vitest';
import { shouldProclaim } from '../../assets/duck/vote/winnerProclamation.ts';

// Régression 4b (§10.5) : MONTAGE (persistant) ≠ PROCLAMATION (transitoire).
// Le montage de l'accessoire gagnant est assuré par duck_controller dès qu'un
// gagnant existe (y compris au late-join) ; ce garde-fou protège la SECONDE
// règle — la proclamation ne se joue que sur l'événement temps-réel.
describe('shouldProclaim — proclamation transitoire seulement en direct (§10.5, régression 4b)', () => {
  it('un gagnant déjà présent dans l’état initial Twig (late-join) ne déclenche PAS la proclamation', () => {
    // L'accessoire est MONTÉ (état persistant, ailleurs), mais le bandeau de
    // proclamation ne doit PAS rejouer au chargement. Pendant exact de
    // « coffee_receive ne joue pas sur l'état initial ».
    expect(shouldProclaim('initial')).toBe(false);
  });

  it('un événement live ACCESSORY_WINNER_SELECTED déclenche la proclamation', () => {
    // Visiteur présent quand 14h tombe : transition pas-de-gagnant → gagnant
    // observée EN DIRECT → la fanfare doit toujours se jouer.
    expect(shouldProclaim('live')).toBe(true);
  });
});
