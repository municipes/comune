# Silfi Triplette

Silfi triplette import terms for Triplette vocabulary from external service.

Silfi Triplette provide drush command: triplette:cron

## Requirements

 * There are no special requirements outside core.

## Installation

 * Install as you would normally install a contributed Drupal module. See:
     https://drupal.org/documentation/install/modules-themes/modules-8
     for further information.

## Configuration

 * Configure user permissions via url /admin/people/permissions#module-silfi_triplette
   or Administration » People » Permissions

   - "Administer silfi_triplette configuration"

     This permission allows the user to alter all Triplette settings and manually import. It should
     therefore only be given to trusted admin roles.

 * Configure the Scheduler global options via /admin/config/system/silfi-triplette
   or Administration » Configuration » System » Silfi Triplette

   - Basic settings: service url and "ente" name.

   - Lightweight Cron: This gives sites admins the granularity to run
     Scheduler's functions only on more frequent crontab jobs than the full
     Drupal cron run.


## Maintainers

Current maintainers:
- [Maurizio Cavalletti](https://www.drupal.org/u/maurizio_akabit) 2023(1.x)-current
