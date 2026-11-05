import { test, expect } from '@playwright/test';

import { testInstall } from './commands/install.js';

test('test', async ({ page }) => {
  await testInstall(page);
});