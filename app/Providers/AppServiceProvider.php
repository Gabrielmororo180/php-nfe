<?php

namespace App\Providers;

use App\Core\Application\Ports\Outbound\FileStorageServiceInterface;
use App\Core\Application\Ports\Outbound\NFeFiscalGatewayInterface;
use App\Infrastructure\Secondary\Fiscal\NFePhpFiscalAdapter;
use App\Infrastructure\Secondary\Storage\LocalFileStorageAdapter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application service bindings (Dependency Inversion Principle - DIP).
     */
    public function register(): void
    {
        // Bind fiscal gateway outbound port to NFePHP secondary adapter
        $this->app->bind(
            NFeFiscalGatewayInterface::class,
            NFePhpFiscalAdapter::class
        );

        // Bind storage outbound port to Local Storage secondary adapter
        $this->app->bind(
            FileStorageServiceInterface::class,
            LocalFileStorageAdapter::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
