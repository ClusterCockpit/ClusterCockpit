--------------------------------------------------------------------------------
NOTICE
--------------------------------------------------------------------------------

ClusterCockpit is currently still work in progress and not yet ready for
general production use. One main component which is not yet finalized is
a generic way to map to metric lists to different profling database backends. If you want to help develop ClusterCockpit you
may want to take a look at [open issues](https://github.com/ClusterCockpit/ClusterCockpit/issues?q=is%3Aopen+is%3Aissue).

This does not mean ClusterCockpit cannot be used, yet. If you are brave and
accept that you may need to migrate the metric database in the future you can
already install and test ClusterCockpit helping to make it a mature and robust
solution for job-specific performance monitoring in HPC environments.

--------------------------------------------------------------------------------
Introduction
--------------------------------------------------------------------------------

This is a web frontend for job specific performance monitoring. It is based on
the [Symfony](https://symfony.com) PHP Framework. The application uses
[Bootstrap 4](http://getbootstrap.com) for layout and styling,
[DataTables](https://datatables.net) for interactive Ajax tables and
[plotly.js](https://plot.ly/javascript/) for graph generation.

--------------------------------------------------------------------------------
Dependencies
--------------------------------------------------------------------------------

To install and use ClusterCockpit you need the following packages:
- PHP 7.1 or newer
- MySQL 5.7
- [Composer](https://getcomposer.org) - PHP package manager

On Ubuntu systems you usually need an additional
[repository](https://launchpad.net/~ondrej/+archive/ubuntu/php) to install newer
PHP version. ClusterCockpit can be installed on any operating system where PHP
is available.

--------------------------------------------------------------------------------
Setup project
--------------------------------------------------------------------------------

Symfony applications are operated in so called environments.  The `dev`
environment is for development and testing and is usually used together with
the builtin PHP web server listening on a local port. For production the
environment is should be switched from `dev` to `prod`. This enables
performance optimisations and is usually used together with a web server, as
e.g. Apache. Below instructions apply to a development setup and are intended
to be used by someone developing or testing ClusterCockpit. Please refer to the
Wiki if you want to install ClusterCockpit in a production environment.

1. Clone repository
```
$ git clone git@github.com:ClusterCockpit/ClusterCockpit.git ClusterCockpit
```

2. Install required PHP version on Ubuntu

You need at least PHP 7.1. On Ubuntu 16.04 this requires to add an additional repository:
```
$ add-apt-repository ppa:ondrej/php
$ apt-get update
$ apt-get install php7.1 php7.1-xml php7.1-mysql
```

3. Setup MySQL

At the moment all development was done using a MySQL server. MariaDB or
PostgresSQL where not tested but should also work. On Ubuntu you need to
install the following packages to install MySQL:

```
$ apt-get install mysql-server mysql-client
```

Create symfony database user and database:

```
$ mysql -u root  -p
$mysql> CREATE USER 'username'@'localhost' IDENTIFIED BY 'mypass';
$mysql> CREATE DATABASE ClusterCockpit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
$mysql> GRANT ALL PRIVILEGES ON ClusterCockpit.* TO 'username'@'localhost'; 
$mysql> quit
```

4. Install Symfony packages

It is recommended to install a recent composer locally as described [here](https://getcomposer.org/download/).
All packages will be installed locally in the Symfony project tree.

```
$ cd ClusterCockpit
$ composer install
```

5. Configure Symfony access to MySQL:

Everything is currently in one database. Symfony uses the
[Doctrine](https://www.doctrine-project.org) ORM for mapping PHP classes on
database tables. Database access for Doctrine is configured in the local only
.env file. This file is not committed.

To configure mysql credentials open the existing .env file in you project root and add the following line (enter above username and password for the placeholders):

```
DATABASE_URL=mysql://<username>:<mypass>@127.0.0.1:3306/ClusterCockpit

```

Please contact me to get recent test DB dumps. You can import the sql dumps with:

```
$ mysql -h localhost -u <username> -p  ClusterCockpit < dump.sql
```

6. Sanity checks

Check if database is setup correctly:
```
$ php bin/console doctrine:schema:validate
```

The common way to update a database schema is to adapt the according entity PHP classes and then do:
```
$ php bin/console doctrine:schema:update --dump-sql
$ php bin/console doctrine:schema:update --force
```

You can get a list of all configured routes (URLs) with:
```
$ php bin/console debug:router
```

7. Start up local web server:

To start the web server with the integrated Symfony profiler console run:
```
$ php bin/console server:run
```

The web application can be accessed with any web browser on localhost port 8000.

A log file is available in var/log/dev.log .

8. Maintenance:

To update PHP packages in project run:
```
$ composer update
```
Please do not push the composer lock files to git.



