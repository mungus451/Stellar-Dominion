# README: Stellar Dominion - A PHP Web Game

## 1. Project Overview

Stellar Dominion is a persistent, turn-based, multiplayer strategy game built with PHP and MySQL. Inspired by classic browser-based empire-building games, it allows players to register, manage resources, train a military, and attack other players to plunder credits and gain experience.

The game operates on a server-side cron job that processes "turns" every 10 minutes, ensuring that the game world evolves and players generate resources even while they are offline.

---

## 2. Core Game Mechanics

### The Turn System
- The game progresses in **10-minute turns**.
- Each turn, every player in the game receives:
    - **2 Attack Turns**
    - **1 Untrained Citizen**
    - **Credits** (based on their income calculation)

### Economy & Income
A player's income per turn is calculated with the following formula:
`Income = floor((Base Income + Worker Income) * Wealth Bonus)`
- **Base Income**: 5,000 credits per turn.
- **Worker Income**: 50 credits per Worker unit.
- **Wealth Bonus**: +1% to total income for every point in the "Wealth" stat.

### Unit Training
- Players can train their "Untrained Citizens" into specialized units on the `battle.php` page.
- Each unit has a base credit cost, which is reduced by the player's **Charisma** stat (`-1% cost per point`).
- **Units:**
    - Worker: Generates income.
    - Soldier: Provides Offense Power.
    - Guard: Provides Defense Rating.
    - Sentry: Provides Fortification.
    - Spy: Provides Infiltration.

### Combat System
- Players attack each other from the `attack.php` page, spending 1-10 Attack Turns per attack.
- **Offense Power**: `floor((Soldiers * 10) * (1 + Strength Bonus))`
- **Defense Rating**: `floor((Guards * 10) * (1 + Constitution Bonus))`
- **Outcome**: If the attacker's total damage (a randomized value based on Offense Power) is greater than the defender's, the attack is a victory.
- **Plunder**: On a victory, the attacker steals a percentage of the defender's current credits, scaled by the number of turns used.
- **Experience**: Both players gain XP based on the damage they dealt during the battle.

### Leveling System
- Players gain XP from battles. When enough XP is accumulated, they level up.
- Each level grants the player **1 Proficiency Point**.
- Points can be spent on the `levels.php` page to increase one of five core stats, each providing a +1% bonus per point.
- **Stats:**
    - **Strength**: Increases Offense Power.
    - **Constitution**: Increases Defense Rating.
    - **Wealth**: Increases credit income per turn.
    - **Dexterity**: (Reserved for future Sentry/Spy bonuses).
    - **Charisma**: Reduces the credit cost of training units.
- **Cap**: A player cannot allocate more than 75 points to any single stat.

---

## 3. Project Setup Guide

### Requirements
- A web server running Apache.
- PHP version 7.4 or higher.
- A MySQL or MariaDB database server.

### Step 1: Database Setup
1.  Create a new, empty database in your MySQL server (e.g., `stellar_dominion`).
2.  Create a database user and password with full permissions for that database.
3.  Run the entire `database.sql` script. This will create and configure the necessary `users` and `battle_logs` tables.

### Step 2: File Configuration
1.  Upload all the project's `.php` and `.html` files to your web server directory.
2.  Open `db_config.php` and fill in the `DB_SERVER`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_NAME` constants with your database credentials.

### Step 3: Cron Job Setup
1.  Log into your server via SSH and open the crontab editor:
    ```bash
    crontab -e
    ```
2.  Add the following line to the file, making sure to replace the file path with the correct absolute path to your `cron_job.php` file:
    ```
    */10 * * * * /usr/bin/php /path/to/your/project/cron_job.php
    ```
3.  Save and exit the editor. The game's turn processing will now run automatically every 10 minutes.

---

## 4. File Structure & Purpose

### User Management
-   **`index.html`**: The main landing page for new visitors, containing the login and registration forms in a modal.
-   **`register.php`**: Handles new user creation. It validates input, hashes the password, and inserts the new player into the `users` table with starting resources.
-   **`login.php`**: Authenticates existing users by comparing their submitted password with the stored hash. On success, it creates a PHP session.
-   **`logout.php`**: Destroys the user's session and redirects to `index.html`.

### Core Game Pages
-   **`dashboard.php`**: The main hub for logged-in players. Displays a full overview of resources, stats, and game time. It also processes any overdue turns for the player upon loading.
-   **`battle.php`**: The main training page where players can spend credits and untrained citizens to create specialized units.
-   **`attack.php`**: Displays a list of all other players, their current credit balance, and level. This is where players initiate attacks.
-   **`war_history.php`**: Shows the player's personal attack and defense logs, with links to detailed battle reports.
-   **`battle_report.php`**: Displays the detailed results of a single battle, including damage dealt, credits plundered, and XP gained.
-   **`levels.php`**: The interface where players can spend their earned proficiency points to upgrade their core stats.

### Server-Side Logic
-   **`db_config.php`**: Contains the database credentials and establishes the connection to the MySQL server. It also sets the connection timezone to UTC.
-   **`cron_job.php`**: The core server-side script that processes turns for all players. It calculates and awards resources based on game rules and is designed to be run automatically by the server's cron scheduler.
-   **`train.php`**: Processes the form submission from `battle.php`, validates if the player has enough resources, and updates the database with the newly trained units.
-   **`process_attack.php`**: The main battle logic script. It calculates damage, determines the outcome, transfers credits, awards XP, checks for level-ups, and creates a permanent record in the `battle_logs` table.
-   **`levelup.php`**: Processes the form submission from `levels.php`, validates if the player has enough points, and updates the database with the new stat allocations.

### Database
-   **`database.sql`**: A consolidated SQL script containing all the necessary commands to create and configure the `users` and `battle_logs` tables from scratch.
