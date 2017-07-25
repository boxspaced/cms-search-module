<?php
namespace Boxspaced\CmsSearchModule\Controller;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Boxspaced\CmsSearchModule\Controller\SearchController;
use Boxspaced\CmsCoreModule\Service\ModulePageService;
use Boxspaced\CmsBlockModule\Service\BlockService;
use Boxspaced\CmsItemModule\Service\ItemService;
use Boxspaced\CmsAccountModule\Service\AccountService;
use Zend\Log\Logger;
use Boxspaced\CmsCoreModule\Controller\AbstractControllerFactory;

class SearchControllerFactory extends AbstractControllerFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $controller = new SearchController(
            $container->get(ModulePageService::class),
            $container->get(BlockService::class),
            $container->get(ItemService::class),
            $container->get(AccountService::class),
            $container->get(Logger::class),
            $container->get('config')
        );

        return $this->adminNavigationWidget($controller);
    }

}
