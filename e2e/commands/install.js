import { expect } from '@playwright/test';

async function testInstall(page) {

    await page.goto('http://127.0.0.1:8080/');
    await page.getByRole('textbox', { name: 'IP Publique*' }).fill('127.0.0.1');
    await page.getByRole('textbox', { name: 'Mot de passe MySQL* Mot de' }).fill('monsupermdp');
    await page.getByRole('textbox', { name: 'Mot de passe SSL_CA_KEY* Mot' }).fill('monsupermdp');
    await page.getByRole('textbox', { name: 'Email de contact* Email de' }).fill('a@a.a');
    await page.getByRole('button', { name: 'Lancer l\'installation' }).click();

    await expect(page.getByRole('link', { name: 'Continuer →' })).toBeVisible();
}

export { testInstall }