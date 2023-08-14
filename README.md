# SuluAutomationBundle

<p align="center">
    <a href="https://sulu.io/" target="_blank">
        <img width="30%" src="https://sulu.io/uploads/media/800x/00/230-Official%20Bundle%20Seal.svg?v=2-6&inline=1" alt="Official Sulu Bundle Badge">
    </a>
</p>
<p align="center">
    <a href="LICENSE" target="_blank">
        <img src="https://img.shields.io/github/license/sulu/SuluAutomationBundle.svg" alt="GitHub license">
    </a>
    <a href="https://github.com/sulu/SuluAutomationBundle/actions" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/sulu/SuluAutomationBundle/test-application.yaml?branch=2.1" alt="Test workflow status">
    </a>
    <a href="https://github.com/sulu/sulu/releases" target="_blank">
        <img src="https://img.shields.io/badge/sulu%20compatibility-%3E=2.0-52b6ca.svg" alt="Sulu compatibility">
    </a>
</p>

The SuluAutomationBundle provides a way to manages future tasks which can be scheduled for entities in the Sulu-Admin.
For example schedule the publishing of a page to a specific datetime in the future.

To schedule tasks this bundle uses the [php-task library](https://github.com/php-task/php-task).

## Requirements

* Composer
* PHP `^7.2`
* Sulu `^2.0`
* PHP-Task `^1.3`

For detailed requirements see [composer.json](composer.json).

## Documentation

The Documentation is stored in the
[Resources/doc/](Resources/doc) folder.

## Installation

All the installation instructions are located in the
[documentation](Resources/doc/installation.md).

## License

This bundle is under the MIT license. See the complete license [in the bundle](LICENSE)

## Reporting an issue or a feature request

Issues and feature requests are tracked in the [Github issue tracker](https://github.com/Sulu/SuluAutomationBundle/issues).

When reporting a bug, it may be a good idea to reproduce it in a basic project built using the
[sulu/skeleton](https://github.com/sulu/skeleton) to allow developers of the bundle to reproduce the issue
by simply cloning it and following some steps.
