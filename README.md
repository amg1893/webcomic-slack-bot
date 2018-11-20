# Sunrise Slack Bot
## Introduction
Welcome to the Sunrise Slack Bot repository! This is the bot that the Sunrise team uses to automate some tasks before every PR.

The first task this bot was made for is to run PHPUnit in the code, but soon™ enough, its functionality can be extended.

## Installation
```
composer install
```
Then, you have to modify the .env file created with your credentials.

For the configuration of the bot, you need to provide the config.local.json as you have in your application, along the channel ID of Slack and tokens for Slack and BitBucket to connect to both services.

## Functionality
This bot downloads the code and validates it against the unit tests developed.