<?php

namespace NeoFusion\JsonRpcBundle\DependencyInjection;


class ServiceList
{
    /** @var array */
    private $services;

    /**
     * ServiceList constructor.
     */
    public function __construct()
    {
        $this->services = [];
    }

    /**
     * @param $service
     * @param string $alias
     */
    public function addService($service, string $alias): void
    {
        $this->services[$alias] = $service;
    }

    /**
     * @param string $alias
     * @return mixed|null
     */
    public function getService(string $alias)
    {
        if (array_key_exists($alias, $this->services)) {
            return $this->services[$alias];
        }

        return null;
    }

}
