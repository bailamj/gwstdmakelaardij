<?php

echo exec('whoami');
echo exec('/usr/local/bin/git status');


$output2 = exec('/usr/local/bin/git fetch origin && /usr/local/bin/git merge origin/ontwikkelen 2>&1');
var_dump($output2);