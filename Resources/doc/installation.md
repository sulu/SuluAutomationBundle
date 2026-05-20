# Installation

## Requirements

Before installing the bundle, ensure your project matches the supported versions from `composer.json`:

- PHP `^8.2`
- Sulu `^3.0`
- `php-task/php-task` `^3.0`
- `php-task/task-bundle` `^4.0`

## Step 1: Download the Bundle

Open a command console, enter your project directory and execute the following
command to download the latest stable version of this bundle:

```bash
composer require sulu/automation-bundle
```

This command requires you to have Composer installed globally, as explained in
the [installation chapter](https://getcomposer.org/doc/00-intro.md) of the
Composer documentation.

### Step 2: Enable the Bundle

If Symfony Flex did not enable the bundle automatically, add it to the list of registered bundles in the
`config/bundles.php` file of your Sulu project.

```php
Task\TaskBundle\TaskBundle::class => ['all' => true],
Sulu\Bundle\AutomationBundle\SuluAutomationBundle::class => ['all' => true],
```

## Step 3: Configure the routes

Add the required routes to the `config/routes/sulu_admin.yaml` configuration file.

```yaml
sulu_automation_api:
    resource: '@SuluAutomationBundle/Resources/config/routing_api.yaml'
    prefix: /admin/api
```

## Step 4: Update the database schema

The bundle needs a few database tables, therefore you should update your schema
using doctrine migrations or the following command:

```console
bin/console doctrine:schema:update --force
```

## Step 5: Configure a cronjob

The php-task library requires a cronjob to run the scheduled tasks. The cronjob
should run the `task:run` command.

```console
bin/adminconsole task:run
```

## Step 6: Modify user roles

Admins will not automatically see the automation tab. Grant the automation permissions to the relevant user roles so the tab becomes available in the Sulu admin.
