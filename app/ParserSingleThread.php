<?php

namespace App;

final class ParserSingleThread
{

    public function parse(string $inputPath, string $outputPath): void
    {
        $file = fopen($inputPath, "r");
        $data = [];

        while (!feof($file)) {
            $line = fgets($file);
            $cut = strpos($line, ",");

            $k = substr($line, 19, str_contains($line, "\n") ? -27 : -26);
            $v = substr($line, $cut + 1, str_contains($line, "\n") ? -16 : -15);

            if (!empty($data[$k][$v])) {
                $data[$k][$v]++;
            } else {
                $data[$k][$v] = 1;
            }
        }

        fclose($file);


        array_walk($data, function (&$sub) {
            ksort($sub, SORT_STRING);
        });

        $lastKey = array_key_last($data);
        $bjstr = "{\n";
        foreach($data as $key => $value) {
            $lastElement = array_key_last($data[$key]);
            $nk = str_replace("/","\/", $key);
            $bjstr .= "    \"$nk\": {\n";
            foreach($value as $date => $dv){
                if($date != $lastElement){
                    $bjstr .= "        \"$date\": $dv,\n";
                }else{
                    $bjstr .= "        \"$date\": $dv\n";
                }
            }
            if ($lastKey != $key){
                $bjstr .= "    },\n";
            }else{
                $bjstr .= "    }\n";   
            }
        }

        $bjstr .= "}";

        file_put_contents($outputPath, $bjstr);
    }
}