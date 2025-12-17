<?php

namespace Linkedcode\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Linkedcode\Base\Settings;
use Psr\Container\ContainerInterface;

class Definitions
{
    public static function get(array $options = []): array
    {
        return [
            EventManager::class => function () {
                return new EventManager;
            },
            EntityManager::class => function (ContainerInterface $container) {
                return $container->get(EntityManagerInterface::class);
            },
            EntityManagerInterface::class => function (ContainerInterface $container) use ($options) {
                $settings = $container->get(Settings::class);
                $appDir = $settings->getAppDir();
                $metadataConfig = $settings->get('doctrine.XMLMetadataConfiguration');
                $paths = [];

                foreach ($metadataConfig['paths'] as $p) {
                    $paths[] = $appDir . '/' . ltrim($p, '/');
                }

                $config = ORMSetup::createXMLMetadataConfiguration(
                    paths: $paths,
                    isDevMode: $metadataConfig['isDevMode'],
                    isXsdValidationEnabled: false,
                );

                $config->enableNativeLazyObjects(true);

                $dbParams = $settings->get('db');

                $connection = DriverManager::getConnection($dbParams, $config);

                if ($settings->get('doctrine.types.uuid', false)) {
                    Type::addType('uuid_binary', 'Ramsey\Uuid\Doctrine\UuidBinaryType');
                    $connection->getDatabasePlatform()->registerDoctrineTypeMapping('uuid_binary', 'binary');
                }

                $entityManager = new EntityManager($connection, $config);

                return $entityManager;
            }
        ];
    }
}
