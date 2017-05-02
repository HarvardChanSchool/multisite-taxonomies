#!/bin/bash

docker-compose run wpcli core install --url="localhost:8080" --title="WordPress" --admin_user="admin" --admin_password="admin" --admin_email="admin@email.com"
docker-compose run wpcli core multisite-convert
docker-compose run wpcli plugin activate multisite-taxonomies
composer install
