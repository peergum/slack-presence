<?php

use AppBundle\AppBundle,
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle,
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle,
    Sensio\Bundle\DistributionBundle\SensioDistributionBundle,
    Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle,
    Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle,
    Symfony\Bundle\DebugBundle\DebugBundle,
    Symfony\Bundle\FrameworkBundle\FrameworkBundle,
    Symfony\Bundle\MonologBundle\MonologBundle,
    Symfony\Bundle\SecurityBundle\SecurityBundle,
    Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle,
    Symfony\Bundle\TwigBundle\TwigBundle,
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle,
    Symfony\Component\Config\Loader\LoaderInterface,
    Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            new FrameworkBundle(),
            new SecurityBundle(),
            new TwigBundle(),
            new MonologBundle(),
            new SwiftmailerBundle(),
            new DoctrineBundle(),
            new SensioFrameworkExtraBundle(),
            new AppBundle(),
            new DoctrineMigrationsBundle(),
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new DebugBundle();
            $bundles[] = new WebProfilerBundle();
            $bundles[] = new SensioDistributionBundle();
            $bundles[] = new SensioGeneratorBundle();
        }

        return $bundles;
    }

    public function getRootDir()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return dirname(__DIR__).'/var/cache/'.$this->getEnvironment();
    }

    public function getLogDir()
    {
        return dirname(__DIR__).'/var/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getRootDir().'/config/config_'.$this->getEnvironment().'.yml');
    }
}
