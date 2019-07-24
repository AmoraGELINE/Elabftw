<?php declare(strict_types=1);
/**
 * generate-cache.php
 * force the generation of Twig cache files
 * so we can use them for gettext po/mo generation
 *
 * Usage: php src/tools/generate-cache.php
 */
require_once \dirname(__DIR__, 2) . '/vendor/autoload.php';
$tplDir = \dirname(__DIR__, 2) . '/src/templates';
$tmpDir = '/tmp/elabftw-twig-cache/';

$loader = new \Twig\Loader\FilesystemLoader($tplDir);

// force auto-reload to always have the latest version of the template
$TwigEnvironment = new Twig\Environment($loader, array(
    'cache' => $tmpDir,
    'auto_reload' => true,
));
// custom twig filters
$filterOptions = array('is_safe' => array('html'));
$msgFilter = new \Twig\TwigFilter('msg', '\Elabftw\Elabftw\Tools::displayMessage', $filterOptions);
$dateFilter = new \Twig\TwigFilter('kdate', '\Elabftw\Elabftw\Tools::formatDate', $filterOptions);
$mdFilter = new \Twig\TwigFilter('md2html', '\Elabftw\Elabftw\Tools::md2html', $filterOptions);
$starsFilter = new \Twig\TwigFilter('stars', '\Elabftw\Elabftw\Tools::showStars', $filterOptions);
$bytesFilter = new \Twig\TwigFilter('formatBytes', '\Elabftw\Elabftw\Tools::formatBytes', $filterOptions);
$extFilter = new \Twig\TwigFilter('getExt', '\Elabftw\Elabftw\Tools::getExt', $filterOptions);
$filesizeFilter = new \Twig\TwigFilter('filesize', '\filesize', $filterOptions);
$limitOptions = new \Twig\TwigFunction('limitOptions', '\Elabftw\Elabftw\Tools::getLimitOptions');

// custom test to check for a file
$test = new \Twig\TwigTest('readable', function (string $path) {
    return \is_readable($this->getUploadsPath() . $path);
});
$TwigEnvironment->addTest($test);
$TwigEnvironment->addFilter($msgFilter);
$TwigEnvironment->addFilter($dateFilter);
$TwigEnvironment->addFilter($mdFilter);
$TwigEnvironment->addFilter($starsFilter);
$TwigEnvironment->addFilter($bytesFilter);
$TwigEnvironment->addFilter($extFilter);
$TwigEnvironment->addFilter($filesizeFilter);
$TwigEnvironment->addFunction($limitOptions);
$TwigEnvironment->addExtension(new \Twig_Extensions_Extension_I18n());

// iterate over all the templates
foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tplDir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
    // force compilation
    if ($file->isFile()) {
        $TwigEnvironment->loadTemplate(str_replace($tplDir . '/', '', $file));
    }
}
