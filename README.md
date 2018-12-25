# NeoFusionJsonRpcBundle

JSON-RPC 2.0 Server for Symfony

[![Build Status](https://travis-ci.org/NeoFusion/JsonRpcBundle.svg?branch=master)](https://travis-ci.org/NeoFusion/JsonRpcBundle)
[![Coverage Status](https://coveralls.io/repos/github/NeoFusion/JsonRpcBundle/badge.svg)](https://coveralls.io/github/NeoFusion/JsonRpcBundle)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/NeoFusion/JsonRpcBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/NeoFusion/JsonRpcBundle/?branch=master)

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/4bfb5084-73f1-4aa5-bebc-aeaed9694f9b/big.png)](https://insight.sensiolabs.com/projects/4bfb5084-73f1-4aa5-bebc-aeaed9694f9b)

## Installation

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require neofusion/json-rpc-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...

            new NeoFusion\JsonRpcBundle\NeoFusionJsonRpcBundle(),
        );

        // ...
    }

    // ...
}
```

### Step 3: Configure API methods

You can easily define methods in your configuration file (config/services.yaml):

```yaml
services:
    App\Api\Auth:
        public: false
        tags:
          - { name: 'app.api.json_rpc', alias: 'auth' }
    

```

### Step 4: Register the routes

Finally, register this bundle's routes by adding the following to your project's routing file:

```yaml
# app/config/routes/neofusion_jsonrpc.yaml
neofusion_jsonrpc:
    resource: "@NeoFusionJsonRpcBundle/Controller/ServerController.php"
    prefix: /
    type: annotation
```

### Step 5: Register compiler

```
# src/Kernel.php
    protected function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new JsonRpcPass());
    }

```