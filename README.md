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
the [Symfony 4](https://symfony.com) PHP Framework. The application uses
[Bootstrap 4](http://getbootstrap.com) for layout and styling,
[DataTables](https://datatables.net) for interactive Ajax tables and
[plotly.js](https://plot.ly/javascript/) for graph generation.

--------------------------------------------------------------------------------
Dependencies
--------------------------------------------------------------------------------

To install and use ClusterCockpit you need the following dependencies:
- PHP 7.2 or newer
- MySQL or MariaDB
- [Composer](https://getcomposer.org) - PHP package manager
- Optional: Apache web server for production use
- Optional: [InfluxDB](https://docs.influxdata.com/influxdb/v1.7/introduction/getting-started/) time series database
- Optional: [Redis](https://redis.io/) caching backend

--------------------------------------------------------------------------------
Configure PHP
--------------------------------------------------------------------------------

The default PHP memory limit is too low for most Symfony applications. You may
also need to enable some PHP extensions. Please note that these settings are
required to run Symfony at all. Please consult the Symfony documentation for
performance optimized PHP settings in production mode.

Please check and if required set or uncomment the following setting in
`php.ini` (Ususally located in `/etc/php/php.ini`):

```
memory_limit = 1G
```

Symfony requires the following PHP extension which are usually included in the
standard PHP installation: `curl, iconv, ldap, mysqli, pdo_mysql`. Please refer
to a Google search how to install PHP extensions on your OS if an extension is missing.

--------------------------------------------------------------------------------
Setup ClusterCockpit
--------------------------------------------------------------------------------

Symfony applications are operated in so called environments.  The `dev`
environment is for development and testing and is usually used together with
the builtin PHP web server listening on a local port. For production the
environment should be switched from `dev` to `prod`. This enables
performance optimisations and is usually used together with a web server, as
e.g. Apache. Below instructions apply to a development setup and are intended
to be used by someone developing or testing ClusterCockpit. Please refer to the
Wiki if you want to install ClusterCockpit in a production environment.

## Preparation

1. Clone repository

You may use 
```
$ git clone git@github.com:ClusterCockpit/ClusterCockpit.git ClusterCockpit
```
if you have a github account or you can use https:

```
$ git clone https://github.com/ClusterCockpit/ClusterCockpit.git ClusterCockpit
```

2. Install required PHP version

You need at least PHP 7.2 or higher. Please refer to
Google for how to install PHP on your operating system.

3. Setup database backends

At the moment all development was done using  MySQL. MariaDB or
PostgresSQL where not tested but should also work.

Create symfony database user and ClusterCockpit database:

```
$ mysql -u root  -p
$mysql> CREATE USER 'username'@'localhost' IDENTIFIED BY 'mypass';
$mysql> CREATE DATABASE ClusterCockpit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
$mysql> GRANT ALL PRIVILEGES ON ClusterCockpit.* TO 'username'@'localhost'; 
$mysql> quit
```

We recommend InfluxDB as database backend for the metric data. The following
are optimizes settings in `influxdb.yml` to start with. Still due to the
complexity of this topic for optimized settings for mySQL as well as influxDB
refer to documentation on the web.

```
[data]
   index-version = "tsi1"
   wal-fsync-delay = "100ms"
   cache-max-memory-size = "2g"
   cache-snapshot-memory-size = "1g"

[http]
   auth-enabled = true 
   reporting-disabled = true
   log-enabled = false

[data]
   query-log-enabled = false

[continuous_queries]
   log-enabled = false
   run-interval = "1m"
```

The following steps need to be executed to setup InfluxDB. Enter influx shell:

```
influx   -precision=s
```

Create admin user for influxDB and ClusterCockpit metric database:

```
> CREATE USER admin WITH PASSWORD 'pass' WITH ALL PRIVILEGES
> CREATE DATABASE ClusterCockpit WITH DURATION 540d NAME data
```

On subsequent influx shell sessions you have to authenticate, for example by
entering the auth command in the influx shell. To create more users enter for
example:

```
> auth
username: admin
password:
> CREATE USER "symfony" WITH PASSWORD 'password'
> GRANT READ ON "ClusterCockpit" TO "symfony"
> CREATE USER "telegraf" WITH PASSWORD 'password'
> GRANT WRITE ON "ClusterCockpit" TO "telegraf"

```

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

3. Start up local web server

To start the web server with integrated Symfony profiler console run:
```
$ php bin/console server:run
```

The web application can be accessed with any web browser on localhost port 8000.

A log file is available in var/log/dev.log .

## Setup

Open the URL `http://localhost:8000` in a web browser. Click on the
JobMonitoring button and login with the credentials of your admin user.

Please refer to the [Wiki pages](https://github.com/ClusterCockpit/ClusterCockpit/wiki) how to setup a
cluster and other required data sources.


