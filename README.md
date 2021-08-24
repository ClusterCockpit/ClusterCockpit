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
- Optional: [InfluxDB](https://docs.influxdata.com/influxdb/v1.7/introduction/getting-started/) time series database

We strongly recommend to use this [Docker compose setup](https://github.com/ClusterCockpit/cc-docker) for having a look on ClusterCockpit or development.
This docker setup already includes the option to download fake monitoring data.

Please refer to the [Wiki](https://github.com/ClusterCockpit/ClusterCockpit/wiki) for documentation.


The default caching backend is file system based cache. For production the use
of [Redis](https://redis.io) as caching backend is recommended. Redis provides
a simpler usage if using Apache as web server, provides a slighly better
performance compared to a SSD based files system cache and is more scalable for
heavy load. To enable redis as caching backend uncomment the following lines in
`config/packages/framework.yaml`:

```
cache:
    app: cache.adapter.redis
```

4. Configure Symfony access to MySQL and InfluxDB:

Symfony uses the [Doctrine](https://www.doctrine-project.org) ORM for mapping
PHP classes on  database tables. Database access for Doctrine is configured in
the local only .env file. This file is not committed. You first need to copy
the .env.dist file to .env and adopt it to your needs.

To configure database backend credentials open the .env file in your project
root and add the following lines (enter above username and password for the
placeholders):

```
DATABASE_URL=mysql://<username>:<password>@127.0.0.1:3306/ClusterCockpit
INFLUXDB_URL=influxdb://<username>:<password>@127.0.0.1:8086/ClusterCockpit
```

## Initialization

1. Setup ClusterCockpit:

It is recommended to install a recent composer locally as described
[here](https://getcomposer.org/download/). All packages will be installed
locally in the ClusterCockpit project tree.

Install Symfony packages:

```
$ cd ClusterCockpit
$ composer install
```

First create the database schema with:

```
$ php bin/console doctrine:schema:update --force
```

Use the following command to initialize database and create an admin user:

```
$ php bin/console  app:init
```

2. Sanity checks

Check if database is setup correctly:
```
$ php bin/console doctrine:schema:validate
```

You can get a list of all configured routes (URLs) with:
```
$ php bin/console debug:router
```

3. Install and build frontend assets:
```
$ yarn install
```
Build assets once:
```
$ yarn encore dev
```

4. Start up local web server

To start the web server with integrated Symfony profiler console run:
```
$ symfony server:start --no-tls
```

The web application can be accessed with any web browser on localhost port 8000.

A log file is available in var/log/dev.log .

## Setup

Open the URL `http://localhost:8000` in a web browser. Click on the
JobMonitoring button and login with the credentials of your admin user.

Please refer to the [Wiki pages](https://github.com/ClusterCockpit/ClusterCockpit/wiki) how to setup a
cluster and other required data sources.
