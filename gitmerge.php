<?php

echo exec('whoami');
echo exec('/usr/local/bin/git status');


$output = exec('ssh -T git@github.com');
var_dump($output);

$output2 = exec('/usr/local/bin/git fetch origin 2>&1');
var_dump($output2);


define('PRIVATE_KEY', 'f1:e1:ce:cc:6d:49:53:70:f8:fc:d9:9a:f7:2a:58:a2');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['thing'] === PRIVATE_KEY)
{
    echo shell_exec("/usr/local/bin/git fetch && git merge origin/master");
}
