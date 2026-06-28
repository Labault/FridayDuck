import type { IStudio } from '@theatre/studio';

let studio: IStudio | null = null;

/**
 * Charge Theatre.js Studio UNIQUEMENT en développement (§15.4, §17.1).
 *
 * Le garde `import.meta.env.DEV` + l'import DYNAMIQUE font que le bundler élimine
 * complètement @theatre/studio d'un build « prod » : le Studio n'y figure jamais
 * (vérifiable via `npm run build:prod`). En prod, seul @theatre/core est chargé.
 */
export async function initStudio(): Promise<void> {
  if (!import.meta.env.DEV) {
    return;
  }
  const mod = await import('@theatre/studio');
  studio = mod.default;
  studio.initialize();
}

export function getStudio(): IStudio | null {
  return studio;
}
