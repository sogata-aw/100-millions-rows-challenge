<?php

namespace App;

use parallel\Runtime;
use parallel\Channel;

use SplFileObject;


final class ParserMultiThread
{

    public function parse(string $inputPath, string $outputPath): void
    {
        //Reçois les données et les formattent

        $rec = function ($ch, $nbThreads) {
            $data = [];
            $nbReceive = 0;

            while ($nbReceive != $nbThreads) {
                $rec = $ch->recv();
                $result = $rec[1];
                foreach ($result as $key => $arr) {
                    foreach ($arr as $value) {
                        $data[$key][$value] = ($data[$key][$value] ?? 0) + 1;
                    }
                }
                if($rec[0]){
                    $nbReceive++;
                }
            }
            $ch->close();
            return $data;
        };

        //Lecture et premier formattage

        $run = function ($ch, $inputPath, $lineBegin, $lineEnd) {
            $file = new SplFileObject($inputPath, "r");
            $file->seek($lineBegin);

            $result = [];

            while ($file->key() < $lineEnd && !$file->eof()) {
                $line = $file->fgets();

                $data = explode(',', rtrim($line));

                $formatted_key = substr($data[0], 19);
                $formatted_value = substr($data[1], 0, -15);

                $result[$formatted_key][] = $formatted_value;

                if (count($result) % 5000 === 0) {
                    $ch->send([$file->key() == $lineEnd,$result]);
                    $result = [];
                }                

            }
            if(!empty($result)){
                $ch->send([true,$result]);
            }
        };

        //Initialisation des variables pour les threads


        $threads = [];

        $nbThreads = 6;

        $totalLine = 1000000;
        $nbLinePerThreads = intdiv($totalLine, $nbThreads);


        //Création des threads

        $ch = new Channel();

        for ($i = 0; $i < $nbThreads; $i++) {
            $lineBegin = $nbLinePerThreads * $i;
            $lineEnd = $nbLinePerThreads * ($i + 1);

            array_push($threads, (new Runtime())->run($run, [$ch, $inputPath, $lineBegin, $lineEnd]));
        }

        //Réception des données formattées

        $future = (new Runtime())->run($rec, [$ch, $nbThreads]);

        $data = $future->value();

        foreach ($data as $key => $value) {
            ksort($data[$key], SORT_STRING);
        }

        //Création de la string JSON

        $lastKey = array_key_last($data);

        $bjstr = "{\n";

        foreach ($data as $key => $value) {
            $lastElement = array_key_last($data[$key]);
            $nk = str_replace("/", "\/", $key);
            $bjstr .= "    \"$nk\": {\n";
            foreach ($value as $date => $dv) {
                if ($date != $lastElement) {
                    $bjstr .= "        \"$date\": $dv,\n";
                } else {
                    $bjstr .= "        \"$date\": $dv\n";
                }
            }
            if ($lastKey != $key) {
                $bjstr .= "    },\n";
            } else {
                $bjstr .= "    }\n";
            }
        }

        $bjstr .= "}";

        file_put_contents($outputPath, $bjstr);
    }
}
