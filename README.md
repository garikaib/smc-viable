# SMC Viable Quiz Plugin

A React-powered Quiz management system for WordPress.

## Features

- **Custom Post Type**: `smc_quiz` for managing quizzes.
- **Admin Interface**: React-based dashboard for creating and editing quizzes.
- **Frontend Block**: Gutenberg block to display quizzes.
- **Scoring**: Dropdown questions support scored options.

## Development

### Requirements
- PHP 8.2+
- WordPress 6.4+
- Node.js & NPM

### Setup

1. Install dependencies:
   ```bash
   npm install
   composer install
   ```

2. Build assets:
   ```bash
   npm run build
   ```

3. Start development server (watch mode):
   ```bash
   npm run start
   ```

## Structure

- `src/` - React source files.
- `build/` - Compiled assets.
- `includes/` - PHP classes and Logic.
- `smc-viable.php` - Main plugin file.
