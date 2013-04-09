<?php
 if( ! function_exists('apache_get_modules') ){ phpinfo(); die; }
 $result = ' not available';
 if(in_array('mod_rewrite',apache_get_modules())) $result = ' 

available';

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 

"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Check for mod_rewrite</title></head>
<body>
<p><?php echo apache_get_version(),"</p><p>mod_rewrite $result"; ?></p>
</body>
</html>