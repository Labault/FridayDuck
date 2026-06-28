import { expect, test } from '@playwright/test';
import { openDuck, sel } from './helpers.ts';

// Scénarios 4 (après 14:00) & 6 (§10.5, §10.6) — project « afternoon ».
// L'horloge (APP_FAKE_NOW) place cette instance APRÈS la clôture du vote.

test('4. après 14:00 : vote fermé et gagnant figé', async ({ page }) => {
  await openDuck(page);

  if (await page.locator(sel.votePanel).count()) {
    for (const option of await page.locator('.vote-option').all()) {
      await expect(option).toBeDisabled(); // vote fermé (autorité horloge §10.6)
    }
  }
  // Un gagnant est figé et monté.
  await expect(page.locator('.duck-accessory.is-mounted')).toHaveCount(1);
});

test('6. late-join : l’accessoire est MONTÉ sans rejouer la proclamation (régression 4b)', async ({ page }) => {
  await openDuck(page);

  // Montage STATIQUE depuis l'état serveur (winnerValue) — pas de proclamation.
  await expect(page.locator('.duck-accessory.is-mounted')).toHaveCount(1);
  await expect(page.locator(sel.proclamation)).toBeHidden();
});

test('6bis. l’accessoire suit la tête/le corps et porte une alternative textuelle (§28.3)', async ({ page }) => {
  await openDuck(page);

  // Monté dans un slot du rig → suit la tête/le corps par héritage SVG.
  const mounted = page.locator('.duck-accessory.is-mounted');
  await expect(mounted).toHaveCount(1);
  await expect(page.locator('[id$="-accessory-slot"] .duck-accessory.is-mounted')).toHaveCount(1);
  // Alternative textuelle exposée (accessibilité §28.3).
  await expect(page.locator(sel.accessoryLabel)).toContainText('Accessoire porté');
});
