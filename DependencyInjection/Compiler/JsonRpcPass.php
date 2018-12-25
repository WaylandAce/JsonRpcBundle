<?php

namespace NeoFusion\JsonRpcBundle\DependencyInjection\Compiler;

use NeoFusion\JsonRpcBundle\DependencyInjection\ServiceList;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;


class JsonRpcPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        // always first check if the primary service is defined
        if (!$container->has(ServiceList::class)) {
            return;
        }

        $definition = $container->findDefinition(ServiceList::class);

        // find all service IDs with the app.mail_transport tag
        $taggedServices = $container->findTaggedServiceIds('app.api.json_rpc');

        foreach ($taggedServices as $id => $tags) {
            // add the transport service to the TransportChain service
            foreach ($tags as $attributes) {
                $definition->addMethodCall('addService', array(
                    new Reference($id),
                    $attributes["alias"] ?? ''
                ));
            }
        }
    }
}