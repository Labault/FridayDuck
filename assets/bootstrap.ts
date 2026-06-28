import { registerControllers, startStimulusApp } from 'vite-plugin-symfony/stimulus/helpers';

// Démarre Stimulus (contrôleurs tiers de controllers.json) puis enregistre les
// contrôleurs LOCAUX de assets/controllers/*_controller.ts via import.meta.glob
// (eager + query ?stimulus, comme attendu par vite-plugin-symfony).
const app = startStimulusApp();

registerControllers(
  app,
  import.meta.glob<StimulusControllerInfosImport>('./controllers/*_controller.{js,ts}', {
    query: '?stimulus',
    eager: true,
  }),
);

export { app };
