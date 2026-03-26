<?php
$data = ["2" => ["c" => 1, "a" => 1, "b" => 2], "1" => ["c" => 1, "a" => 1, "b" => 2]];

foreach($data as $key => $value){
   
   ksort($data[$key], SORT_STRING);
   echo "key: ". $key. ", value: \n";
   var_dump($value);
}


// var_dump($data);
