import { expect, test } from '@playwright/test';
import { openDuck, sel } from './helpers.ts';

// Scénario 7 (DORMANT) — project « dormant » : APP_FAKE_NOW = un NON-vendredi.

test('7c. non-vendredi : canard dormant, interactions désactivées', async ({ page }) => {
  await openDuck(page);

  // Autorité horloge (§7.2) : café impossible, état dormant.
  await expect(page.locator(sel.coffeeButton)).toBeDisabled();
  await expect(page.locator(sel.energyState)).toHaveText(/dormant/i);

  // Hors vendredi, ni vote ni conseil ne sont rendus (§10, §11).
  await expect(page.locator(sel.votePanel)).toHaveCount(0);
  await expect(page.locator(sel.advicePanel)).toHaveCount(0);
});

// §13, §7.2 : hors vendredi, le gabarit /duck affiche la date du PROCHAIN vendredi
// (CurrentFridayView::$date, fournie par le serveur même quand active=false).
test('7c-bis. DORMANT affiche le prochain vendredi', async ({ page }) => {
  await openDuck(page);
  await expect(page.getByText(/de retour vendredi|prochain vendredi/i)).toBeVisible();
});
