const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: __dirname,
  timeout: 60000,
  retries: 0,
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8080',
    headless: true
  }
});
