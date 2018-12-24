# NeoFusionJsonRpcBundle

JSON-RPC 2.0 Server for Symfony

## Installation

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require waylandace/json-rpc-bundle
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
    
    app.api.auth:
        alias: App\Api\Auth
        public: true
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
