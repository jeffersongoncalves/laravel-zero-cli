<?php

namespace App\Support;

/**
 * The files every jeffersongoncalves `*-cli` project carries.
 *
 * These were audited across the 16 existing CLIs: `bootstrap/app.php` and
 * `tests/TestCase.php` were byte-identical in all 16, while `release.yml`,
 * `SelfUpdateCommand`, `AppServiceProvider` and `config/app.php` had 16
 * "variants" purely because each embeds the binary name — they are one
 * template with one substitution. `box.json` (3 variants), `cliff.toml` (4),
 * `phpunit.xml.dist` (5) and `phpstan.neon.dist` (7 variants across only 8
 * repos) had drifted apart for no reason; the canonical version lives here.
 *
 * Every template is a nowdoc so GitHub Actions' `${{ }}` expressions survive
 * untouched, with substitution done through {@see render()}.
 */
class Templates
{
    /**
     * @param  array<string, string>  $replacements
     */
    public static function render(string $template, array $replacements): string
    {
        return strtr($template, $replacements);
    }

    public static function binStub(): string
    {
        return <<<'PHP'
        #!/usr/bin/env php
        <?php

        define('LARAVEL_START', microtime(true));

        $autoloader = require file_exists(__DIR__.'/vendor/autoload.php')
            ? __DIR__.'/vendor/autoload.php'
            : __DIR__.'/../../autoload.php';

        $app = require_once __DIR__.'/bootstrap/app.php';

        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

        $status = $kernel->handle(
            $input = new Symfony\Component\Console\Input\ArgvInput,
            new Symfony\Component\Console\Output\ConsoleOutput
        );

        $kernel->terminate($input, $status);

        exit($status);

        PHP;
    }

    public static function composerJson(): string
    {
        return <<<'JSON'
        {
            "name": "{{vendor}}/{{repo}}",
            "description": "{{description}}",
            "keywords": ["cli", "laravel-zero", "console"],
            "homepage": "https://github.com/{{vendor}}/{{repo}}",
            "type": "project",
            "license": "MIT",
            "authors": [
                {
                    "name": "{{author}}",
                    "email": "{{email}}",
                    "role": "Developer"
                }
            ],
            "require": {
                "php": "^8.3"
            },
            "require-dev": {
                "jeffersongoncalves/laravel-zero-self-update": "^1.1",
                "laravel-zero/framework": "^13.0",
                "laravel/pint": "^1.25",
                "mockery/mockery": "^1.6",
                "pestphp/pest": "^3.8|^4.7",
                "phpstan/phpstan": "^2.1"
            },
            "autoload": {
                "psr-4": {
                    "App\\": "app/"
                }
            },
            "autoload-dev": {
                "psr-4": {
                    "Tests\\": "tests/"
                }
            },
            "scripts": {
                "build": "php {{binary}} app:build {{binary}} --build-version=\"$(git describe --tags --abbrev=0 2>/dev/null || echo unreleased)\"",
                "lint": "./vendor/bin/pint",
                "test:lint": "./vendor/bin/pint --test",
                "test:unit": "./vendor/bin/pest --parallel",
                "test:types": "./vendor/bin/phpstan analyse --memory-limit=256M",
                "phpstan": "./vendor/bin/phpstan analyse --memory-limit=256M",
                "phpstan-baseline": "./vendor/bin/phpstan analyse --generate-baseline --memory-limit=256M",
                "test": [
                    "@test:unit",
                    "@test:lint"
                ]
            },
            "config": {
                "preferred-install": "dist",
                "sort-packages": true,
                "optimize-autoloader": true,
                "allow-plugins": {
                    "pestphp/pest-plugin": true
                }
            },
            "minimum-stability": "stable",
            "prefer-stable": true,
            "bin": ["builds/{{binary}}"]
        }

        JSON;
    }

    public static function bootstrapApp(): string
    {
        return <<<'PHP'
        <?php

        use LaravelZero\Framework\Application;

        return Application::configure(basePath: dirname(__DIR__))->create();

        PHP;
    }

    public static function bootstrapProviders(): string
    {
        return <<<'PHP'
        <?php

        use App\Providers\AppServiceProvider;

        return [
            AppServiceProvider::class,
        ];

        PHP;
    }

