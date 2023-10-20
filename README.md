# ACS Manager Service

> The [AMRC Connectivity Stack (ACS)](https://github.com/AMRC-FactoryPlus/amrc-connectivity-stack) is an open-source implementation of the AMRC's [Factory+ Framework](https://factoryplus.app.amrc.co.uk).

This `acs-manager` service satisfies the **Manager** component of the Factory+ framework and provides centralised management of the Sparkplug namespace, configuration of device connections and ensures conformance to Schemas. It also provides a user interface to interact with the Files service.

For more information about the Manager component of Factory+ see the [specification](https://factoryplus.app.amrc.co.uk) or for an example of how to deploy this service see the [AMRC Connectivity Stack repository](https://github.com/AMRC-FactoryPlus/amrc-connectivity-stack).

## Local Development

The ACS Manager is based on Laravel 9 and VueJS 2, and therefore a local PHP environment must be configured for local development. See the [Laravel Documentation](https://laravel.com/) for more information on getting started with Laravel.

### Prerequisites
- A local PHP environment like [Laravel Valet](https://laravel.com/docs/10.x/valet) with the KRB5 extension installed
- A local instance of MySQL
- Docker
- Accessible Identity, Authentication & Config Store Factory+ services

### Getting Started
- Copy `.env.example` to `.env` and replace the variables as appropriate
- Copy `.env.example` to `.env.testing` and replace the variables as appropriate
- Create a `krb5.conf` file in the root of the project containing the kerberos config for your domain
- Create a `k3s.yaml` file in the root of the project containing your kubeconfig for your Kubernetes cluster
- Set the `AUTH_SERVICE_URL`, `CONFIGDB_SERVICE_URL`, `FILE_SERVICE_ENDPOINT` and `CMDESC_SERVICE_ENDPOINT` values in `.env` to values for your environment
- Run `composer install` to install all PHP dependencies
- Run `php artisan key:generate` to set the application key and copy the new `APP_KEY` value from `.env` to `.env.testing`
- Edit and run `./get-keytab.sh` to generate a keytab file for the application to use for authentication. Set the `KEYTAB_TEMP_FILE` environment variable in `.env` to the output of this script
- Run `php artisan passport:keys` to create the encryption keys for API authentication 
- Run `yarn` to install frontend dependencies 
- Run `yarn dev` to launch the frontend server 
- Run `sail artisan schema:import` to import the Schema list from Github
- Run `./vendor/bin/sail up` to start the development environment
- Run `sail exec acs-manager.test php artisan migrate` to run the database migrations
- Head to `localhost` in your browser to view the application
- Log in using your credentials from your Identity provider
- Update the `adminstrator` field on your user in the `users` table to `true`
