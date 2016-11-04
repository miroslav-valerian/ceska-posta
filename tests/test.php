<?php

include '../vendor/autoload.php';


$ceskaPosta = new \Valerian\CeskaPosta\CeskaPosta();
$packageInfo = $ceskaPosta->getPackageInfo('package number');
print_r($packageInfo);
