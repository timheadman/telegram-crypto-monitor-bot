.PHONY: build
build:
	@docker build -t crypto_monitor_bot . 
	@docker image save crypto_monitor_bot | bzip2 > crypto_monitor_bot.tar.bz2

.PHONY: copy
copy:
	@scp crypto_monitor_bot.tar.bz2 tim@10.11.12.252:/home/tim/ 
	@ssh server "docker load < /home/tim/crypto_monitor_bot.tar.bz2 && rm -f /home/tim/crypto_monitor_bot.tar.bz2" 

.PHONY: run
run:
	docker run --restart=always --detach --name crypto_monitor_bot crypto_monitor_bot