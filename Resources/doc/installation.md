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

Enable the bundle by adding it to the list of registered bundles in the
`config/bundles.php` file of your Sulu project.

```php
Task\TaskBundle\TaskBundle::class => ['all' => true],
Sulu\Bundle\AutomationBundle\SuluAutomationBundle::class => ['all' => true],
```

## Step 3: Configure the routes

Add the required routes to the `config/routes/sulu_admin.yaml` configuration file.

```yaml
sulu_automation_api:
    resource: '@SuluAutomationBundle/Resources/config/routing_api.yml'
    prefix: /admin/api

sulu_automation:
    type: rest
    resource: '@SuluAutomationBundle/Resources/config/routing.yml'
    prefix: /admin/automation
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

Admins won't automatically start seeing automation tab. You need to modify your user roles and grant permissions to automations in order for that to work.
