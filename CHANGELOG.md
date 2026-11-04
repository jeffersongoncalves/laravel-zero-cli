# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-11-04

### Bug Fixes

- Pin PHP floor to 8.3 for Laravel Zero 13

### Build

- **deps:** Bump orhun/git-cliff-action in the all-actions group
- **deps:** Bump shivammathur/setup-php (#8)
- **deps:** Bump stefanzweifel/git-auto-commit-action (#11)
- **deps-dev:** Bump the composer-deps group with 3 updates

### CI/CD

- Pass release version input through env
- Standardize dependabot config
- Standardize tests workflow
- Remove run-tests.yml (replaced by tests.yml)
- Standardize tests workflow
- Auto-merge dependabot github-actions minor/patch

### Other

- Auto-assign issues and pull requests

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>

## [1.0.5] - 2026-09-10

### Bug Fixes

- Track composer.lock in scaffolded projects

## [1.0.4] - 2026-09-10

### Bug Fixes

- First auto-versioned release should be 1.0.0, not 0.0.1

## [1.0.3] - 2026-09-10

### Bug Fixes

- Sync git-cliff-action pin in release template with own workflow
- **deps:** Update laravel-zero-package-scaffold to 1.0.1

## [1.0.2] - 2026-09-08

### Bug Fixes

- Prevent terminal text overflow in portfolio banner
- **ci:** Publish release as draft until PHAR asset is attached

### Build

- **deps:** Bump orhun/git-cliff-action in the all-actions group

### Documentation

- Add portfolio banner

## [1.0.1] - 2026-09-08

### Bug Fixes

- Move the package-scaffold dependency to require-dev

## [0.0.1] - 2026-09-08

### Features

- Scaffold Laravel Zero CLI projects with the release pipeline wired


