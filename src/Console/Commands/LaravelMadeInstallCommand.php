<?php

namespace SoftTechMX\LaravelMade\Console\Commands;

use Illuminate\Console\Command;

class LaravelMadeInstallCommand extends Command
{
    protected $signature = 'laravel-made:install';

    protected $description = 'Publica e inicializa las dependencias base usadas por SoftTechMX (logger, permissions, livewire).';

    /**
     * Cada entrada es el comando artisan a ejecutar y los parámetros que normalmente
     * le pasarías por consola. Si en el futuro agregas/quitas dependencias en
     * composer.json, este es el único lugar que necesitas tocar.
     */
    protected array $steps = [
        [
            'label'   => 'Publicando migraciones de laravel-logger',
            'command' => 'vendor:publish',
            'params'  => ['--tag' => 'laravel-logger-migrations'],
        ],
        [
            'label'   => 'Publicando configuración de spatie/laravel-permission',
            'command' => 'vendor:publish',
            'params'  => ['--provider' => 'Spatie\Permission\PermissionServiceProvider'],
        ],
        [
            'label'   => 'Publicando configuración de Livewire',
            'command' => 'livewire:publish',
            'params'  => ['--config' => true],
        ],
        [
            'label'   => 'Publicando vistas de paginación de Livewire',
            'command' => 'livewire:publish',
            'params'  => ['--pagination' => true],
        ],
        [
            'label'   => 'Generando scaffolding de autenticación con Bootstrap (laravel/ui)',
            'command' => 'ui',
            'params'  => ['type' => 'bootstrap', '--auth' => true],
        ],
    ];

    public function handle(): int
    {
        $this->info('Inicializando dependencias de laravel-made...');
        $this->newLine();

        foreach ($this->steps as $step) {
            $this->line("→ {$step['label']}");

            $exitCode = $this->call($step['command'], $step['params']);

            if ($exitCode !== self::SUCCESS) {
                $this->error("El comando '{$step['command']}' falló. Deteniendo instalación.");
                return self::FAILURE;
            }

            $this->newLine();
        }

        $this->info('Listo. Todas las dependencias fueron inicializadas correctamente.');
        $this->comment('Recuerda correr "php artisan migrate" para aplicar las migraciones publicadas.');
        $this->comment('Y luego "npm install && npm run build" para compilar los assets de laravel/ui.');

        return self::SUCCESS;
    }
}