    public static function configApp(): string
    {
        return <<<'PHP'
        <?php

        use App\Providers\AppServiceProvider;

        return [

            'name' => '{{appName}}',

            'version' => app('git.version'),

            'env' => 'development',

            'providers' => [
                AppServiceProvider::class,
            ],

        ];

        PHP;
    }

    public static function configCommands(): string
    {
        return <<<'PHP'
        <?php

        use Illuminate\Console\Scheduling\ScheduleFinishCommand;
        use Illuminate\Console\Scheduling\ScheduleListCommand;
        use Illuminate\Console\Scheduling\ScheduleRunCommand;
        use Illuminate\Foundation\Console\VendorPublishCommand;
        use LaravelZero\Framework\Commands\StubPublishCommand;
        use NunoMaduro\LaravelConsoleSummary\SummaryCommand;
        use Symfony\Component\Console\Command\DumpCompletionCommand;
        use Symfony\Component\Console\Command\HelpCommand;

        return [

            'default' => SummaryCommand::class,

            'paths' => [app_path('Commands')],

            'add' => [],

            'hidden' => [
                VendorPublishCommand::class,
                StubPublishCommand::class,
                DumpCompletionCommand::class,
                HelpCommand::class,
                ScheduleRunCommand::class,
                ScheduleListCommand::class,
                ScheduleFinishCommand::class,
            ],

            'remove' => [],

        ];

        PHP;
    }

    public static function appServiceProvider(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Providers;

        use Illuminate\Support\ServiceProvider;
        use JeffersonGoncalves\LaravelZero\SelfUpdate\PharUpdater;

        class AppServiceProvider extends ServiceProvider
        {
            public function boot(): void
            {
                //
            }

            public function register(): void
            {
                $this->app->singleton(PharUpdater::class, fn () => new PharUpdater(
                    githubRepo: '{{vendor}}/{{repo}}',
                    assetName: '{{binary}}.phar',
                    tempPrefix: '{{tempPrefix}}',
                    currentVersion: (string) config('app.version', 'unreleased'),
                ));
            }
        }

        PHP;
    }

    public static function selfUpdateCommand(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Commands;

        use JeffersonGoncalves\LaravelZero\SelfUpdate\PharUpdater;
        use JeffersonGoncalves\LaravelZero\SelfUpdate\SelfUpdateCommand as BaseSelfUpdateCommand;

        class SelfUpdateCommand extends BaseSelfUpdateCommand
        {
            protected $description = 'Update the {{binary}} CLI to the latest version';

            protected function githubRepo(): string
            {
                return '{{vendor}}/{{repo}}';
            }

            protected function assetName(): string
            {
                return '{{binary}}.phar';
            }

            protected function tempPrefix(): string
            {
                return '{{tempPrefix}}';
            }

            protected function currentVersion(): string
            {
                return (string) config('app.version', 'unreleased');
            }

            protected function makeUpdater(): PharUpdater
            {
                return $this->getLaravel()->make(PharUpdater::class);
            }
        }

        PHP;
    }

    public static function exampleCommand(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Commands;

        use LaravelZero\Framework\Commands\Command;

        class ExampleCommand extends Command
        {
            /**
             * Keep commands non-interactive: every input an argument or flag, so a
             * script or an AI agent can drive them without a TTY. No ->ask()/->confirm().
             *
             * @var string
             */
            protected $signature = 'example {name : who to greet}';

            protected $description = 'Replace me with the first real command';

            public function handle(): int
            {
                $this->components->info('Hello '.$this->argument('name'));

                return self::SUCCESS;
            }
        }

        PHP;
    }

    public static function boxJson(): string
    {
        return <<<'JSON'
        {
            "chmod": "0755",
            "directories": [
                "app",
                "bootstrap",
                "config",
                "vendor"
            ],
            "files": [
                "composer.json"
            ],
            "exclude-composer-files": false,
            "exclude-dev-files": false,
            "compression": "GZ",
            "compactors": [
                "KevinGH\\Box\\Compactor\\Php",
                "KevinGH\\Box\\Compactor\\Json"
            ]
        }

        JSON;
    }

