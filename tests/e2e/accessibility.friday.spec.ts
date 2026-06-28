import { expect, test } from '@playwright/test';
import { openDuck, readEnergy, sel, serveCoffee } from './helpers.ts';

// Scénario 7 (animation & accessibilité, §28.1, §15.4/§17.1) — project « friday ».

test.describe('7a. prefers-reduced-motion', () => {
  test('poses statiques, mais énergie/serveur toujours corrects (pose, pas mouvement)', async ({ page }) => {
    // Émulation par la voie runtime (CDP) : fiable dans ce runner, contrairement
    // à l'option de contexte `test.use({ reducedMotion })` qui n'y est pas
    // propagée (matchMedia resterait à false). Posée AVANT la navigation pour
    // que le contrôleur lise le mode dès connect() (§28.1).
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await openDuck(page);

    // Le contrôleur signale le mode (§28.1) ; aucune animation continue.
    await expect(page.locator(sel.reducedFlag)).toHaveText(/reduced-motion/i);

    // L'autorité serveur reste effective : le café met à jour la POSE (valeur).
    const energy0 = await readEnergy(page);
    await serveCoffee(page);
    expect(await readEnergy(page)).toBe(energy0 + 1);
  });
});

test('7b. Theatre.js Studio JAMAIS dans le build prod servi (§15.4/§17.1)', async ({ page }) => {
  const studioRequests: string[] = [];
  page.on('request', (request) => {
    if (/studio/i.test(request.url())) {
      studioRequests.push(request.url());
    }
  });

  await openDuck(page);
  // L'EventSource Mercure (SSE) de /duck reste OUVERT en permanence : `networkidle`
  // ne se résout jamais (incompatible par conception avec un SSE persistant). On
  // attend l'état `load` puis un court settle BORNÉ — assez pour que d'éventuels
  // chunks (le dynamic import Studio, présent seulement en DEV) soient requêtés —
  // sans attente infinie. Les requêtes sont capturées depuis le goto (listener
  // posé avant openDuck), donc l'assertion s'évalue bien APRÈS le chargement.
  await page.waitForLoadState('load');
  await page.waitForTimeout(1000);

  // Le build prod tree-shake @theatre/studio : aucun chunk Studio n'est chargé.
  expect(studioRequests, `chunks Studio chargés : ${studioRequests.join(', ')}`).toHaveLength(0);
  // Complément : aucun bundle Studio n'a exposé son global.
  const studioGlobalPresent = await page.evaluate(() => {
    const w = window as unknown as Record<string, unknown>;
    return Object.keys(w).some((key) => /theatre/i.test(key) && /studio/i.test(key));
  });
  expect(studioGlobalPresent).toBe(false);
});
