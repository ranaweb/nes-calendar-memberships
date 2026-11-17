<?php
/** Regression checks for monthly, split and exception semantics. Run with php qa/test-recurrence.php. */
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/calendar/includes/class-engine.php';

$input = [
    'start' => '2026-12-14 18:30:00', 'timezone' => 'America/New_York', 'duration' => 5400,
    'rules' => [['frequency' => 'monthly', 'ordinal' => [2], 'weekdays' => [1], 'end' => 'count', 'count' => 6]],
    'additions' => ['2027-01-13 17:00'], 'exclusions' => ['2027-02-08'],
];
$definition = NES_Calendar_Recurrence_Engine::normalize($input);
$slots = NES_Calendar_Recurrence_Engine::occurrences($definition, '2026-12-01', '2027-07-01');
$dates = array_column($slots, 'start');
$expected = ['2026-12-14 18:30:00', '2027-01-11 18:30:00', '2027-01-13 17:00:00',
    '2027-03-08 18:30:00', '2027-04-12 18:30:00', '2027-05-10 18:30:00'];
if ($dates !== $expected || $slots[4]['utc'] !== '2027-04-12 22:30:00' || $slots[4]['end'] !== '2027-04-12 20:00:00') {
    throw new RuntimeException('A monthly date, exception, duration or daylight-saving time changed.');
}
if (NES_Calendar_Recurrence_Engine::matches_before($definition['rules'][0], '2027-03-08 18:30:00') !== 3) {
    throw new RuntimeException('A following-date edit would restart a finite series.');
}
echo "Recurrence checks passed.\n";
