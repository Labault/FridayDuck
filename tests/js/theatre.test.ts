// @vitest-environment happy-dom
import { describe, expect, it } from 'vitest';
import { createDuckTheatre } from '../../assets/duck/theatre/project.ts';

/**
 * « Testé au minimum par initialisation » (§17.3) : le projet Theatre.js et ses
 * feuilles se construisent et l'état exporté (duck-friday.state.json) se charge
 * sans erreur. Studio non impliqué ici (core seul).
 */
describe('Theatre.js project boots with the exported state (§17.3)', () => {
  it('constructs the sheets and resolves project.ready', async () => {
    const { project, idle, coffee } = createDuckTheatre();
    await project.ready;

    expect(idle.value.breathSpeed).toBeGreaterThan(0);
    expect(coffee.value.durationMs).toBeGreaterThan(0);
  });
});
