<?php

namespace App\Services\Shopify;

use Exception;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Actions\InstallShop as PackageInstallShop;
use Osiset\ShopifyApp\Actions\VerifyThemeSupport;
use Osiset\ShopifyApp\Contracts\Commands\Shop as IShopCommand;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Objects\Enums\AuthMode;
use Osiset\ShopifyApp\Objects\Enums\ThemeSupportLevel as ThemeSupportLevelEnum;
use Osiset\ShopifyApp\Objects\Values\NullAccessToken;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Objects\Values\ThemeSupportLevel;
use Osiset\ShopifyApp\Util;
use Throwable;

/**
 * Paket InstallShop'unun davranışını birebir kopyalar, ama OAuth
 * callback aşamasındaki exception'ı **loglar** ki "Missing auth url"
 * hatasının gerçek sebebini görebilelim. Paket aksi halde sessizce
 * url: null dönüp hatayı yutuyor.
 */
class LoggingInstallShop extends PackageInstallShop
{
    public function __invoke(ShopDomain $shopDomain, ?string $code = null, ?string $idToken = null): array
    {
        $shop = $this->shopQuery->getByDomain($shopDomain, [], true);

        if ($shop === null) {
            $this->shopCommand->make($shopDomain, NullAccessToken::fromNative(null));
            $shop = $this->shopQuery->getByDomain($shopDomain);
        }

        $apiHelper = $shop->apiHelper();
        $grantMode = $shop->hasOfflineAccess()
            ? AuthMode::fromNative(Util::getShopifyConfig('api_grant_mode', $shop))
            : AuthMode::OFFLINE();

        if (empty($code) && empty($idToken)) {
            return [
                'completed' => false,
                'url' => $apiHelper->buildAuthUrl($grantMode, Util::getShopifyConfig('api_scopes', $shop)),
                'shop_id' => $shop->getId(),
            ];
        }

        try {
            if ($shop->trashed()) {
                $shop->restore();
            }

            $data = $idToken !== null
                ? $apiHelper->performOfflineTokenExchange($idToken)
                : $apiHelper->getAccessData($code, $grantMode);

            $this->persistShopifyOAuthTokens($shop, $data, $grantMode);

            try {
                $themeSupportLevel = call_user_func($this->verifyThemeSupport, $shop->getId());
                $this->shopCommand->setThemeSupportLevel($shop->getId(), ThemeSupportLevel::fromNative($themeSupportLevel));
            } catch (Exception $e) {
                $themeSupportLevel = ThemeSupportLevelEnum::NONE;
            }

            return [
                'completed' => true,
                'url' => null,
                'shop_id' => $shop->getId(),
                'theme_support_level' => $themeSupportLevel,
            ];
        } catch (Throwable $e) {
            Log::error('Shopify InstallShop OAuth exception', [
                'shop' => $shopDomain->toNative(),
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return [
                'completed' => false,
                'url' => null,
                'shop_id' => null,
                'theme_support_level' => null,
            ];
        }
    }
}
