import { expect, test } from '@playwright/test';
import { openDuck, readEnergy, readRemaining, sel, serveCoffee } from './helpers.ts';

// Scénarios 1–3 (§13, §8) — un (faux) vendredi avant 14:00 (project « friday »).

test('1. café même onglet : clic → POST → énergie serveur reflétée', async ({ page }) => {
  await openDuck(page);
  const energy0 = await readEnergy(page);
  const remaining0 = await readRemaining(page);

  await serveCoffee(page);

  // Autorité serveur : +1 énergie officielle, -1 café restant (mécanique §8).
  expect(await readEnergy(page)).toBe(energy0 + 1);
  expect(await readRemaining(page)).toBe(remaining0 - 1);
});

test('2. café cross-onglets : l’onglet B reçoit la diffusion via Mercure (relais actif)', async ({ browser }) => {
  // Deux visiteurs distincts (contextes = cookies neufs) → quotas indépendants.
  const contextA = await browser.newContext();
  const contextB = await browser.newContext();
  const pageA = await contextA.newPage();
  const pageB = await contextB.newPage();
  await openDuck(pageA);
  await openDuck(pageB);

  const energyB0 = await readEnergy(pageB);
  const remainingB0 = await readRemaining(pageB);

  await serveCoffee(pageA);

  // Toute la chaîne async : POST A → outbox → RELAIS → Mercure → onglet B.
  await expect(pageB.locator(sel.energyValue)).toHaveText(String(energyB0 + 1), { timeout: 15_000 });
  // Invariant B : le quota de B n'est JAMAIS touché par le café d'un autre.
  expect(await readRemaining(pageB)).toBe(remainingB0);

  await contextA.close();
  await contextB.close();
});

test('3. quota & idempotence : 3 cafés puis bouton désactivé, +3 exactement', async ({ page }) => {
  await openDuck(page);
  const energy0 = await readEnergy(page);
  expect(await readRemaining(page)).toBe(3);

  await serveCoffee(page);
  await serveCoffee(page);
  await serveCoffee(page);

  // Quota atteint (§8.2) : 4e café impossible côté UI (autorité serveur).
  expect(await readRemaining(page)).toBe(0);
  await expect(page.locator(sel.coffeeButton)).toBeDisabled();
  // Aucune sur-incrémentation (idempotence/anti double-soumission §8.6).
  expect(await readEnergy(page)).toBe(energy0 + 3);
});
