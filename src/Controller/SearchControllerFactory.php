<?php
namespace Search\Controller;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Search\Controller\SearchController;
use Core\Service\ModulePageService;
use Block\Service\BlockService;
use Item\Service\ItemService;
use Account\Service\AccountService;
use Zend\Log\Logger;
use Core\Controller\AbstractControllerFactory;

class SearchControllerFactory extends AbstractControllerFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new SearchController(
            $container->get(ModulePageService::class),
            $container->get(BlockService::class),
            $container->get(ItemService::class),
            $container->get(AccountService::class),
            $container->get(Logger::class),
            $container->get('config')
        );
    }

}
