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

const VIEWPORTS = [
    { name: 'bureau', width: 1280, height: 900 },
    { name: 'mobile', width: 390, height: 844 },
];

const SCREENS = [
    { name: 'accueil', path: '/' },
    { name: 'bibliotheque', path: '/bibliotheque' },
    { name: 'recherche', path: '/recherche?query=sommeil' },
    { name: 'taches', path: '/taches' },
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
