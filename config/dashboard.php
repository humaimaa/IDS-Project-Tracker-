<?php

$projects = [];
$examples = [
    ['Water Supply Improvement in Khyber District', 'Water', 'Public Health Engineering Department', 'Khyber', 'ADB', 'Concept', 'Delayed', 950000000],
    ['District Road Upgrade (Phase II)', 'Transport', 'Communication & Works Department', 'Peshawar', 'World Bank', 'Implementation', 'On track', 1800000000],
    ['Rural Health Centres (Phase I)', 'Health', 'Health Department', 'Kohat', 'World Bank', 'Implementation', 'On track', 840000000],
    ['Secondary Schools Upgradation', 'Education', 'Elementary & Secondary Education Department', 'Swat', 'ADB', 'PC-I Development', 'On track', 1200000000],
    ['Urban Flood Protection Scheme', 'Water', 'Irrigation Department', 'Nowshera', 'ADB', 'Concept', 'On track', 760000000],
    ['Community Learning Centres', 'Education', 'Elementary & Secondary Education Department', 'Mardan', 'UNDP', 'Implementation', 'On track', 320000000],
    ['District Hospital Rehabilitation', 'Health', 'Health Department', 'Abbottabad', 'World Bank', 'Implementation', 'Delayed', 1100000000],
    ['Rural Access Roads', 'Transport', 'Communication & Works Department', 'Bannu', 'ADB', 'PC-I Development', 'On track', 650000000],
    ['Clean Drinking Water Extension', 'Water', 'Public Health Engineering Department', 'Bajaur', 'UNICEF', 'Implementation', 'On track', 480000000],
    ['Digital Skills Development Centres', 'Education', 'Science & Information Technology Department', 'Dera Ismail Khan', 'UNDP', 'Concept', 'On track', 290000000],
];
foreach ($examples as $index => [$name, $sector, $agency, $district, $partner, $stage, $health, $cost]) {
    $reference = 'IDS-2026-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
    $issues = [];
    if ($health === 'Delayed') {
        $issues[] = ['name' => $stage === 'Implementation' ? 'Physical component 2 is delayed' : 'Pipeline component 3 is delayed', 'status' => 'Open', 'priority' => 'High', 'officer' => 'Project Director', 'description' => 'Review the component schedule and agree a recovery plan.', 'demo' => true];
    }
    if ($index === 0) {
        foreach (['Site access clearance pending', 'Revised cost estimates awaited', 'Technical drawings require review'] as $issueName) {
            $issues[] = ['name' => $issueName, 'status' => 'Open', 'priority' => 'Medium', 'officer' => 'Project implementation unit', 'description' => 'Coordinate with the responsible team before the next review.', 'demo' => true];
        }
    }
    $issues[] = ['name' => 'Supporting documents received', 'status' => 'Resolved', 'priority' => 'Low', 'officer' => 'Section Officer', 'resolution' => 'Documents received and reviewed.', 'demo' => true];
    $projects[] = [
        'name' => $name, 'reference' => $reference, 'stage' => $stage, 'health' => $health,
        'status' => 'Active', 'is_flagship' => $index < 3,
        'partners' => [$partner], 'sectors' => [$sector], 'districts' => [$district],
        'cost' => $cost, 'currency' => 'PKR', 'agency' => $agency, 'funding_type' => 'Development partner assistance',
        'description' => $name.' supports improved '.strtolower($sector).' services in '.$district.'. This presentation record includes sample planning and delivery information.',
        'officer' => ['Aina Khan', 'Muhammad Ali', 'Sara Ahmed'][$index % 3], 'designation' => 'Project Director',
        'email' => 'project'.($index + 1).'@example.com', 'phone' => 'Demo contact', 'office' => $agency.', Peshawar',
        'approval_date' => '2024-06-15', 'start' => '2024-07-01', 'completion' => '2027-06-30',
        'issues' => $issues, 'reasons' => $health === 'Delayed' ? [$issues[0]['name']] : [],
    ];
}

$issueSamples = [];
foreach ($projects as $project) {
    foreach ($project['issues'] as $issue) {
        $issueSamples[count($issueSamples) + 1] = array_merge($issue, ['project' => $project['name'], 'stage' => $project['stage'], 'reported_at' => '2026-10-01', 'reported_by' => $project['officer']]);
    }
}

return [
    'issue_samples' => $issueSamples,
    'presentation' => true,
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
