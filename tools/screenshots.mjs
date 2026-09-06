/*
 * Captures des écrans, pour juger le rendu au lieu de le déduire.
 *
 * Utilise `playwright-core` et le Chromium fourni par nix : les binaires que
 * Playwright télécharge lui-même sont liés dynamiquement et ne démarrent pas
 * sur NixOS.
 *
 *   devenv shell -- shots
 */

import { chromium } from 'playwright-core';
import { execSync } from 'node:child_process';
import { mkdirSync, rmSync } from 'node:fs';

const BASE = process.env.FOCUSYN_URL ?? 'http://127.0.0.1:8000';
const OUT = 'var/screenshots';
const ACCOUNT = { email: 'demo@focusyn.fr', password: 'une phrase de passe tenable' };

/* Le thème par défaut suit l'appareil : une passe en `prefers-color-scheme:
   dark` suffit donc à voir le sombre, sans toucher au réglage du compte. */
const VIEWPORTS = [
    { name: 'bureau', width: 1280, height: 900 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'sombre', width: 1280, height: 900, colorScheme: 'dark' },
];

const SCREENS = [
    { name: 'accueil', path: '/' },
    { name: 'bibliotheque', path: '/bibliotheque' },
    { name: 'recherche', path: '/recherche?query=sommeil' },
    { name: 'taches', path: '/taches' },
    { name: 'boite', path: '/boite' },
    { name: 'reglages', path: '/reglages' },
    { name: 'equipe', path: '/equipe' },
    { name: 'abonnement', path: '/abonnement' },
    { name: 'obsession', path: '/obsessions/sommeil' },
    { name: 'design-system', path: '/_design-system' },
];

const executablePath = execSync('command -v chromium', { encoding: 'utf8' }).trim();

rmSync(OUT, { recursive: true, force: true });
mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch({ executablePath, args: ['--font-render-hinting=none'] });

for (const viewport of VIEWPORTS) {
    const context = await browser.newContext({
        viewport: { width: viewport.width, height: viewport.height },
        deviceScaleFactor: 2,
        locale: 'fr-FR',
        colorScheme: viewport.colorScheme ?? 'light',
    });
    const page = await context.newPage();

    // Connexion par le vrai formulaire : c'est aussi une vérification.
    await page.goto(`${BASE}/connexion`, { waitUntil: 'networkidle' });
    await page.fill('input[name="login[email]"]', ACCOUNT.email);
    await page.fill('input[name="login[password]"]', ACCOUNT.password);
    await Promise.all([page.waitForURL(`${BASE}/`), page.click('button[type="submit"]')]);

    for (const screen of SCREENS) {
        await page.goto(`${BASE}${screen.path}`, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({
            path: `${OUT}/${screen.name}-${viewport.name}.png`,
            fullPage: true,
        });
        process.stdout.write(`  ${screen.name}-${viewport.name}.png\n`);
    }

    // Une note ouverte, pour voir l'éditeur.
    await page.goto(`${BASE}/bibliotheque`, { waitUntil: 'networkidle' });
    const firstNote = await page.getAttribute('.fx-note-row', 'href');
    if (firstNote) {
        await page.goto(`${BASE}${firstNote}`, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        await page.waitForSelector('.cm-content', { timeout: 5000 }).catch(() => {});
        await page.screenshot({ path: `${OUT}/note-${viewport.name}.png`, fullPage: true });
        process.stdout.write(`  note-${viewport.name}.png\n`);
    }

    // La revue du soir : un dialogue, donc caché au chargement. Sans ce clic,
    // aucune capture ne le montrerait jamais.
    await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
    await page.click('[data-fx-dialog="review"]');
    await page.waitForSelector('.fx-review__panel', { state: 'visible', timeout: 3000 }).catch(() => {});
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: `${OUT}/revue-${viewport.name}.png`, fullPage: true });
    process.stdout.write(`  revue-${viewport.name}.png\n`);

    // Une routine ouverte : c'est là que se règlent les calendriers.
    await page.goto(`${BASE}/taches`, { waitUntil: 'networkidle' });
    // Les deux premières : l'une quotidienne, l'autre cadencée. Les pastilles
    // de jours ne se voient que sur la seconde.
    const routineLinks = await page.locator('a[href^="/routines/"]').evaluateAll(
        (nodes) => nodes.map((node) => node.getAttribute('href')),
    );
    for (const [index, href] of routineLinks.slice(0, 2).entries()) {
        await page.goto(`${BASE}${href}`, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        const name = index === 0 ? 'routine' : 'routine-cadencee';
        await page.screenshot({ path: `${OUT}/${name}-${viewport.name}.png`, fullPage: true });
        process.stdout.write(`  ${name}-${viewport.name}.png\n`);
    }

    // Une liste ouverte : c'est là que se voient les pastilles de rappel.
    await page.goto(`${BASE}/taches`, { waitUntil: 'networkidle' });
    const firstList = await page.getAttribute('.fx-board__card', 'href');
    if (firstList) {
        await page.goto(`${BASE}${firstList}`, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({ path: `${OUT}/liste-${viewport.name}.png`, fullPage: true });
        process.stdout.write(`  liste-${viewport.name}.png\n`);

        // Le dialogue d'échéance ne s'ouvre qu'au clic : sans cette capture,
        // rien ne le regarde jamais.
        await page.click('.fx-reminder-chip');
        await page.waitForSelector('.fx-remind:not([hidden])', { timeout: 2000 }).catch(() => {});
        await page.screenshot({ path: `${OUT}/rappel-${viewport.name}.png`, fullPage: false });
        process.stdout.write(`  rappel-${viewport.name}.png\n`);
    }

    await context.close();
}

// Écrans publics, hors session.
const anonymous = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 2, locale: 'fr-FR' });
const page = await anonymous.newPage();
for (const screen of [{ name: 'connexion', path: '/connexion' }, { name: 'inscription', path: '/inscription' }]) {
    await page.goto(`${BASE}${screen.path}`, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: `${OUT}/${screen.name}-bureau.png`, fullPage: true });
    process.stdout.write(`  ${screen.name}-bureau.png\n`);
}
await anonymous.close();

await browser.close();
