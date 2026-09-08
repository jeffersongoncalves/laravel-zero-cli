# Laravel Zero CLI

Scaffold a new Laravel Zero CLI project with the whole release pipeline already
wired: PHAR build, self-update, git-cliff changelog, CI, and the conventions
shared by the 16 `*-cli` projects in this account.

Built with Laravel Zero. Designed to be driven non-interactively (by an AI agent
or a script) — every input is an argument or a flag, so there are no prompts.

## Installation

```bash
composer global require jeffersongoncalves/laravel-zero-cli
```

Or grab the standalone PHAR from the [latest release](https://github.com/jeffersongoncalves/laravel-zero-cli/releases/latest).

## Usage

```bash
laravel-zero-cli create jeffersongoncalves/deps-cli "Audit composer dependencies"
```

That writes a project whose `composer install && composer test` passes out of the
box, with a `deps` executable, and prints the remaining steps (create the repo,
run the release workflow, submit to Packagist).

### Arguments

| Argument | Required | Description |
|----------|----------|-------------|
| `vendor-repo` | yes | `vendor/repo` slug, e.g. `jeffersongoncalves/deps-cli` |
| `description` | no | Short one-line description, used in `composer.json` and the generated `README.md` |

### Options

| Option | Description |
|--------|-------------|
| `--binary=NAME` | Name of the executable. Default: the repo without its `-cli` suffix (`deps-cli` → `deps`), since the suffix names the project, not the command people type |
| `--app-name="Name"` | Display name for `config/app.php`. Default: derived from the binary (`deps` → `Deps CLI`) |
| `--path=DIR` | Target directory. Default: `./<repo>` under the current directory |
| `--author="Name"` | Author for `composer.json` and `LICENSE`. Default: `git config user.name` |
| `--email=EMAIL` | Author email. Default: `git config user.email` |
| `--no-git` | Skip `git init` and the first commit |
| `--dry-run` | Print the planned file list without writing anything |

## What it generates

Laravel Zero skeleton, parameterized by the binary name:

`<binary>` (executable stub) · `bootstrap/app.php` · `bootstrap/providers.php` ·
`config/app.php` · `config/commands.php` · `app/Providers/AppServiceProvider.php` ·
`app/Commands/SelfUpdateCommand.php` · `app/Commands/ExampleCommand.php`

Build and release furniture:

`box.json` · `cliff.toml` · `version.txt` · `.github/workflows/release.yml`

Quality gates and meta:

`phpstan.neon.dist` · `phpunit.xml.dist` · `tests/` · `run-tests.yml` ·
`phpstan.yml` · `fix-php-code-style-issues.yml` · `dependabot.yml` ·
`SECURITY.md` · `LICENSE` · `CHANGELOG.md` · `README.md` ·
`.editorconfig` · `.gitattributes` · `.gitignore`

The last group comes from
[laravel-zero-package-scaffold](https://github.com/jeffersongoncalves/laravel-zero-package-scaffold),
shared with `laravel-package-cli` and `filament-plugin-cli`.

## Why these files

Audited across the 16 existing `*-cli` repos before writing the templates:

| Observation | What it means |
|-------------|---------------|
| `bootstrap/app.php` and `tests/TestCase.php` — identical in all 16 | Pure boilerplate, nothing to decide |
| `release.yml`, `SelfUpdateCommand`, `AppServiceProvider`, `config/app.php` — 16 "variants" | One template, one substitution: the binary name |
| `phpstan.neon.dist` — 7 different contents across the only 8 repos that had one | Drift, plus half the projects had no static analysis at all |
| `box.json` (3), `cliff.toml` (4), `phpunit.xml.dist` (5) | Drift with no cause |
| `jeffersongoncalves/laravel-zero-self-update` — required by all 16 | Part of the standard, wired in from the start |

Every generated project therefore ships with static analysis, tests, and the
same release flow — the things that were previously missing or subtly different
from repo to repo.

### The release invariant

The generated `release.yml` builds the PHAR and commits it to `main` **before**
creating the tag, then pins the tag to that exact commit. Nothing rebuilds onto
a post-tag commit, so a published tag never moves and Composer's stable-version
immutability holds.

```bash
gh workflow run release.yml --ref main
```

## Updating

```bash
laravel-zero-cli self-update
laravel-zero-cli self-update --check
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security

If you discover any security related issues, please see [SECURITY](.github/SECURITY.md).

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
