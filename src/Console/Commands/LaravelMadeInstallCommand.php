<?php

namespace SoftTechMX\LaravelMade\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class LaravelMadeInstallCommand extends Command
{
    protected $signature = 'laravel-made:install
        {--skip-npm : No instalar las dependencias de npm}
        {--force : Sobrescribir resources/js/app.js sin preguntar}';

    protected $description = 'Publica e inicializa las dependencias base usadas por SoftTechMX (logger, permissions, livewire).';

    /**
     * Cada entrada es el comando artisan a ejecutar y los parámetros que normalmente
     * le pasarías por consola. Si en el futuro agregas/quitas dependencias en
     * composer.json, este es el único lugar que necesitas tocar.
     */
    protected array $configCommands = [
        [
            'label'   => 'Creando migraciones de laravel-logger',
            'command' => 'vendor:publish',
            'params'  => ['--tag' => 'laravel-logger-migrations'],
            'check_tag'  => 'laravel-logger-migrations',
        ],
        [
            'label'   => 'Creando ServiceProvider de spatie/laravel-permission',
            'command' => 'vendor:publish',
            'params'  => ['--provider' => 'Spatie\Permission\PermissionServiceProvider'],
            'check_provider' => 'Spatie\Permission\PermissionServiceProvider',
        ],
        [
            'label'   => 'Creando archivo de configuración de Livewire 3',
            'command' => 'livewire:publish',
            'params'  => ['--config' => true],
        ],
        [
            'label'   => 'Creando vistas de Paginacion para componentes Livewire 3',
            'command' => 'livewire:publish',
            'params'  => ['--pagination' => true],
            
        ],
        [
            'label'   => 'Generando scaffolding de autenticación con Bootstrap (laravel/ui)',
            'command' => 'ui',
            'params'  => ['type' => 'bootstrap', '--auth' => true],
        ],
    ];

    protected array $npmPackages = [
        'aos@^3.0.0-beta.6',
        'glightbox@^3',
        'bootstrap@^5',
        'bootstrap-icons@^1',
        'isotope-layout@^3',
        'jquery@^4',
        '@srexi/purecounterjs@^1',
        'swiper@^14',
        'toastr@^2',
        'typed.js@^3',
    ];

    /** stub (en /stubs) => destino (relativo a base_path) */
    protected array $stubs = [
        'app.js.stub'   => 'resources/js/app.js',
        'app.scss.stub' => 'resources/sass/app.scss',
    ];

