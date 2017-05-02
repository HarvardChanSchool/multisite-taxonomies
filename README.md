# Multisite Taxonomies
## A WordPress plugin
Multisite Taxonomies brings the ability to register custom taxonomies, accessible on an entire multisite network, to WordPress.

## Coding standards
We follow (WordPress Coding Standards)[https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards] and enforce them using PHP Code Sniffer.

## Local dev environement
The plugin comes with a "ready to code in 5 minutes" local dev environement. This is totally optional and you can use you own environement.

### Dependencies:
- (Docker)[https://docs.docker.com/engine/installation/] (with Docker Compose)
- (Composer)[https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx] (globally installed)

### How to get started?
- `$ git clone git@github.com:HarvardChanSchool/multisite-taxonomies.git`
- `$ cd multisite-taxonomies`
- `$ docker-compose up -d`
- Wait 15/30 seconds for Docker to finish initialising in the backgound
- If this is the very first time you launch the project you will also need: `$ chmod +x install.sh && ./install.sh`.
- You can now access your dev environment at [http://localhost:8080]() and admin at [http://localhost:8080/wp-admin/]() (user: admin password: admin).
