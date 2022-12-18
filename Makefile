NAME = telegram_crypto_monitor_bot

.PHONY: build
build:
	@docker build -t $(NAME) . 
	@docker image save $(NAME) | bzip2 > $(NAME).tar.bz2

.PHONY: copy
copy:
	@scp $(NAME).tar.bz2 tim@10.11.12.252:/home/tim/ 
	@ssh server "docker load < /home/tim/$(NAME).tar.bz2 && rm -f /home/tim/$(NAME).tar.bz2" 

.PHONY: run
run:
	@docker run --restart=always --detach --name $(NAME) $(NAME)
