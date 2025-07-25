<?php
/**
 * game_data.php
 *
 * Central repository for all static game data, such as upgrade trees.
 * This makes balancing and adding new content much easier.
 */

$upgrades = [
    'fortifications' => [
        'title' => 'Empire Foundations',
        'db_column' => 'fortification_level',
        'levels' => [
            1 => ['name' => 'Foundation Outpost', 'cost' => 50000, 'level_req' => 1, 'bonuses' => [], 'description' => 'Establishes a basic command structure.'],
            2 => ['name' => 'Planetary Base', 'cost' => 250000, 'level_req' => 5, 'bonuses' => [], 'description' => 'A fortified base of operations.'],
            3 => ['name' => 'Orbital Station', 'cost' => 1000000, 'level_req' => 10, 'bonuses' => [], 'description' => 'Extends your influence into the local system.'],
            4 => ['name' => 'Star Fortress', 'cost' => 5000000, 'level_req' => 15, 'bonuses' => [], 'description' => 'A bastion of your military and economic power.'],
            5 => ['name' => 'Galactic Citadel', 'cost' => 20000000, 'level_req' => 20, 'bonuses' => [], 'description' => 'The unshakable heart of your growing empire.'],
        ]
    ],
    'offense' => [
        'title' => 'Offense Upgrades',
        'db_column' => 'offense_upgrade_level',
        'levels' => [
            1 => ['name' => 'Enhanced Targeting I', 'cost' => 150000, 'fort_req' => 1, 'bonuses' => ['offense' => 5], 'description' => '+5% Offense Power.'],
            2 => ['name' => 'Enhanced Targeting II', 'cost' => 750000, 'fort_req' => 2, 'bonuses' => ['offense' => 5], 'description' => '+5% Offense Power (Total: 10%).'],
            3 => ['name' => 'Enhanced Targeting III', 'cost' => 3000000, 'fort_req' => 3, 'bonuses' => ['offense' => 10], 'description' => '+10% Offense Power (Total: 20%).'],
        ]
    ],
    'defense' => [
        'title' => 'Defense Upgrades',
        'db_column' => 'defense_upgrade_level',
        'levels' => [
            1 => ['name' => 'Improved Armor I', 'cost' => 150000, 'fort_req' => 1, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating.'],
            2 => ['name' => 'Improved Armor II', 'cost' => 750000, 'fort_req' => 2, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating (Total: 10%).'],
            3 => ['name' => 'Improved Armor III', 'cost' => 3000000, 'fort_req' => 3, 'bonuses' => ['defense' => 10], 'description' => '+10% Defense Rating (Total: 20%).'],
        ]
    ],
    'economy' => [
        'title' => 'Economic Upgrades',
        'db_column' => 'economy_upgrade_level',
        'levels' => [
            1 => ['name' => 'Trade Hub I', 'cost' => 200000, 'fort_req' => 1, 'bonuses' => ['income' => 5], 'description' => '+5% to all credit income.'],
            2 => ['name' => 'Trade Hub II', 'cost' => 1000000, 'fort_req' => 2, 'bonuses' => ['income' => 5], 'description' => '+5% credit income (Total: 10%).'],
            3 => ['name' => 'Trade Hub III', 'cost' => 4000000, 'fort_req' => 3, 'bonuses' => ['income' => 10], 'description' => '+10% credit income (Total: 20%).'],
        ]
    ],
    'population' => [
        'title' => 'Population Upgrades',
        'db_column' => 'population_level',
        'levels' => [
            1 => ['name' => 'Habitation Pods I', 'cost' => 300000, 'fort_req' => 1, 'bonuses' => ['citizens' => 1], 'description' => '+1 citizen per turn (Total: 2).'],
            2 => ['name' => 'Habitation Pods II', 'cost' => 1500000, 'fort_req' => 2, 'bonuses' => ['citizens' => 1], 'description' => '+1 citizen per turn (Total: 3).'],
            3 => ['name' => 'Habitation Pods III', 'cost' => 6000000, 'fort_req' => 4, 'bonuses' => ['citizens' => 2], 'description' => '+2 citizens per turn (Total: 5).'],
        ]
    ],
];
?>