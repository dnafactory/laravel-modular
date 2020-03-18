<?php

namespace DNAFactory\Core\Provider;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

class ModuleFinderServiceProvider extends ServiceProvider
{
    public function getBasePath()
    {
        $modulePath = app_path() . DIRECTORY_SEPARATOR . 'Modules';
        return $modulePath;
    }

    public function register()
    {

    }

    public function boot()
    {
        $basePath = $this->getBasePath();
        if (!file_exists($basePath)) {
            return;
        }

        $directories = new \DirectoryIterator($basePath);
        foreach ($directories as $fileinfo) {
            if (!$fileinfo->isDir()) {
                continue;
            }

            if ($fileinfo->isDot()) {
                continue;
            }

            $moduleName = $fileinfo->getFilename();
            $modulePath = $basePath . DIRECTORY_SEPARATOR . $moduleName;

            $this->loadModule($modulePath, $moduleName);
        }
    }

    protected function loadModule($modulePath, $moduleName)
    {
        $this->loadConfigs($modulePath, $moduleName);
        $this->loadHelper($modulePath, $moduleName);
        $this->loadFactories($modulePath, $moduleName);

        $this->loadMigrations($modulePath, $moduleName);

        $this->loadViews($modulePath, $moduleName);
        $this->loadRoutes($modulePath, $moduleName);

        $this->loadServiceProviders($modulePath, $moduleName);
        $this->loadDependencyInjection($modulePath, $moduleName);
    }

    protected function loadConfigs($modulePath, $moduleName)
    {
        $configFolder = $modulePath . DIRECTORY_SEPARATOR . 'configs';

        if (file_exists($configFolder)) {
            $this->loadSubConfigs($configFolder, strtolower($moduleName));
        }
    }

    protected function loadSubConfigs($path, $moduleName)
    {
        $directories = new \DirectoryIterator($path);
        foreach ($directories as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }

            if ($fileinfo->isDir()) {
                $newConfigPath = $path . DIRECTORY_SEPARATOR . $fileinfo->getFilename();
                $newModuleName = $moduleName . '.' . $fileinfo->getFilename();
                $this->loadSubConfigs($newConfigPath, $newModuleName);
                continue;
            }

            $configFilename = $path . DIRECTORY_SEPARATOR . $fileinfo->getFilename();
            $configName = $moduleName . '.' . str_replace(".php", "", $fileinfo->getFilename());
            $this->mergeConfigFrom($configFilename, $configName);
        }
    }

    protected function loadHelper($modulePath, $moduleName)
    {
        $helperFile = $modulePath . DIRECTORY_SEPARATOR . 'helpers.php';

        if (file_exists($helperFile)) {
            require_once $helperFile;
        }
    }

    protected function loadFactories($modulePath, $moduleName)
    {
        $factoriesFolder = $modulePath . DIRECTORY_SEPARATOR . 'factories';

        if (file_exists($factoriesFolder)) {
            $this->loadFactoriesFrom($factoriesFolder);
        }
    }

    protected function loadMigrations($modulePath, $moduleName)
    {
        $migrationsFolder = $modulePath . DIRECTORY_SEPARATOR . 'migrations';

        if (file_exists($migrationsFolder)) {
            $this->loadMigrationsFrom($migrationsFolder);
        }
    }

    protected function loadViews($modulePath, $moduleName)
    {
        $viewsFolder = $modulePath . DIRECTORY_SEPARATOR . 'views';

        if (file_exists($viewsFolder)) {
            $this->loadViewsFrom($viewsFolder, strtolower($moduleName));
        }
    }

    protected function loadRoutes($modulePath, $moduleName)
    {
        $routeFile = $modulePath . DIRECTORY_SEPARATOR . 'routes.php';

        if (file_exists($routeFile)) {
            $this->loadRoutesFrom($routeFile);
        }
    }

    protected function loadServiceProviders($modulePath, $moduleName)
    {
        $providersFolder = $modulePath . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'providers.php';

        if (file_exists($providersFolder)) {
            $providers = require $providersFolder;

            if (!is_array($providers)) {
                throw new \Exception(sprintf("Attenzione, il file di service provider di %s deve esser un array di classi", $moduleName));
            }

            foreach ($providers as $provider) {
                $this->app->register($provider);
            }
        }
    }

    protected function loadDependencyInjection($modulePath, $moduleName)
    {
        $diFolder = $modulePath . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'di.php';

        if (file_exists($diFolder)) {
            $dis = require $diFolder;

            if (!is_array($dis)) {
                throw new \Exception(sprintf("Attenzione, il file di Dependency Injection di %s deve esser un array con due voci opzionali bind e singleton", $moduleName));
            }

            if (array_key_exists('bind', $dis)) {
                $binds = $dis['bind'];
                if (!is_array($binds)) {
                    throw new \Exception(sprintf("Attenzione, nel file di Dependency Injection di %s, bind deve essere un array associativo interface => concrete", $moduleName));
                }

                foreach ($binds as $interface => $concrete) {
                    $this->app->bind($interface, $concrete);
                }
            }

            if (array_key_exists('singleton', $dis)) {
                $singletons = $dis['singleton'];
                if (!is_array($singletons)) {
                    throw new \Exception(sprintf("Attenzione, nel file di Dependency Injection di %s, singleton deve essere un array associativo interface => concrete", $moduleName));
                }

                foreach ($singletons as $interface => $concrete) {
                    $this->app->singleton($interface, $concrete);
                }
            }
        }
    }
}
