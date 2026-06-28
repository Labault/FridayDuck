import { defineConfig } from 'vitest/config';

// Config de test ISOLÉE du plugin Symfony (vite.config.ts) : on ne veut pas que
// la génération d'entrypoints/Stimulus interfère avec les tests unitaires.
export default defineConfig({
  test: {
    include: ['tests/js/**/*.test.ts'],
  },
});
