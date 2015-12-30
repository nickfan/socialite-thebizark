<?php
namespace SocialiteProviders\Thebizark;

use SocialiteProviders\Manager\SocialiteWasCalled;

class ThebizarkExtendSocialite
{
    /**
     * Execute the provider.
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('thebizark', __NAMESPACE__.'\Provider');
    }
}
