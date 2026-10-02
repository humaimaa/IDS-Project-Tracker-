<?php

$projects = [];
$names = ['Water Supply Improvement in Khyber District', 'District Road Upgrade (Phase II)', 'Rural Health Centres (Phase I)', 'Secondary Schools Upgradation', 'Urban Flood Protection Scheme', 'Community Learning Centres', 'District Hospital Rehabilitation', 'Rural Access Roads'];
for ($index = 0; $index < 48; $index++) {
    $stage = $index < 12 ? 'Concept' : ($index < 28 ? 'PC-I Development' : 'Implementation');
    $health = $index % 8 === 0 ? 'Delayed' : ($index % 8 === 1 ? 'On hold' : 'On track');
    $projects[] = [
        'name' => $names[$index % 8].($index >= 8 ? ' — Package '.(intdiv($index, 8) + 1) : ''),
        'reference' => 'IDS-2026-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
        'stage' => $stage,
        'health' => $health,
        'partners' => [$index < 20 ? 'ADB' : ($index < 38 ? 'World Bank' : 'Other')],
        'sectors' => [$index < 16 ? 'Education' : ($index < 28 ? 'Health' : ($index < 38 ? 'Transport' : 'Water'))],
        'districts' => [['Khyber', 'Peshawar', 'Kohat', 'Swat'][$index % 4]],
        'officer' => $index % 2 === 0 ? 'Section Officer' : 'Project Director',
        'reasons' => $health === 'Delayed' ? [['Stage review overdue', 'Activity: Cost estimates pending', 'Sub-activity: Site inspection overdue'][intdiv($index, 8) % 3]] : [],
    ];
}

return [
    'projects' => $projects,
    'meetings' => [
        ['name' => 'ADB Project Review', 'date' => '2026-10-05T10:00', 'description' => 'Review of ongoing projects and disbursement status'],
        ['name' => 'PC-I Coordination', 'date' => '2026-10-07T11:30', 'description' => 'Discussion on new PC-I proposals'],
    ],
    'updates' => [
        ['text' => 'PC-I for District Road Upgrade submitted to Planning & Development Department', 'time' => '2 hours ago'],
        ['text' => 'ADB released second tranche for Water Supply Improvement Project', 'time' => '5 hours ago'],
        ['text' => 'Site visit completed for Rural Health Centres (Kohat)', 'time' => '1 day ago'],
        ['text' => 'Revised cost estimates received for Flood Protection Scheme', 'time' => '1 day ago'],
    ],
];
