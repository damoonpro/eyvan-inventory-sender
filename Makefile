up:
	@docker compose up -d
down:
	@docker compose down
ps:
	@docker ps
build:
	@docker compose build
build-c:
	@docker compose build --no-cache
upf:
	@docker compose up --force-recreate -d
bash:
	@docker exec -it inventory-sender bash
