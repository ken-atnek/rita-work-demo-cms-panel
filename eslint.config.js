// eslint.config.js
import globals from 'globals';
import js from '@eslint/js';

export default [
  {
    files: ['assets/js/**/*.js'],
    languageOptions: {
      globals: globals.browser,
    },
    rules: {
      ...js.configs.recommended.rules,
    },
  },
];
