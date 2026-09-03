<?php
// PHP 8-safe compatibility helper retained for recovered creation paths.
$natures = [
    'Hardy','Lonely','Brave','Adamant','Naughty','Bold','Docile','Relaxed','Impish','Lax',
    'Timid','Hasty','Serious','Jolly','Naive','Modest','Mild','Quiet','Bashful','Rash',
    'Calm','Gentle','Sassy','Careful','Quirky'
];
$nature = $natures[random_int(0, count($natures)-1)];