    protected function installNpmPackages(): void
    {
        if (! file_exists(base_path('package.json'))) {
            $this->warn('No se encontró package.json; omito la instalación de npm.');
            return;
        }

        $this->info('Instalando dependencias frontend ...');

        $process = new Process(['npm', 'install', ...$this->npmPackages], base_path());
        $process->setTimeout(null);
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            $this->error('Falló npm install. Ejecútalo manualmente:');
            $this->line('  npm i ' . implode(' ', $this->npmPackages));
        }
    }

    /**
     * ¿Ya están en database/migrations todas las migraciones que publicaría este tag/provider?
     */
    protected function migrationsAlreadyPublished(?string $provider = null, ?string $tag = null): bool
    {
        $migrationsPath = database_path('migrations');

        $existing = collect(File::files($migrationsPath))
            ->map(fn ($file) => $this->normalizeMigrationName($file->getFilename()))
            ->all();

        $expected = [];

        foreach (ServiceProvider::pathsToPublish($provider, $tag) as $source => $destination) {
            // Solo nos interesan los que van a database/migrations
            if (! str_starts_with($destination, $migrationsPath)) {
                continue;
            }

            if (is_dir($source)) {
                foreach (File::files($source) as $file) {
                    $expected[] = $this->normalizeMigrationName($file->getFilename());
                }
            } else {
                $expected[] = $this->normalizeMigrationName(basename($destination));
            }
        }

        // Si el tag no publica ninguna migración, no hay nada que validar
        if (empty($expected)) {
            return false;
        }

        return empty(array_diff($expected, $existing));
    }

    /** Quita el timestamp inicial y la extensión (.php / .php.stub). */
    protected function normalizeMigrationName(string $filename): string
    {
        $name = preg_replace('/\.php(\.stub)?$/', '', $filename);

        return preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name);
    }


    protected function publishStubs(): void
    {
        foreach ($this->stubs as $stub => $destination) {
            $this->publishStub(
                dirname(__DIR__, 3) . "/stubs/{$stub}",
                base_path($destination),
                $destination
            );
        }

        $this->ensureSassVariables();
    }

    protected function publishStub(string $source, string $target, string $label): void
    {
        if (! File::exists($source)) {
            $this->error("No se encontró el stub: {$source}");
            return;
        }

        File::ensureDirectoryExists(dirname($target));

        if (File::exists($target)) {
            if (md5_file($source) === md5_file($target)) {
                $this->line("  {$label} ya está actualizado.");
                return;
            }

            if (! $this->option('force')
                && ! $this->confirm("{$label} ya existe. ¿Sobrescribir? (se guardará un respaldo)", true)) {
                $this->warn("  Omitido {$label}.");
                return;
            }

            File::copy($target, $target . '.' . date('YmdHis') . '.bak');
        }

        File::copy($source, $target);
        $this->info("✔ {$label} publicado.");
    }

    protected function ensureSassVariables(): void
    {
        $variables = resource_path('sass/_variables.scss');

        if (! File::exists($variables)) {
            File::put($variables, "// Variables de Bootstrap (se importan antes que bootstrap)\n");
            $this->line('  Creado resources/sass/_variables.scss (requerido por app.scss).');
        }
    }

    protected function publishAppJs(): void
    {
        $source = dirname(__DIR__, 3) . '/stubs/app.js.stub';
        $target = resource_path('js/app.js');

        if (! File::exists($source)) {
            $this->error("No se encontró el stub: {$source}");
            return;
        }

        File::ensureDirectoryExists(dirname($target));

        if (File::exists($target)) {
            // Idempotente: si ya es idéntico, no hacemos nada
            if (md5_file($source) === md5_file($target)) {
                $this->line('  resources/js/app.js ya está actualizado.');
                return;
            }

            if (! $this->option('force')
                && ! $this->confirm('resources/js/app.js ya existe. ¿Sobrescribir? (se guardará un respaldo .bak)', true)) {
                $this->warn('  Omitido resources/js/app.js.');
                return;
            }

            File::copy($target, $target . '.bak');
        }

        File::copy($source, $target);
        $this->info('✔ resources/js/app.js publicado.');
    }

    public function handle(): int
    {
        $this->info('Configurando dependencias del Backend ...');
        $this->newLine();

        foreach ($this->configCommands as $step) {
            $this->line("→ {$step['label']}");

            if (
                (isset($step['check_tag']) || isset($step['check_provider']))
                && $this->migrationsAlreadyPublished($step['check_provider'] ?? null, $step['check_tag'] ?? null)
            ) {
                $this->warn('  Omitido: las migraciones ya existen en database/migrations.');
                $this->newLine();
                continue;
            }

            $exitCode = $this->call($step['command'], $step['params']);

            if ($exitCode !== self::SUCCESS) {
                $this->error("El comando '{$step['command']}' falló. Deteniendo instalación.");
                return self::FAILURE;
            }

            $this->newLine();
        }

        if (! $this->option('skip-npm')) {
            $this->installNpmPackages();
        }

        $this->line('→ Publicando resources/js/app.js y resources/sass/app.scss');
        $this->publishStubs();
        $this->newLine();

        $this->info('Listo. Todas las dependencias fueron inicializadas correctamente.');
        $this->comment('Recuerda correr "php artisan migrate" para aplicar las migraciones publicadas.');
        $this->comment('Y luego "npm install && npm run build" para compilar los assets de laravel/ui.');

        return self::SUCCESS;
    }
}