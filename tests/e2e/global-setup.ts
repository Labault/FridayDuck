import { execSync } from 'node:child_process';

// Prépare la base e2e ISOLÉE (app_e2e) AVANT les scénarios : migrations (qui
// seedent le catalogue accessoires/conseils). Les éditions/votes sont créés
// paresseusement par l'app ; chaque contexte navigateur = un visiteur frais
// (cookie neuf) → quota/identité isolés sans reset entre scénarios. Les
// assertions d'énergie/compteurs sont RELATIVES (delta), robustes à l'état partagé.
export default function globalSetup(): void {
  if (process.env.E2E_SKIP_MIGRATE === '1') {
    return;
  }
  const exec = (cmd: string): void => {
    execSync(cmd, { stdio: 'inherit' });
  };
  // L'app-friday partage la base e2e avec les autres instances ; on migre une fois.
  exec('docker compose -f compose.e2e.yaml exec -T app-friday php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration');
}
