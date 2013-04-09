<?php 
  $dp = "https://api.wazzupsoftware.com/MultipleListingService/a95aff8f-1a5e-44cc-9438-37aaff2c255f/zip"; 
  if (!$dp) 
    { 
      echo("<P>Error: unable to load URL file into $dp.  Process aborted.</P>"); 
      exit(); 
    } 
  $sp = file_get_contents($dp); 
  highlight_string($sp); 
?>