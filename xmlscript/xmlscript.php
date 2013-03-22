<?php

    //path to directory to scan
    $directory = "xml/";

    $naam = "tst";
 
    //get all image files with a .jpg extension.
    $filenames = glob($directory . "*.xml");

    $docList = new DOMDocument();

    $root = $docList->createElement('documents');
    $docList->appendChild($root);

    foreach($filenames as $filename) {

        $doc = new DOMDocument();
        $doc->load($filename);

        $xmlString = $doc->saveXML($doc->documentElement);

        $xpath = new DOMXPath($doc);
        $query = "//RealEstateProperty";  // this is the name of the ROOT element

        $nodelist = $xpath->evaluate($query, $doc->documentElement);

        if( $nodelist->length > 0 ) {

            $node = $docList->importNode($nodelist->item(0), true);

            $xmldownload = $docList->createElement('document');
            $xmldownload->setAttribute("filename", $filename);
            $xmldownload->appendChild($node);

            $root->appendChild($xmldownload);
        }

    }

    echo $docList->saveXML();
?>