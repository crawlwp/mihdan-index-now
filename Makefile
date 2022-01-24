jjj:
	@ echo "There is no default task"

## Development environment (dev-env)

### Setup
dev-env--up:
	make wp-core-download
	make dev-env--download
	cd ./custom/dev-env && make up
	@ echo "\nWaiting for mysql..."
	sleep 5
	make dev-env--install

wp-core-download:
	rm -rf ./custom/wp-core
	git clone --depth=1 --branch=5.8.3 git@github.com:WordPress/WordPress.git ./custom/wp-core
	rm -rf ./custom/wp-core/.git

dev-env--download:
	rm -fr ./custom/dev-env && \
	mkdir -p ./custom/dev-env && \
	cd ./custom/dev-env && \
	git clone -b 5.4.42 --depth=1 -- git@github.com:wodby/docker4wordpress.git . && \
	rm ./docker-compose.override.yml && \
	cp ../../tools/dev-env/docker-compose.yml . && \
	cp ../../tools/dev-env/.env . && \
	cp ../../tools/dev-env/wp-config.php ../wp-core/

dev-env--install:
	cd ./custom/dev-env && \
	make wp 'core install --url="http://in.docker.local:8000/" --title="Dev site" --admin_user="admin" --admin_password="admin" --admin_email="admin@docker.local" --skip-email' && \
	make wp 'plugin activate mihdan-index-now' && \
	make wp "rewrite structure '/%postname%/'" && \
	\
	docker-compose exec mariadb mysql -uroot -ppassword -e "create database wordpress_test;" && \
	docker-compose exec mariadb mysql -uroot -ppassword -e "GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wordpress'@'%';" && \
	docker-compose exec test_php wp core install --url="http://test.in.docker.local:8000/" --title="Testing site" --admin_user="admin" --admin_password="admin" --admin_email="admin@docker.local" --skip-email && \
	docker-compose exec test_php wp plugin activate mihdan-index-now && \
	docker-compose exec test_php wp rewrite structure '/%postname%/'

### Regular commands
dev-env--start:
	cd ./custom/dev-env && make start

dev-env--stop:
	cd ./custom/dev-env && make stop

dev-env--prune:
	cd ./custom/dev-env && make prune

dev-env--restart:
	cd ./custom/dev-env && make stop
	cd ./custom/dev-env && make start


dev-env--shell:
	cd ./custom/dev-env && make shell
