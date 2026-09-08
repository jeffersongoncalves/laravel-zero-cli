<?php

use Illuminate\Support\Facades\File;

it('scaffolds a project in dry-run mode without touching disk', function () {
    $dir = sys_get_temp_dir().'/laravel-zero-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-repo' => 'acme/example-cli',
        'description' => 'Example CLI',
        '--path' => $dir,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(is_dir($dir))->toBeFalse();
});

it('derives the binary from the repo name and wires it through every file', function () {
    $dir = sys_get_temp_dir().'/laravel-zero-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-repo' => 'jeffersongoncalves/deps-cli',
        'description' => 'Audit composer dependencies',
        '--path' => $dir,
        '--no-git' => true,
    ])->assertExitCode(0);

    $composer = json_decode(file_get_contents($dir.'/composer.json'), true);

    expect(is_file($dir.'/deps'))->toBeTrue()
        ->and($composer['name'])->toBe('jeffersongoncalves/deps-cli')
        ->and($composer['description'])->toBe('Audit composer dependencies')
        ->and($composer['bin'])->toBe(['builds/deps'])
        ->and($composer['scripts']['build'])->toContain('php deps app:build deps')
        ->and(file_get_contents($dir.'/config/app.php'))->toContain("'name' => 'Deps CLI'")
        ->and(file_get_contents($dir.'/app/Commands/SelfUpdateCommand.php'))->toContain("return 'deps.phar';")
        ->and(file_get_contents($dir.'/app/Commands/SelfUpdateCommand.php'))->toContain("return 'deps_';")
        ->and(file_get_contents($dir.'/app/Providers/AppServiceProvider.php'))->toContain("assetName: 'deps.phar'")
        ->and(file_get_contents($dir.'/.github/workflows/release.yml'))->toContain('cp builds/deps deps.phar');

    File::deleteDirectory($dir);
});

it('keeps the GitHub Actions expressions intact', function () {
    $dir = sys_get_temp_dir().'/laravel-zero-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-repo' => 'acme/thing-cli',
        '--path' => $dir,
        '--no-git' => true,
    ])->assertExitCode(0);

    $release = file_get_contents($dir.'/.github/workflows/release.yml');
    $tests = file_get_contents($dir.'/.github/workflows/run-tests.yml');

    // Nowdoc templates: a PHP heredoc would have eaten ${{ ... }} as interpolation.
    expect($release)->toContain('${{ steps.v.outputs.tag }}')
        ->and($release)->toContain('${{ secrets.GITHUB_TOKEN }}')
        ->and($release)->not->toContain('{{binary}}')
        ->and($tests)->toContain('${{ matrix.php }}');

    File::deleteDirectory($dir);
});

it('writes the full file set and leaves no placeholder behind', function () {
    $dir = sys_get_temp_dir().'/laravel-zero-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-repo' => 'acme/thing-cli',
        'description' => 'Thing',
        '--path' => $dir,
        '--no-git' => true,
    ])->assertExitCode(0);

    foreach ([
        'thing', 'composer.json', 'box.json', 'cliff.toml', 'version.txt', 'LICENSE',
        'CHANGELOG.md', 'README.md', 'phpstan.neon.dist', 'phpunit.xml.dist',
        '.editorconfig', '.gitattributes', '.gitignore',
        'bootstrap/app.php', 'bootstrap/providers.php', 'config/app.php', 'config/commands.php',
        'app/Providers/AppServiceProvider.php', 'app/Commands/SelfUpdateCommand.php',
        'app/Commands/ExampleCommand.php', 'tests/TestCase.php', 'tests/Pest.php',
        'tests/Feature/ExampleCommandTest.php',
        '.github/workflows/release.yml', '.github/workflows/run-tests.yml',
        '.github/workflows/phpstan.yml', '.github/workflows/fix-php-code-style-issues.yml',
        '.github/dependabot.yml', '.github/SECURITY.md',
    ] as $file) {
        expect(is_file($dir.'/'.$file))->toBeTrue("missing {$file}");
    }

    foreach (File::allFiles($dir) as $file) {
        expect($file->getContents())->not->toContain('{{', 'placeholder left in '.$file->getRelativePathname());
    }

    File::deleteDirectory($dir);
});

it('honours an explicit --binary and --app-name', function () {
    $dir = sys_get_temp_dir().'/laravel-zero-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-repo' => 'jeffersongoncalves/obsidian-notes-cli',
        '--binary' => 'obsidian-notes',
        '--app-name' => 'Obsidian Notes CLI',
        '--path' => $dir,
        '--no-git' => true,
    ])->assertExitCode(0);

    expect(is_file($dir.'/obsidian-notes'))->toBeTrue()
        ->and(file_get_contents($dir.'/config/app.php'))->toContain("'name' => 'Obsidian Notes CLI'")
        ->and(file_get_contents($dir.'/app/Commands/SelfUpdateCommand.php'))->toContain("return 'obsidian_notes_';");

    File::deleteDirectory($dir);
});

it('rejects a vendor-repo without a slash', function () {
    $this->artisan('create', [
        'vendor-repo' => 'not-a-vendor-repo',
        '--dry-run' => true,
    ])->assertExitCode(1);
});
