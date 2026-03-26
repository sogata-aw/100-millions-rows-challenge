<?php

namespace App;

use parallel\Runtime;
use parallel\Channel;

use SplFileObject;


final class ParserMultiThread
{

    public function parse(string $inputPath, string $outputPath): void
    {
        $rec = function ($ch, $nbThreads) {
            $nbReceive = 0;

            while ($nbReceive != $nbThreads) {
                $result = $ch->recv();
                foreach ($result as $key => $arr) {
                    foreach ($arr as $value) {
                        if (!empty($data[$key][$value])) {
                            $data[$key][$value]++;
                        } else {
                            $data[$key][$value] = 1;
                        }
                    }
                }
                $nbReceive++;
            }
            $ch->close();
            return $data;
        };

        $run = function ($nb, $ch, $inputPath, $lineBegin, $lineEnd) {
            $file = new SplFileObject($inputPath, "r");
            for ($i = 0; $i < $lineBegin; $i++) {
                $file->fgets();
            }

            $result = [];
            $nbLineRead = 0;

            while ($file->key() < $lineEnd && !$file->eof()) {
                $line = $file->fgets();

                $cut = strpos($line, ",");

                $formatted_key = substr($line, 19, str_contains($line, "\n") ? -27 : -26);
                $formatted_value = substr($line, $cut + 1, str_contains($line, "\n") ? -16 : -15);

                if (!empty($result[$formatted_key])) {
                    array_push($result[$formatted_key], $formatted_value);
                } else {
                    $result[$formatted_key] = [$formatted_value];
                }
                $nbLineRead++;
            }
            $ch->send($result);
        };


        $offsets = [];
        $file = new SplFileObject($inputPath, "r");
        $file->rewind();

        while (!$file->eof()) {
            $offsets[] = $file->ftell();
            $file->fgets();
        }


        $threads = [];
        $nbThreads = 4;
        $totalLine = count($offsets) - 1;
        $nbLinePerThreads = intdiv($totalLine, $nbThreads);

        $data = [];

        $ch = new Channel();

        for ($i = 0; $i < $nbThreads; $i++) {
            $thread = new Runtime();
            $thread->run($run, [$i, $ch, $inputPath, $nbLinePerThreads * $i + 1, $nbLinePerThreads + $nbLinePerThreads * $i]);
            array_push($threads, $thread);
        }

        $future = (new Runtime())->run($rec, [$ch, $nbThreads]);

        $data = $future->value();

        foreach ($data as $key => $value) {
            ksort($data[$key], SORT_STRING);
        }

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
