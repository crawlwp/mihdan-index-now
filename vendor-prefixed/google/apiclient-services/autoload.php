<?php

namespace Mihdan\IndexNow\Dependencies;

// For older (pre-2.7.2) verions of google/apiclient
if (\file_exists(__DIR__ . '/../apiclient/src/Google/Client.php') && !\class_exists('Mihdan\\IndexNow\\Dependencies\\Google_Client', \false)) {
    require_once __DIR__ . '/../apiclient/src/Google/Client.php';
    if (\defined('Mihdan\\IndexNow\\Dependencies\\Google_Client::LIBVER') && \version_compare(Google_Client::LIBVER, '2.7.2', '<=')) {
        $servicesClassMap = ['Mihdan\\IndexNow\\Dependencies\\Google\\Client' => 'Google_Client', 'Mihdan\\IndexNow\\Dependencies\\Google\\Service' => 'Google_Service', 'Mihdan\\IndexNow\\Dependencies\\Google\\Service\\Resource' => 'Google_Service_Resource', 'Mihdan\\IndexNow\\Dependencies\\Google\\Model' => 'Google_Model', 'Mihdan\\IndexNow\\Dependencies\\Google\\Collection' => 'Google_Collection'];
        foreach ($servicesClassMap as $alias => $class) {
            \class_alias($class, $alias);
        }
    }
}
\spl_autoload_register(function ($class) {
    if (0 === \strpos($class, 'Google_Service_')) {
        // Autoload the new class, which will also create an alias for the
        // old class by changing underscores to namespaces:
        //     Google_Service_Speech_Resource_Operations
        //      => Google\Service\Speech\Resource\Operations
        $classExists = \class_exists($newClass = \str_replace('_', '\\', $class));
        if ($classExists) {
            return \true;
        }
    }
}, \true, \true);
