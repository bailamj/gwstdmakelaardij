<?php

echo exec('whoami');
echo exec('/usr/local/bin/git status');

<<<<<<< HEAD

$output = exec('ssh -T git@github.com');
var_dump($output);

$output2 = exec('/usr/local/bin/git fetch origin && /usr/local/bin/git merge origin/master 2>&1');
=======
$output2 = exec('/usr/local/bin/git fetch origin && /usr/local/bin/git merge origin/ontwikkelen 2>&1');
>>>>>>> ontwikkelen
var_dump($output2);

