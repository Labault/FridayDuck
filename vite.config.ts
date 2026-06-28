import { defineConfig } from 'vite';
import symfony from 'vite-plugin-symfony';

// Pipeline asset Symfony (pentatrion/vite-bundle). `stimulus: true` enregistre
// automatiquement les contrôleurs de assets/controllers/*_controller.ts et
// ceux déclarés dans assets/controllers.json.
//
// @theatre/studio n'est chargé qu'en dev (import dynamique gardé par
// import.meta.env.DEV dans assets/duck/theatre/studio.ts) → tree-shaké du build
// de production. Vérifié par `npm run build` (aucun symbole du Studio).
export default defineConfig({
  plugins: [symfony({ stimulus: true })],
  build: {
    rollupOptions: {
      input: {
        app: './assets/app.ts',
      },
    },
  },
});
