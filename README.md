# Awasqa

## Requirements

- Docker [installed](https://docs.docker.com/install/)
- PHP and Composer installed locally. If not, you can use them in containers provided, prefixing all Composer commands below with `docker compose run composer <command>`

## Run locally

1. Copy `.env.example` to `.env`
2. Run `composer install` or `docker run composer install` to install PHP dependencies
3. Run `npm install` in `web/app/themes` to instal JavaScript dependencies
4. Build frontend assets with `npm run watch` in `web/app/themes`
5. Start the server with `docker compose up`
6. Import data with `mysql -h 127.0.0.1 -P 3308 -uroot -ppassword awasqa < awasqa.local.sql`
7. The WordPress admin password can be reset with `docker compose run wordpress wp user update admin --user_pass="PASSWORD"`
8. The site should be at [http://localhost:8082](http://localhost:8082)

## Deployment

The site is deployed on Kinsta. Update the site by using ssh to connect to the server, then
`cd public; git pull`. The ssh credentials can be found on the Kinsta dashboard.