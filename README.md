![ClusterCockpit banner](https://github.com/ClusterCockpit/ClusterCockpit/wiki/img/ClusterCockpit-banner-small.png)

--------------------------------------------------------------------------------
NOTICE
--------------------------------------------------------------------------------
The PHP Symfony based ClusterCockpit Webfrontend Implementation is deprecated
and is replaced by [cc-backend](https://github.com/ClusterCockpit/cc-backend)!
[Here](https://github.com/ClusterCockpit/ClusterCockpit/wiki/Why-we-switched-from-PHP-Symfony-to-a-Golang-based-solution) are some thoughts on why we switched in case you care.

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


