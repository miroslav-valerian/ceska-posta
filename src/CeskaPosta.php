<?php

namespace Valerian\CeskaPosta;

class CeskaPosta
{
    /**
     * @param $packageId
     * @return PackageInfo
     */
    public function getPackageInfo($packageId)
    {
        $packageInfo = new PackageInfo($packageId);
        return $packageInfo->parse();
    }
}
