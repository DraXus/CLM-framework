<?php

$featureExtractor = '~/github/CLM-framework/bin/FeatureExtraction';
$wekaJar = '~/apps/weka-3-7-13/weka.jar';
$subjects = range(1, 51);
$trials = range(1, 4);
$poses = range(1, 4);

echo "#!/bin/bash\n";

foreach ($poses as $p) {
    $file = fopen("~/gaze-detection/TabletGaze/start_times/pose$p.csv", "r");
    $subject = 1;
    while (($data = fgetcsv($file)) !== false) {
        foreach ($trials as $t) {
            $startTime = sprintf('%2.3f', (float) $data[$t - 1]);
            $startTime = str_pad($startTime, 6, '0', STR_PAD_LEFT);
            $baseName = sprintf('%d_%d_%d', $subject, $t, $p);
            $commands = [];
            $commands[] = "echo 'Processing $subject $t $p ...'";
            $commands[] = "avconv -i $subject/$baseName.mp4 -ss 00:00:$startTime -c:v copy -an $subject/$baseName.fix.mp4";
            $commands[] = "avconv -i $subject/$baseName.fix.mp4 -t 00:01:45 -c:v copy -an $subject/$baseName.new.mp4";
            $commands[] = "avconv -i $subject/$baseName.new.mp4 -r 25 $subject/$baseName.new.25fps.mp4";
            $commands[] = "$featureExtractor -rigid -verbose -f \"$subject/$baseName.new.25fps.mp4\" -ogaze \"$subject/$baseName.csv\"";
            $commands[] = "head -2626 $subject/$baseName.csv > $subject/$baseName.csv";
            $commands[] = "cut -d, -f4-15 $subject/$baseName.csv > $subject/$baseName.vectors.csv";
            $commands[] = "paste -d, $subject/$baseName.vectors.csv <(cut -d, -f1 TabletGazePoints.csv) > $subject/$baseName.x.csv";
            $commands[] = "paste -d, $subject/$baseName.vectors.csv <(cut -d, -f2 TabletGazePoints.csv) > $subject/$baseName.y.csv";
            echo implode(' && ', $commands) . "\n";
        }
        $subject++;
    }
}

foreach($subjects as $subject) {
    echo "nawk 'FNR==1 && NR!=1{next;}{print}' $subject/*.x.csv >> all.x.csv\n";
    echo "nawk 'FNR==1 && NR!=1{next;}{print}' $subject/*.y.csv >> all.y.csv\n";
}

echo "java -cp $wekaJar weka.core.converters.CSVLoader all.x.csv > all.x.arff\n";
echo "java -cp $wekaJar weka.core.converters.CSVLoader all.y.csv > all.y.arff\n";
echo "java -cp $wekaJar weka.classifiers.lazy.IBk -t all.x.arff -classifications \"weka.classifiers.evaluation.output.prediction.CSV\" -p 0 > predictions.x.csv\n";
echo "java -cp $wekaJar weka.classifiers.lazy.IBk -t all.y.arff -classifications \"weka.classifiers.evaluation.output.prediction.CSV\" -p 0 > predictions.y.csv\n";
