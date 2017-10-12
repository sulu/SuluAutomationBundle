# Installation

## Step 1: Download the Bundle

Open a command console, enter your project directory and execute the following
command to download the latest stable version of this bundle:

```console
$ composer require sulu/automation-bundle
```

This command requires you to have Composer installed globally, as explained in
the [installation chapter](https://getcomposer.org/doc/00-intro.md) of the
Composer documentation.

### Step 2: Enabled the Bundle

Then, enable the bundle by adding it to the list of registered bundles in the
`app/AbstractKernel.php` file of your Sulu project.

```php
<?php
// app/AbstractKernel.php

// ...
abstract class AbstractKernel extends SuluKernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new Task\TaskBundle\TaskBundle(),
            new Sulu\Bundle\AutomationBundle\SuluAutomationBundle(),
        ];
    }
}
```

## Step 3: Configure the routes

Then, add the required routes to the `app/config/admin/routing.yml` configuration file.

```yaml
# ...
sulu_automation_api:
    type: rest
    resource: "@SuluAutomationBundle/Resources/config/routing_api.xml"
    prefix: /admin/api

sulu_automation:
    type: rest
    resource: "@SuluAutomationBundle/Resources/config/routing.xml"
    prefix: /admin/automation
```

## Step 4: Update the database schema

The bundle needs a few database tables, therefore you should update your schema
using doctrine migrations or the following command:

```console
bin/console doctrine:schema:update --force
```

## Step 5: Export the translations

This bundle also includes a few translations, which must be exported:

```console
bin/console sulu:translate:export
```

## Step 6: Configure a cronjob

The php-task library requires a cronjob to run the scheduled tasks. The cronjob
should run the `task:run` command.

```console
bin/adminconsole task:run
```

## Step 7: Modify user roles

Admins won't automatically start seeing automation tab. You need to modify your user roles and grant permissions to automations in order for that to work.
