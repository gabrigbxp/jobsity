# Requirements

- Composer: https://getcomposer.org/download/
- Symfony executable: https://symfony.com/download
- PHP 7.4 or higher
- A [Doctrine-compliant](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/platforms.html) database

# How to use

## Installation

1. Clone the repository
2. Execute `composer install` 
3. Set connection string in `.env` file
4. Once is installed, execute the server with `composer start` 
5. Prepare database with `php bin/console doctrine:migrations:migrate`

## Bot commands

1.  register a new user with one line of 4 segments `register username@mailserver.dom password ABC`, ex: `register scocozza.gabriel@gmail.com 1234 ARS`
2. login with `login username@mailserver.dom password`, ex: `login scocozza.gabriel@gmail.com 1234`
3.  Make a deposit with `deposit # ABC` ex `deposit 1 usd`
4.  Make a withdraw with `withdraw # ABC` ex `withdraw 1 usd`
5.  Perform currency exchange with `# ABC to DEF`, ex: `1 usd to ars`
6.  Set a new currency with `set currency ABC`, ex: `set currency ars`

