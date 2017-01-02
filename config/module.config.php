<?php
namespace Search;

use Zend\Router\Http\Segment;
use Zend\ServiceManager\Factory\InvokableFactory;

return [
    'search' => [
        'show_per_page' => 10,
        'index_path' => '',
    ],
    'router' => [
        'routes' => [
            // LIFO
            'search' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/search[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\SearchController::class,
                        'action' => 'simple',
                    ],
                ],
            ],
            // LIFO
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\SearchController::class => Controller\SearchControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'aliases' => [
            'searchQuery' => Controller\Plugin\SearchQuery::class,
        ],
        'factories' => [
            Controller\Plugin\SearchQuery::class => InvokableFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
];
