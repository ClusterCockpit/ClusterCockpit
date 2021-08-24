![ClusterCockpit banner](https://github.com/ClusterCockpit/ClusterCockpit/wiki/img/ClusterCockpit-banner-small.png)

--------------------------------------------------------------------------------
NOTICE
--------------------------------------------------------------------------------

ClusterCockpit is still in testing phase. We are working hard to get an BETA release soon.
If you want to help develop ClusterCockpit you may want to take a look at [open issues](https://github.com/ClusterCockpit/ClusterCockpit/issues?q=is%3Aopen+is%3Aissue).
A good starting point about the software design of ClusterCockpit are the Wiki
pages about the [overall structure](https://github.com/ClusterCockpit/ClusterCockpit/wiki/DEV-Software-structure)
and [naming conventions](https://github.com/ClusterCockpit/ClusterCockpit/wiki/DEV-Conventions)
used.

--------------------------------------------------------------------------------
Introduction
--------------------------------------------------------------------------------

This is a web frontend for job specific performance monitoring. It is based on
the [Symfony 5](https://symfony.com) PHP Framework. It uses
[Bootstrap 5](http://getbootstrap.com) for layout and styling,
[API Platform](https://api-platform.com/) for REST APIs,
[OverblogGraphQLBundle](https://github.com/overblog/GraphQLBundle) for GraphQL APIs,  and
[Svelte](https://svelte.dev/) for the frontend UI.

## Dependencies

To install and use ClusterCockpit the following dependencies have to be fulfilled:
- PHP 8.0 or newer
- MySQL or MariaDB
- [Composer](https://getcomposer.org) - PHP package manager
- [Yarn](https://yarnpkg.com/) - Node package manager
- [Symfony CLI](https://symfony.com/download) - Symfony command line tool
- Optional: Apache or Nginx web server
- Optional: InfluxDB time series database (both V1 or V2 are supported)
- Optional: Redis caching server

We strongly recommend to use this [Docker compose setup](https://github.com/ClusterCockpit/cc-docker) for testing ClusterCockpit or for development.
This docker setup also includes downloadable monitoring data.

## Installation and Setup

Please refer to the [Wiki](https://github.com/ClusterCockpit/ClusterCockpit/wiki) for documentation.