    public static function phpstanNeon(): string
    {
        return <<<'NEON'
        parameters:
            level: 5
            paths:
                - app
                - tests
            ignoreErrors:
                -
                    identifier: method.notFound

        NEON;
    }

    public static function phpunitXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                 xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
                 bootstrap="vendor/autoload.php"
                 colors="true"
        >
            <testsuites>
                <testsuite name="Feature">
                    <directory>./tests/Feature</directory>
                </testsuite>
                <testsuite name="Unit">
                    <directory>./tests/Unit</directory>
                </testsuite>
            </testsuites>
            <source>
                <include>
                    <directory>./app</directory>
                </include>
            </source>
        </phpunit>

        XML;
    }

    public static function testCase(): string
    {
        return <<<'PHP'
        <?php

        namespace Tests;

        use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

        abstract class TestCase extends BaseTestCase {}

        PHP;
    }

    public static function pestPhp(): string
    {
        return <<<'PHP'
        <?php

        use Tests\TestCase;

        uses(TestCase::class)->in('Feature');

        PHP;
    }

    public static function exampleTest(): string
    {
        return <<<'PHP'
        <?php

        it('greets the given name', function () {
            $this->artisan('example', ['name' => 'world'])->assertExitCode(0);
        });

        PHP;
    }

    /**
     * The release flow every CLI here uses. The invariant it protects: the PHAR
     * is built and committed to main BEFORE the tag exists, and the tag is
     * pinned to that commit, so a published tag never moves.
     */
    public static function workflowRelease(): string
    {
        return <<<'YAML'
        name: Release

        # Single linear release flow. The KEY invariant: the PHAR binary (composer `bin`,
        # builds/{{binary}}) is built and committed to main BEFORE the tag is created, and the
        # tag is pinned to that exact commit. Nothing rebuilds onto a post-tag commit, so the
        # tag never moves and Composer's stable-version immutability is never violated.

        on:
          workflow_dispatch:
            inputs:
              version:
                description: 'Explicit version without the leading "v" (e.g. 1.2.4). Leave blank to auto-bump the patch.'
                required: false

        permissions: write-all

        concurrency:
          group: release
          cancel-in-progress: false

        jobs:
          release:
            runs-on: ubuntu-latest

            steps:
              - name: Checkout main
                uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7
                with:
                  ref: main
                  fetch-depth: 0

              - name: Resolve version
                id: v
                run: |
                  if [ -n "${{ inputs.version }}" ]; then
                    NEXT="${{ inputs.version }}"
                    NEXT="${NEXT#v}"
                  else
                    LATEST=$(git tag -l \
                      | sed 's/^v//' \
                      | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$' \
                      | sort -V \
                      | tail -n1)
                    [ -z "$LATEST" ] && LATEST="0.0.0"
                    IFS='.' read -r MA MI PA <<< "$LATEST"
                    NEXT="${MA}.${MI}.$((PA + 1))"
                  fi
                  while gh release view "v$NEXT" &>/dev/null; do
                    IFS='.' read -r MA MI PA <<< "$NEXT"
                    NEXT="${MA}.${MI}.$((PA + 1))"
                  done
                  echo "version=$NEXT" >> "$GITHUB_OUTPUT"
                  echo "tag=v$NEXT" >> "$GITHUB_OUTPUT"
                  echo "Releasing v$NEXT"
                env:
                  GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

              - name: Setup PHP
                uses: shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240 # v2
                with:
                  php-version: 8.4
                  extensions: dom, curl, libxml, mbstring, zip
                  ini-values: error_reporting=E_ALL, phar.readonly=Off
                  tools: composer:v2
                  coverage: none

              - name: Install the dependencies
                run: composer install --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-suggest --no-scripts --ansi -v --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix --ignore-platform-req=ext-sockets

              - name: Stamp embedded version
                run: echo "${{ steps.v.outputs.version }}" > version.txt

              - name: Generate CHANGELOG.md
                uses: orhun/git-cliff-action@3d96a18cc4ec17e9dc69ddcc424ccafaf1f78ce2 # v4.9.0
                with:
                  config: cliff.toml
                  args: --tag ${{ steps.v.outputs.tag }} -o CHANGELOG.md

              - name: Generate release notes
                uses: orhun/git-cliff-action@3d96a18cc4ec17e9dc69ddcc424ccafaf1f78ce2 # v4.9.0
                with:
                  config: cliff.toml
                  args: --tag ${{ steps.v.outputs.tag }} --unreleased --strip all -o RELEASE_NOTES.md

              - name: Build the PHAR with the release version
                run: php {{binary}} app:build {{binary}} --build-version=${{ steps.v.outputs.version }}

              - name: Commit release artifacts to main
                id: commit
                uses: stefanzweifel/git-auto-commit-action@4a55954c782fc1ea30b9056cd3e7a2b40ca8887d # v7
                with:
                  branch: main
                  commit_message: "Release ${{ steps.v.outputs.tag }}"
                  file_pattern: "builds/{{binary}} version.txt CHANGELOG.md"

              - name: Resolve release commit
                id: sha
                run: echo "sha=$(git rev-parse HEAD)" >> "$GITHUB_OUTPUT"

              - name: Create draft tag and release at the release commit
                run: |
                  gh release create ${{ steps.v.outputs.tag }} \
                    --title "${{ steps.v.outputs.tag }}" \
                    --notes-file RELEASE_NOTES.md \
                    --target ${{ steps.sha.outputs.sha }} \
                    --draft \
                    --latest
                env:
                  GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

              - name: Upload the PHAR asset
                run: |
                  cp builds/{{binary}} {{binary}}.phar
                  gh release upload ${{ steps.v.outputs.tag }} {{binary}}.phar --clobber
                env:
                  GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

              - name: Publish the release
                run: gh release edit ${{ steps.v.outputs.tag }} --draft=false
                env:
                  GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

        YAML;
    }

    public static function workflowTests(): string
    {
        return <<<'YAML'
        name: tests

        on:
          push:
            branches: [main]
          pull_request:
            branches: [main]

        permissions: write-all

        jobs:
          test:
            runs-on: ${{ matrix.os }}

            strategy:
              fail-fast: false
              matrix:
                os: [ubuntu-latest]
                php: [8.4]
                dependency-version: [prefer-stable]

            name: P${{ matrix.php }} - ${{ matrix.dependency-version }} - ${{ matrix.os }}

            steps:
              - name: Checkout code
                uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7

              - name: Cache dependencies
                uses: actions/cache@55cc8345863c7cc4c66a329aec7e433d2d1c52a9 # v6
                with:
                  path: ~/.composer/cache/files
                  key: dependencies-php-${{ matrix.php }}-composer-${{ hashFiles('composer.json') }}

              - name: Setup PHP
                uses: shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240 # v2
                with:
                  php-version: ${{ matrix.php }}
                  coverage: none

              - name: Install dependencies
                run: composer update --${{ matrix.dependency-version }} --prefer-dist --ansi --no-interaction --no-scripts

              - name: Execute tests
                run: composer test

        YAML;
    }

    public static function workflowPhpstan(): string
    {
        return <<<'YAML'
        name: PHPStan

        on:
          push:
            paths:
              - '**.php'
              - 'phpstan.neon.dist'
          workflow_dispatch:

        jobs:
          phpstan:
            name: phpstan
            runs-on: ubuntu-latest
            steps:
              - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7

              - name: Setup PHP
                uses: shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240 # v2
                with:
                  php-version: '8.4'
                  coverage: none

              - name: Install composer dependencies
                uses: ramsey/composer-install@65e4f84970763564f46a70b8a54b90d033b3bdda # v4

              - name: Run PHPStan
                run: ./vendor/bin/phpstan --error-format=github

        YAML;
    }

    public static function workflowPint(): string
    {
        return <<<'YAML'
        name: Fix PHP code style issues

        on:
          push:
            paths:
              - '**.php'

        permissions: write-all

        jobs:
          php-code-styling:
            runs-on: ubuntu-latest

            steps:
              - name: Checkout code
                uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7
                with:
                  ref: ${{ github.head_ref }}

              - name: Fix PHP code style issues
                uses: aglipanci/laravel-pint-action@36de00d5f5a8a4e12d443e01671daa12a18f4c79 # 2.6

              - name: Commit changes
                uses: stefanzweifel/git-auto-commit-action@4a55954c782fc1ea30b9056cd3e7a2b40ca8887d # v7
                with:
                  commit_message: Fix styling

        YAML;
    }

    /**
     * Weekly, grouped, and never auto-merged: Dependabot PRs are reviewed by a
     * human here. See the axios 1.14.1 supply-chain incident.
     */
    public static function dependabot(): string
    {
        return <<<'YAML'
        # Please see the documentation for all configuration options:
        # https://help.github.com/github/administering-a-repository/configuration-options-for-dependency-updates

        version: 2
        updates:

          - package-ecosystem: "github-actions"
            directory: "/"
            schedule:
              interval: "weekly"
            groups:
              all-actions:
                patterns: ["*"]
            labels:
              - "dependencies"

          - package-ecosystem: "composer"
            directory: "/"
            schedule:
              interval: "weekly"
            groups:
              all-composer:
                patterns: ["*"]
            labels:
              - "dependencies"

        YAML;
    }

    public static function cliffToml(): string
    {
        return <<<'TOML'
        [changelog]
        header = """
        # Changelog

        All notable changes to this project will be documented in this file.

        """
        body = """
        {% if version %}\
            ## [{{ version | trim_start_matches(pat="v") }}] - {{ timestamp | date(format="%Y-%m-%d") }}
        {% else %}\
            ## [Unreleased]
        {% endif %}\
        {% for group, commits in commits | group_by(attribute="group") %}
            ### {{ group | striptags | trim | upper_first }}
            {% for commit in commits %}
                - {% if commit.scope %}**{{ commit.scope }}:** {% endif %}{{ commit.message | upper_first }}\
            {% endfor %}
        {% endfor %}\n
        """
        footer = ""
        trim = true

        [git]
        conventional_commits = true
        filter_unconventional = false
        split_commits = false
        protect_breaking_commits = false
        filter_commits = false
        tag_pattern = "v[0-9]*"
        topo_order = false
        sort_commits = "oldest"
        commit_parsers = [
            { message = "^Release v", skip = true },
            { message = "^Fix styling$", skip = true },
            { message = "^Generate build$", skip = true },
            { message = "^Update CHANGELOG$", skip = true },
            { message = "^Merge pull request", skip = true },
            { message = "^Merge branch", skip = true },
            { message = "^Bump [\\w./@-]+ (from|in) ", skip = true },
            { message = "^feat", group = "Features" },
            { message = "^fix", group = "Bug Fixes" },
            { message = "^perf", group = "Performance" },
            { message = "^refactor", group = "Refactor" },
            { message = "^doc", group = "Documentation" },
            { message = "^style", group = "Styling" },
            { message = "^test", group = "Testing" },
            { message = "^ci", group = "CI/CD" },
            { message = "^build", group = "Build" },
            { message = "^revert", group = "Revert" },
            { message = "^chore\\(deps\\)", group = "Dependencies" },
            { message = "^chore", group = "Miscellaneous Tasks" },
            { message = ".*", group = "Other" },
        ]

        TOML;
    }

    public static function security(): string
    {
        return <<<'MD'
        # Security Policy

        If you discover any security related issues, please email the maintainer
        instead of using the issue tracker. All security vulnerabilities will be
        promptly addressed.

        MD;
    }

    public static function readme(): string
    {
        return <<<'MD'
        # {{appName}}

        {{description}}

        ## Installation

        ```bash
        composer global require {{vendor}}/{{repo}}
        ```

        Or grab the standalone PHAR from the [latest release](https://github.com/{{vendor}}/{{repo}}/releases/latest).

        ## Usage

        ```bash
        {{binary}} example world
        ```

        Every command is non-interactive: all input arrives as arguments and flags,
        so a script or an AI agent can drive it without a TTY.

        Update to the latest release (PHAR installs only — Composer installs update
        via `composer global update`):

        ```bash
        {{binary}} self-update
        {{binary}} self-update --check
        ```

        ## Releasing

        Run the **Release** workflow from the Actions tab. It stamps `version.txt`,
        regenerates `CHANGELOG.md` with git-cliff, builds the PHAR, commits it to
        `main`, then creates the tag and release pinned to that exact commit — so a
        published tag never moves.

        ```bash
        gh workflow run release.yml --ref main
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

        MD;
    }
}
