<p align="center" padding-top="100">
	<img src="https://ledgerleap.com/web/images/devxdao-logo.png" width="400">
</p>

## DevxDao Grant Portal Backend

The DevxDao's grant and voting associates portal hosted at http://portal.devxdao.com

This is the backend repo of the portal. Frontends for this backend API are listed below.

Main portal: https://github.com/ledgerleapllc/devxdao-frontend

Project Management portal: https://github.com/ledgerleapllc/devxdao-pm

Compliance portal: https://github.com/ledgerleapllc/devxdao-compliance

### Prerequisites

Relies on Laravel PHP. You can find Laravel's documentation here https://github.com/laravel/laravel

### Install and Deploy

Relies on Laravel PHP, and Mysql if hosting locally

```bash
sudo apt -y install apache2
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod ssl
sudo apt -y install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update
sudo apt-get install -y php7.4
sudo apt-get install -y php7.4-{bcmath,bz2,intl,gd,mbstring,mysql,zip,common,curl,xml}
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
```

Setup the repo according to our VHOST path. Note, the actual VHOST path in this case should be set to **/var/www/devxdao-backend/public**

```bash
cd /var/www/
git clone https://github.com/ledgerleapllc/devxdao-backend
cd devxdao-backend
```

Install packages and setup environment

```bash
composer install
composer update
cp .env.example .env
```

Notes on the .env variables.

**X_CMC_PRO_API_KEY** is Coin Market Cap API key you will need to provide.

**STRIPE_SK_LIVE** and **STRIPE_SK_TEST** are found in your Stripe payment processor account.

**INSTALL_ROUTE_ENABLED** is purposed for initial installation of the portal. Set to 1 to enable /api/install path. This will install admin accounts and role information into the DB. Set to 0 to disable this service.

**KYC_KANGAROO_URL** is the URL to the KYC Kangaroo portal API for KYC processing.

**KYC_KANGAROO_TOKEN** is the API token to the KYC Kangaroo portal API for KYC processing.

**EXTERNAL_API_TOKEN** is the API token for retrieving information about user IDs, emails, and forum nicknames purposed for the RFP team to match VA info.

After adjusting .env with your variables, run Artisan to finish setup

```bash
php artisan key:generate
php artisan migrate
php artisan passport:install
php artisan config:clear
php artisan route:clear
php artisan cache:clear
(crontab -l 2>>/dev/null; echo "* * * * * cd /var/www/devxdao-backend && php artisan schedule:run >> /dev/null 2>&1") | crontab -
```

You may also have to authorize Laravel to write to the storage directory

```bash
sudo chown -R www-data:www-data storage/
```

### Default user/passwords

Default user/admin logins are created in the portal during initial install. 
All the default logins that are created on install are given random hash passwords that can be found printed in your laravel log file that will look something like this:

```
[2021-11-30 18:13:51] production.INFO:  Created <type> admin
[2021-11-30 18:13:51] production.INFO:  Email: <email>
[2021-11-30 18:13:51] production.INFO:  Password: <random_password>
```

### Testing

We use PHPUnit for unit testing of the portal's api endpoints. In order to run the test suite, you will need to build composer dependencies and run PHP Artisan's commands, ensuring a proper backend build. Run **composer run-script --dev test** to run the unit tests and see output on th CLI. Run this command at the root of the repo directory.

```bash
composer run-script --dev test
```