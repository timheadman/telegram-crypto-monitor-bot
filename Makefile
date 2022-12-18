.PHONY: build
build:
	@docker build -t crypto_daily_news_bot . 
	@docker image save crypto_daily_news_bot | bzip2 > crypto_daily_news_bot.tar.bz2

.PHONY: copy
copy:
	@scp crypto_daily_news_bot.tar.bz2 tim@10.11.12.252:/home/tim/projects/ 
	@ssh server "docker load < /home/tim/projects/crypto_daily_news_bot.tar.bz2"
