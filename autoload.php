<?php

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('Cissee\\Webtrees\\Module\\ResearchSuggestions\\', __DIR__);
$loader->addPsr4('Cissee\\WebtreesExt\\', __DIR__ . "/patchedWebtrees");
$loader->addPsr4('Cissee\\WebtreesExt\\Functions\\', __DIR__ . "/patchedWebtrees/Functions");
$loader->register();

$classMap = array();

//TODO Issue #2
//adjustments for SOUR.DATA.EVEN
$extend = !class_exists("Fisharebest\Webtrees\Functions\FunctionsEdit", false);
if ($extend) {
  $classMap["Fisharebest\Webtrees\Functions\FunctionsEdit"] = __DIR__ . '/replacedWebtrees/Functions/FunctionsEdit.php';
}

$loader->addClassMap($classMap);        
$loader->register(true); //prepend in order to override definitions from default class loader
