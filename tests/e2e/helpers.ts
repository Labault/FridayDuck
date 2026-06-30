import { type Page, expect } from '@playwright/test';

// Sélecteurs RÉELS du DOM de la page du canard (data-*-target des contrôleurs Stimulus).
export const sel = {
  coffeeButton: '[data-duck-target="coffeeButton"]',
  energyValue: '[data-duck-target="energyValue"]',
  energyState: '[data-duck-target="energyState"]',
  remaining: '[data-duck-target="remainingValue"]',
  message: '[data-duck-target="message"]',
  reducedFlag: '[data-duck-target="reducedFlag"]',
  accessoryLabel: '[data-duck-target="accessoryLabel"]',
  votePanel: '.vote-panel',
  voteOption: (code: string) => `.vote-option[data-code="${code}"]`,
  voteCount: (code: string) => `.vote-option[data-code="${code}"] [data-role="count"]`,
  proclamation: '[data-accessory-vote-target="proclamation"]',
  advicePanel: '.advice-card',
  adviceButton: (code: string) => `.advice-reaction[data-reaction="${code}"]`,
  adviceCount: (code: string) => `.advice-reaction[data-reaction="${code}"] [data-role="count"]`,
};

/** Ouvre la page du canard (route `duck_demo`, servie à la racine) et attend que le contrôleur ait amorcé le readout. */
export async function openDuck(page: Page): Promise<void> {
  await page.goto('/');
  await expect(page.locator(sel.energyValue)).toBeVisible();
}

export async function readEnergy(page: Page): Promise<number> {
  return Number(await page.locator(sel.energyValue).textContent());
}

export async function readRemaining(page: Page): Promise<number> {
  return Number(await page.locator(sel.remaining).textContent());
}

export async function readCount(page: Page, selector: string): Promise<number> {
  return Number(await page.locator(selector).textContent());
}

/** Offre un café et attend l'effet serveur (énergie + cafés restants à jour). */
export async function serveCoffee(page: Page): Promise<void> {
  const before = await readEnergy(page);
  await page.locator(sel.coffeeButton).click();
  // Autorité serveur : l'énergie officielle remonte via la barrière de version.
  await expect(page.locator(sel.energyValue)).not.toHaveText(String(before), { timeout: 10_000 });
}
