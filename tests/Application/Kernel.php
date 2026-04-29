<?php

namespace Talleu\TriggerMapping\Tests\Application;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Talleu\TriggerMapping\Bundle\TriggerMappingBundle;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new TriggerMappingBundle();

        // doctrine/doctrine-migrations-bundle is now an optional dependency of the bundle.
        // Register it only when the package is installed so the kernel still boots when
        // contributors run `composer remove doctrine/doctrine-migrations-bundle` to verify
        // the bundle works without it (see the `no_migrations_bundle` CI job).
        if (class_exists(DoctrineMigrationsBundle::class)) {
            yield new DoctrineMigrationsBundle();
        }

        yield new MakerBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('container.autowiring.strict_mode', true);
        $loader->load($this->getProjectDir().'/Application/config/{packages}/*.yaml', 'glob');
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/TalleuTriggerMapping/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/TalleuTriggerMapping/log';
    }
}
