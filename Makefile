NAME = telegram-crypto-monitor-bot
RUNNER_NAME = run-$(NAME)-container.sh

.PHONY: build
build:
	@docker build -t $(NAME) . 
	docker image save $(NAME) | bzip2 > $(NAME).tar.bz2

.PHONY: copy
copy:
	echo "docker run --restart=always --detach --name $(NAME) $(NAME)" > $(RUNNER_NAME) 
	chmod +x $(RUNNER_NAME) 
	scp -v $(NAME).tar.bz2 $(RUNNER_NAME) tim@10.11.12.252:/home/tim/ 
	ssh server "docker load < /home/tim/$(NAME).tar.bz2 && rm -f /home/tim/$(NAME).tar.bz2" 
	rm -vf $(NAME).tar.bz2 $(RUNNER_NAME)
