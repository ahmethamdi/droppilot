<?php

namespace App\Providers;

use App\Services\Shopify\LoggingInstallShop;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Osiset\ShopifyApp\Actions\InstallShop as PackageInstallShop;
use Osiset\ShopifyApp\Contracts\Commands\Shop as IShopCommand;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Actions\VerifyThemeSupport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // kyon147/laravel-shopify paketinin InstallShop'unu kendi
        // logging-wrapper sürümümüzle değiştir. Paket OAuth callback'inde
        // exception'ı sessizce yutuyordu; bizim sürüm Laravel logger'a yazıyor.
        $this->app->bind(PackageInstallShop::class, function ($app) {
            return new LoggingInstallShop(
                $app->make(IShopQuery::class),
                $app->make(IShopCommand::class),
                $app->make(VerifyThemeSupport::class),
            );
        });
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
