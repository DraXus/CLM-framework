<?php

$featureExtractor = '~/github/CLM-framework/bin/FeatureExtraction';
$wekaJar = '~/apps/weka-3-7-13/weka.jar';
$subjects = range(1, 51);
$trials = range(1, 4);
$poses = range(1, 4);

echo "#!/bin/bash\n";

foreach ($poses as $p) {
    $data = array_map('str_getcsv', file("~/gaze-detection/TabletGaze/start_times/pose$p.csv"));
    foreach ($subjects as $subject) {
        foreach ($trials as $t) {
            $startTime = sprintf('%2.3f', (float) $data[$subject - 1][$t - 1]);
            $startTime = str_pad($startTime, 6, '0', STR_PAD_LEFT);
            $baseName = sprintf('%d_%d_%d', $subject, $t, $p);
            $commands = [];
            $commands[] = "echo 'Processing $subject $t $p ...'";
            $commands[] = "avconv -i $subject/$baseName.mp4 -ss 00:00:$startTime -c:v copy -an $subject/$baseName.fix.mp4";
            $commands[] = "avconv -i $subject/$baseName.fix.mp4 -t 00:01:45 -c:v copy -an $subject/$baseName.new.mp4";
            $commands[] = "avconv -i $subject/$baseName.new.mp4 -r 25 $subject/$baseName.new.25fps.mp4";
            $commands[] = "$featureExtractor -q -rigid -verbose -f \"$subject/$baseName.new.25fps.mp4\" -ogaze \"$subject/$baseName.csv\"";
            $commands[] = "head -2626 $subject/$baseName.csv > $subject/$baseName.tmp";
            $commands[] = "cut -d, -f4-15 $subject/$baseName.tmp > $subject/$baseName.vectors.csv";
            $commands[] = "paste -d, $subject/$baseName.vectors.csv <(cut -d, -f1 TabletGazePoints.csv) > $subject/$baseName.x.csv";
            $commands[] = "paste -d, $subject/$baseName.vectors.csv <(cut -d, -f2 TabletGazePoints.csv) > $subject/$baseName.y.csv";
            $commands[] = "rm $subject/$baseName.vectors.csv";
            $commands[] = "rm $subject/$baseName.tmp";
            echo implode(' && ', $commands)."\n";
        }
    }
}

foreach ($subjects as $subject) {
    echo "nawk 'FNR==1 && NR!=1{next;}{print}' $subject/*.x.csv > $subject/$subject.x.tmp && mv $subject/$subject.x.tmp $subject/$subject.x.csv\n";
    echo "nawk 'FNR==1 && NR!=1{next;}{print}' $subject/*.y.csv > $subject/$subject.y.tmp && mv $subject/$subject.y.tmp $subject/$subject.y.csv\n";

    echo "sed '/^,/d' $subject/$subject.x.csv > $subject/$subject.x.tmp && mv $subject/$subject.x.tmp $subject/$subject.x.csv\n";
    echo "sed '/^,/d' $subject/$subject.y.csv > $subject/$subject.y.tmp && mv $subject/$subject.y.tmp $subject/$subject.y.csv\n";

    echo "java -cp $wekaJar weka.core.converters.CSVLoader -M \" -nan\" $subject/$subject.x.csv > $subject/$subject.x.arff\n";
    echo "java -cp $wekaJar weka.core.converters.CSVLoader -M \" -nan\" $subject/$subject.y.csv > $subject/$subject.y.arff\n";
}
