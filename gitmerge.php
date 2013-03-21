<?php

echo exec('whoami');
echo exec('/usr/local/bin/git status');

$output2 = exec('/usr/local/bin/git fetch origin 2>&1');
var_dump($output2);


$output = exec('ssh -T git@github.com 2>?1');
var_dump($output);