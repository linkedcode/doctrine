<?php

namespace Linkedcode\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Linkedcode\Slim\Settings;
use Psr\Container\ContainerInterface;

class Definitions
{
    public static function get(): array
    {
        return [
            EventManager::class => function() {
                return new EventManager;
            },
            EntityManager::class => function(ContainerInterface $container) {
                return $container->get(EntityManagerInterface::class);
            },
            EntityManagerInterface::class => function (ContainerInterface $container) {
                $appDir = $container->get(Settings::class)->getAppDir();

                $config = ORMSetup::createXMLMetadataConfiguration(
                    paths: array($appDir . "/config/xml"),
                    isDevMode: $container->get(Settings::class)->get('doctrine.isDevMode'),
                    isXsdValidationEnabled: false,
                );

                $dbParams = $container->get(Settings::class)->get('db');

                $connection = DriverManager::getConnection($dbParams, $config);
                $entityManager = new EntityManager($connection, $config);

                return $entityManager;
            }
        ];
    }
}