import { expect, test } from '@playwright/test';
import { openDuck, readCount, sel } from './helpers.ts';

// Scénarios 4 (avant 14:00) & 5 (§10, §11) — project « friday ».

test('4. vote : voter verrouille le choix (autorité serveur, hasVoted)', async ({ page }) => {
  await openDuck(page);
  await expect(page.locator(sel.votePanel)).toBeVisible();

  const code = await page.locator('.vote-option').first().getAttribute('data-code');
  expect(code).toBeTruthy();

  await page.locator(sel.voteOption(code as string)).click();

  // hasVoted (autorité serveur) → toutes les options se verrouillent.
  for (const option of await page.locator('.vote-option').all()) {
    await expect(option).toBeDisabled();
  }
});

test('4bis. vote cross-onglets : les résultats montent chez l’autre (barrière de séquence)', async ({ browser }) => {
  const contextA = await browser.newContext();
  const contextB = await browser.newContext();
  const pageA = await contextA.newPage();
  const pageB = await contextB.newPage();
  await openDuck(pageA);
  await openDuck(pageB);

  const code = (await pageA.locator('.vote-option').first().getAttribute('data-code')) as string;
  const countB0 = await readCount(pageB, sel.voteCount(code));

  await pageA.locator(sel.voteOption(code)).click();

  await expect(pageB.locator(sel.voteCount(code))).toHaveText(String(countB0 + 1), { timeout: 15_000 });

  await contextA.close();
  await contextB.close();
});

test('5. réactions : changer de réaction met à jour cross-onglets, re-clic = no-op', async ({ browser }) => {
  const contextA = await browser.newContext();
  const contextB = await browser.newContext();
  const pageA = await contextA.newPage();
  const pageB = await contextB.newPage();
  await openDuck(pageA);
  await openDuck(pageB);
  await expect(pageA.locator(sel.advicePanel)).toBeVisible();

  const countB0 = await readCount(pageB, sel.adviceCount('CONCERNING'));

  await pageA.locator(sel.adviceButton('CONCERNING')).click();
  await expect(pageA.locator(sel.adviceButton('CONCERNING'))).toHaveAttribute('aria-pressed', 'true');
  await expect(pageB.locator(sel.adviceCount('CONCERNING'))).toHaveText(String(countB0 + 1), { timeout: 15_000 });

  // Re-clic MÊME réaction → no-op serveur : le compteur ne bouge pas.
  await pageA.locator(sel.adviceButton('CONCERNING')).click();
  await pageA.waitForTimeout(1500);
  expect(await readCount(pageA, sel.adviceCount('CONCERNING'))).toBe(countB0 + 1);

  await contextA.close();
  await contextB.close();
});
