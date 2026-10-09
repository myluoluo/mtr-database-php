MTR Database for PHP
====================

MTR Batch processing storing in database with dashboard 

[![Latest Stable Version](https://poser.pugx.org/yidas/mtr-database/v/stable?format=flat-square)](https://packagist.org/packages/yidas/mtr-database)
[![License](https://poser.pugx.org/yidas/mtr-database/license?format=flat-square)](https://packagist.org/packages/yidas/mtr-database)

OUTLINE
-------

- [Demonstration](#demonstration)
- [Introduction](#introduction)
- [Requirements](#requirements)
- [Installation](#installation)
    - [Download](#download) 
    - [Startup](#startup)
- [Usage](#usage)
    - [Launch](#launch)
    - [Purge](#purge) 
    - [Dashboard](#Dashboard)
- [References](#references)

---

DEMONSTRATION
-------------

<img src="https://raw.githubusercontent.com/yidas/mtr-database-php/main/img/demo-dashboard-overview.png" height="260" /><img src="https://raw.githubusercontent.com/yidas/mtr-database-php/main/img/demo-dashboard-table-details.png" height="260" />

---

INTRODUCTION
------------

![Basic Flow](https://www.plantuml.com/plantuml/proxy?src=https://raw.githubusercontent.com/yidas/mtr-database-php/main/img/architecture-diagram.plantuml)

---

REQUIREMENTS
------------
This library requires the following:

- MTR library (CLI) 0.9+
- PHP 5.4.0+\|7.0+

---

INSTALLATION
------------

### Download

#### Composer Installation

Using Composer to install is the easiest way with auto-installer:

```shell
composer create-project --prefer-dist yidas/mtr-database
```

#### Wget Installation

You could see [Release](https://github.com/yidas/mtr-database-php/releases) for picking up the package with version, for example:
    
```shell
$ wget https://github.com/yidas/mtr-database-php/archive/master.tar.gz -O mtr-database-phpi.tar.gz
```

After download, uncompress the package:

```shell
$ tar -zxvf mtr-database-php.tar.gz
```

### Startup

#### Database Setup

After the download, you could start to set up the `config.inc.php` with your database connection:

```php
...
    'database' => [
        'host' => '',
        'driver'    => 'mysql',
        'database'  => 'mtr_database',
        'username'  => '',
        'password'  => '',
        'table' => 'records',
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
    ],
...
```

Then run `install.php` which will help you to install the database & table:

```shell
$ php install.php
Installation completed
```

#### Agent Launch

After the installation, enjoy to run or set `launch.php` with your prefered arguments in crontab:

```shell
$php launch.php
Process success
```

Set crontab into `/etc/cron.d/mtr-database`:

```shell
# Launch and record MTR every 10 miniutes by default
*/10 * * * * root php /var/www/html/mtr-database/launch.php >/dev/null 2>&1

# Purge data before 90 days by default (Optional)
00 00 * * * root php /var/www/html/mtr-database/purge.php >/dev/null 2>&1
```

> The default batch period is configured to be 10 minutes, so we can set the batch to be executed every 10 minutes.

---

USAGE
-----

### Launch

The configuration file `config.inc.php` allows you to set the default settings for MTR launching. However, you can also specify parameters immediately in the command to be run.

#### Host

Host parameter allows you to specify the target host to be tracked with `-h --host` parameter:

```shell
php launch.php --host="yourhost.local"
```

#### Period

Period argument allocate the number of minutes between crontab intervals with `-p --period` parameter:

```shell
# Launch and record MTR every 5 miniutes
*/5 * * * * root php /var/www/html/mtr-database/launch.php --period=5 >/dev/null 2>&1
```

In addition, Count argument will distribute the sending count between the interval according to the Period setting with `-c --report-cycles`  parameter:

```shell
# Launch and record MTR every 1 miniutes, and each report will send 10 count (send every 6 seconds)
*/1 * * * * root php /var/www/html/mtr-database/launch.php --period=1 --report-cycles=10 >/dev/null 2>&1
```

#### TCP

TCP argument allows you to use MTR TCP mode with specified port, `-T --tcp` for enabling TCP mode and `-P --port` for setting port:

```shell
php launch.php --tcp -port=443
```

#### Category

Category allows to categorize each monitor command and supports filtering from the dashboard, which also can be achieved by using the `--category` parameter:

```shell
php launch.php --category="Monitor-A1"
```

### Purge

Running `purge.php` will delete old records older than the given number of days. You can use the `-d --days` parameter to set with (default is 90 days):

```shell
00 00 * * * root php /var/www/html/mtr-database/purge.php --days=30 >/dev/null 2>&1
```

### Dashboard

The endpoint of MTR Dashboard is `/index.php`. It is disabled by default. To enable it, set `dashboard.enable` to `true` in `config.inc.php` and provide both authentication credentials.

Set `MTR_DASHBOARD_USERNAME` and `MTR_DASHBOARD_PASSWORD` in the PHP web process environment or through your secret manager. The configuration reads these variables without storing credentials in source control:

```php
...
    'dashboard' => [
        'enable' => true,
        'username' => (string) getenv('MTR_DASHBOARD_USERNAME'),
        'password' => (string) getenv('MTR_DASHBOARD_PASSWORD'),
        'categories' => [''],   // Category list for selection
    ],
...
```

Both the HTML page and `?route=get` data endpoint require HTTP Basic authentication. Disabled dashboards return HTTP 403; missing, empty or whitespace-only credentials return HTTP 503; missing or incorrect request credentials return HTTP 401 with a `WWW-Authenticate` challenge. Existing enabled installations with blank credentials must configure authentication before they can be accessed. Serve the dashboard over HTTPS.

The category selector and date window only filter records; they do not provide access control. Selecting `(Category)` queries all categories, and the default date window covers the last 24 hours.

Run the authentication regression tests with PHP 5.5+ (no database is required):

```shell
php tests/dashboard-auth.php
```

The tests use a temporary local HTTP server and synthetic credentials. Denied data requests use an empty database configuration to verify that authentication stops processing before any database access. They do not connect to a production database.

---

REFERENCES
----------

- [MTR - Github](https://github.com/traviscross/mtr)



