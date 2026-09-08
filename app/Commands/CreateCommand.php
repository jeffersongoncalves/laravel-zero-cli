<?php

namespace App\Commands;

use App\Support\Templates;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use JeffersonGoncalves\LaravelZero\PackageScaffold\Scaffold;
use LaravelZero\Framework\Commands\Command;

class CreateCommand extends Command
{
    /**
     * Fully non-interactive: every input comes from arguments/options so an
     * AI agent (or any script) can call this without a TTY. Never add ->ask()
     * or ->confirm() here.
     *
     * @var string
     */
    protected $signature = 'create
        {vendor-repo : vendor/repo, e.g. jeffersongoncalves/deps-cli}
        {description? : short description of the CLI}
        {--binary= : name of the executable (default: repo without the -cli suffix)}
        {--app-name= : display name for config/app.php (default: derived from the binary)}
        {--path= : target directory (default: ./<repo> under cwd)}
        {--author= : defaults to `git config user.name`}
        {--email= : defaults to `git config user.email`}
        {--no-git : skip git init/commit}
        {--dry-run : print planned actions, write nothing}';

    protected $description = 'Scaffold a new Laravel Zero CLI project with the release pipeline (PHAR build, self-update, git-cliff changelog, CI) already wired.';

    public function handle(): int
    {
        $vendorRepo = (string) $this->argument('vendor-repo');
        if (! str_contains($vendorRepo, '/')) {
            $this->components->error("Expected vendor/repo, got: {$vendorRepo}");

            return self::FAILURE;
        }
        [$vendor, $repo] = explode('/', $vendorRepo, 2);
        $description = (string) ($this->argument('description') ?? '');

        $dryRun = (bool) $this->option('dry-run');
        $noGit = (bool) $this->option('no-git');

        // Repo `deps-cli` installs a `deps` executable: the suffix names the
        // project, not the command people type.
        $binary = (string) ($this->option('binary') ?: Str::beforeLast($repo, '-cli'));
        if ($binary === '') {
            $this->components->error('Could not derive a binary name; pass --binary=NAME');

            return self::FAILURE;
        }

        $appName = (string) ($this->option('app-name') ?: Scaffold::studly($binary).' CLI');
        $tempPrefix = str_replace('-', '_', strtolower($binary)).'_';

        $author = $this->option('author') ?: trim((string) Process::run('git config --get user.name')->output()) ?: 'Jefferson Gonçalves';
        $email = $this->option('email') ?: trim((string) Process::run('git config --get user.email')->output());

        $replacements = [
            '{{vendor}}' => $vendor,
            '{{repo}}' => $repo,
            '{{binary}}' => $binary,
            '{{appName}}' => $appName,
            '{{description}}' => $description,
            '{{tempPrefix}}' => $tempPrefix,
            '{{author}}' => $author,
            '{{email}}' => $email,
        ];

        $dir = $this->option('path') ?: getcwd().DIRECTORY_SEPARATOR.$repo;

        $files = [];
        $write = function (string $relative, string $content) use ($dir, $dryRun, &$files): void {
            $path = $dir.'/'.$relative;
            if (File::exists($path)) {
                $files[] = "skip (exists) {$relative}";

                return;
            }
            if ($dryRun) {
                $files[] = "write (dry-run) {$relative}";

                return;
            }
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $content);
            $files[] = "write {$relative}";
        };

        foreach (['app/Commands', 'app/Providers', 'bootstrap', 'config', 'tests/Feature', 'tests/Unit', '.github/workflows'] as $d) {
            if (! $dryRun) {
                File::ensureDirectoryExists($dir.'/'.$d);
            }
        }

        // The Laravel Zero skeleton, parameterized by binary name.
        $write($binary, Templates::render(Templates::binStub(), $replacements));
        $write('composer.json', Templates::render(Templates::composerJson(), $replacements));
        $write('bootstrap/app.php', Templates::bootstrapApp());
        $write('bootstrap/providers.php', Templates::bootstrapProviders());
        $write('config/app.php', Templates::render(Templates::configApp(), $replacements));
        $write('config/commands.php', Templates::configCommands());
        $write('app/Providers/AppServiceProvider.php', Templates::render(Templates::appServiceProvider(), $replacements));
        $write('app/Commands/SelfUpdateCommand.php', Templates::render(Templates::selfUpdateCommand(), $replacements));
        $write('app/Commands/ExampleCommand.php', Templates::exampleCommand());

        // Build + release furniture.
        $write('box.json', Templates::boxJson());
        $write('cliff.toml', Templates::cliffToml());
        $write('version.txt', "0.0.0\n");

        // Quality gates, canonical instead of drifted.
        $write('phpstan.neon.dist', Templates::phpstanNeon());
        $write('phpunit.xml.dist', Templates::phpunitXml());
        $write('tests/TestCase.php', Templates::testCase());
        $write('tests/Pest.php', Templates::pestPhp());
        $write('tests/Feature/ExampleCommandTest.php', Templates::exampleTest());

        // Shared with the package generators via laravel-zero-package-scaffold.
        $write('.editorconfig', Scaffold::editorconfig());
        $write('.gitattributes', Scaffold::gitattributes());
        $write('.gitignore', Scaffold::gitignore());
        $write('LICENSE', Scaffold::license($author, date('Y')));
        $write('CHANGELOG.md', Scaffold::changelog());
        $write('README.md', Templates::render(Templates::readme(), $replacements));

        $write('.github/workflows/release.yml', Templates::render(Templates::workflowRelease(), $replacements));
        $write('.github/workflows/run-tests.yml', Templates::workflowTests());
        $write('.github/workflows/phpstan.yml', Templates::workflowPhpstan());
        $write('.github/workflows/fix-php-code-style-issues.yml', Templates::workflowPint());
        $write('.github/dependabot.yml', Templates::dependabot());
        $write('.github/SECURITY.md', Templates::security());

        $gitLog = [];
        if (! $noGit) {
            foreach (['init -q', 'checkout -q -B main', 'add .', 'commit -q -m "chore: scaffold Laravel Zero CLI project"'] as $cmd) {
                $gitLog[] = $this->git($dir, $cmd, $dryRun);
            }
        }

        $this->components->info(($dryRun ? '[dry-run] ' : '')."Scaffolded {$vendor}/{$repo} at {$dir} (binary: {$binary})");
        foreach ($files as $line) {
            $this->line("  {$line}");
        }
        if (! $noGit) {
            $this->line('  git: '.implode(' | ', array_filter($gitLog)));
        }
        $this->newLine();
        $this->components->info('Next steps:');
        foreach ([
            'composer install',
            'composer test',
            "php {$binary} example world",
            "gh repo create {$vendor}/{$repo} --public --source={$dir} --remote=origin --push",
            'gh workflow run release.yml --ref main   # builds the PHAR, tags, and publishes',
            "packagist submit https://github.com/{$vendor}/{$repo}",
        ] as $step) {
            $this->line("  - {$step}");
        }

        return self::SUCCESS;
    }

    private function git(string $dir, string $cmd, bool $dryRun): string
    {
        if ($dryRun) {
            return "(dry-run) git {$cmd}";
        }
        File::ensureDirectoryExists($dir);
        $result = Process::path($dir)->run("git {$cmd}");
        if ($result->failed()) {
            return "git {$cmd} FAILED: ".trim($result->errorOutput());
        }

        return "git {$cmd} ok";
    }
}
