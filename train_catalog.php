<?php

function getTrainCatalog(): array
{
    return [
        12002 => [
            'train_id' => 12002,
            'train_name' => 'New Delhi Shatabdi Express',
            'train_code' => 'NDLS-BPL',
            'from' => 'New Delhi',
            'from_code' => 'NDLS',
            'to' => 'Bhopal Junction',
            'to_code' => 'BPL',
            'departure' => '06:00',
            'arrival' => '14:25',
            'duration' => '08h 25m',
            'distance_km' => 707,
            'days' => 'Mon, Tue, Wed, Thu, Fri, Sat',
            'classes' => [
                'CC' => ['label' => 'Chair Car', 'price' => 850],
                'EC' => ['label' => 'Executive Chair Car', 'price' => 1450],
            ],
        ],
        12628 => [
            'train_id' => 12628,
            'train_name' => 'Karnataka Express',
            'train_code' => 'SBC-NDLS',
            'from' => 'KSR Bengaluru',
            'from_code' => 'SBC',
            'to' => 'New Delhi',
            'to_code' => 'NDLS',
            'departure' => '19:20',
            'arrival' => '12:10',
            'duration' => '40h 50m',
            'distance_km' => 2392,
            'days' => 'Daily',
            'classes' => [
                'SL' => ['label' => 'Sleeper', 'price' => 760],
                '3A' => ['label' => 'AC 3 Tier', 'price' => 1960],
                '2A' => ['label' => 'AC 2 Tier', 'price' => 2840],
                'GN' => ['label' => 'General', 'price' => 420],
            ],
        ],
        12952 => [
            'train_id' => 12952,
            'train_name' => 'Mumbai Rajdhani Express',
            'train_code' => 'MMCT-NDLS',
            'from' => 'Mumbai Central',
            'from_code' => 'MMCT',
            'to' => 'New Delhi',
            'to_code' => 'NDLS',
            'departure' => '16:35',
            'arrival' => '08:35',
            'duration' => '16h 00m',
            'distance_km' => 1384,
            'days' => 'Daily',
            'classes' => [
                '3A' => ['label' => 'AC 3 Tier', 'price' => 2480],
                '2A' => ['label' => 'AC 2 Tier', 'price' => 3380],
            ],
        ],
        12622 => [
            'train_id' => 12622,
            'train_name' => 'Tamil Nadu Express',
            'train_code' => 'NDLS-MAS',
            'from' => 'New Delhi',
            'from_code' => 'NDLS',
            'to' => 'MGR Chennai Central',
            'to_code' => 'MAS',
            'departure' => '22:30',
            'arrival' => '07:10',
            'duration' => '32h 40m',
            'distance_km' => 2182,
            'days' => 'Daily',
            'classes' => [
                'SL' => ['label' => 'Sleeper', 'price' => 810],
                '3A' => ['label' => 'AC 3 Tier', 'price' => 2080],
                '2A' => ['label' => 'AC 2 Tier', 'price' => 2990],
                'GN' => ['label' => 'General', 'price' => 430],
            ],
        ],
        12302 => [
            'train_id' => 12302,
            'train_name' => 'Howrah Rajdhani Express',
            'train_code' => 'NDLS-HWH',
            'from' => 'New Delhi',
            'from_code' => 'NDLS',
            'to' => 'Howrah Junction',
            'to_code' => 'HWH',
            'departure' => '16:55',
            'arrival' => '10:05',
            'duration' => '17h 10m',
            'distance_km' => 1449,
            'days' => 'Daily',
            'classes' => [
                '3A' => ['label' => 'AC 3 Tier', 'price' => 2330],
                '2A' => ['label' => 'AC 2 Tier', 'price' => 3210],
            ],
        ],
        12723 => [
            'train_id' => 12723,
            'train_name' => 'Telangana Express',
            'train_code' => 'HYB-NDLS',
            'from' => 'Hyderabad Deccan',
            'from_code' => 'HYB',
            'to' => 'New Delhi',
            'to_code' => 'NDLS',
            'departure' => '06:00',
            'arrival' => '07:40',
            'duration' => '25h 40m',
            'distance_km' => 1677,
            'days' => 'Daily',
            'classes' => [
                'SL' => ['label' => 'Sleeper', 'price' => 690],
                '3A' => ['label' => 'AC 3 Tier', 'price' => 1810],
                '2A' => ['label' => 'AC 2 Tier', 'price' => 2580],
                'GN' => ['label' => 'General', 'price' => 390],
            ],
        ],
    ];
}

function getStationList(): array
{
    return [
        'New Delhi',
        'Bhopal Junction',
        'KSR Bengaluru',
        'Mumbai Central',
        'MGR Chennai Central',
        'Howrah Junction',
        'Hyderabad Deccan',
    ];
}

function getTrainById($trainId): ?array
{
    $catalog = getTrainCatalog();
    $id = (int) $trainId;

    return $catalog[$id] ?? null;
}

function findMatchingTrains(string $from, string $to): array
{
    $from = strtolower(trim($from));
    $to = strtolower(trim($to));
    $matches = [];

    foreach (getTrainCatalog() as $train) {
        if (strtolower($train['from']) === $from && strtolower($train['to']) === $to) {
            $matches[] = $train;
        }
    }

    return $matches;
}

