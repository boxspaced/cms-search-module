<?php
namespace Search;

class Module
{

    const VERSION = '4.0.0';

    /**
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

}
