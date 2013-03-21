<?php

echo exec('whoami');
echo exec('git status');

exec('git fetch origin && git merge origin master');

$output = shell_exec('ls -lart');
echo "<pre>$output</pre>";

$output2 = exec('/usr/local/bin/git fetch origin 2>&1');
var_dump($output2);
