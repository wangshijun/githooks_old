#!/usr/bin/env php
<?php
// TODO 抽象下面的逻辑，更加有条理，界面更友好

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(realpath(__DIR__)) . DS);

$REDIRECT = ' 2> /dev/null';
$CWD = getcwd();
$PHP = 'php';
$JSHINT = 'jshint';
$JSHINTRC = $CWD . DS . '.jshintrc';
$ESLINT = 'eslint';
$ESLINTRC = $CWD . DS . '.eslintrc';
$CSSLINT = 'csslint';
$PHPCS = ROOT . 'vendor/bin/phpcs';
$TMP = ROOT . '.tmp' . DS;
mkdir($TMP);

$exitCode = 0;
$fileList = array();

exec("git rev-parse --verify HEAD $REDIRECT", $output, $return);
$against = $return ? '4b825dc642cb6eb9a060e54bf8d69288fbee4904' : 'HEAD';
exec("git diff-index --cached --full-index --diff-filter=ACMR $against --", $fileList);

$errmsg = array();
foreach ($fileList as $fileAttrs) {
    $fileAttrs = preg_replace('/\s+/i', ' ', $fileAttrs);
    $attrs = explode(' ', $fileAttrs);
    $sha = $attrs[3];
    $status = strtoupper($attrs[4]);
    $filename = $attrs[5];

    if (strpos($filename, 'vendor/') !== false) {
        continue;
    }

    if (!preg_match('/\.(php|js|css)$/i', $filename, $match)) {
        continue;
    }

    // make tmp file
    $ext = strtolower($match[1]);
    $tmpFilename = $TMP . $filename;
    $tmpFileDir = dirname($tmpFilename);
    if (!is_dir($tmpFileDir)) {
        exec('mkdir -p ' . $tmpFileDir);
    }
    exec("git cat-file blob $sha > $tmpFilename");

    $output = array();
    $return = 0;

    switch ($ext) {
        case 'php':
            // php -l
            exec("$PHP -l $tmpFilename $REDIRECT", $output, $return);
            if ($return) {
                $exitCode = 1;
                array_shift($output);
                array_pop($output);
                foreach ($output as $line) {
                    $errmsg[] = sprintf(" - %s:%s \n", $filename, $line);
                }
            }

            // php code sniffer
            $output = array();
            $return = 0;
            exec("$PHPCS --standard=Fegeeks --report=emacs $tmpFilename $REDIRECT", $output, $return);
            if (!empty($output)) {
                $exitCode = 1;
                foreach ($output as $line) {
                    if (empty($line)) {
                        continue;
                    }
                    $errmsg[] = sprintf(" - %s \n", str_replace($tmpFilename, $filename, $line));
                }
            }
        break;

        // jshint | eslint
        case 'js':
            if (file_exists($ESLINTRC)) {
                exec("$ESLINT -f json --config $ESLINTRC $tmpFilename", $output, $return);
                if ($return) {
                    $exitCode = 1;
                    $object = array_shift(json_decode($output[0]));
                    $filepath = str_replace($tmpFilename, $filename, $object->filePath);
                    foreach ($object->messages as $message) {
                        $errmsg[] = sprintf(" - %s:%d:%d [%s]\t%s\n", $filepath, $message->line, $message->column, $message->ruleId, $message->message);
                    }
                }
            } else if (file_exists($JSHINTRC)) {
                exec("$JSHINT --config $JSHINTRC $tmpFilename", $output, $return);
                if ($return) {
                    $exitCode = 1;
                    foreach ($output as $line) {
                        if (empty($line)) {
                            continue;
                        }
                        $errmsg[] = sprintf(" - %s \n", str_replace(array($tmpFilename, ' line ', ', col '), array($filename, '', ':'), $line));
                    }
                }
            }
        break;

        // csslint
        case 'css':
            exec("$CSSLINT --format=compact --quiet $tmpFilename $REDIRECT", $output, $return);
            if (!empty($output)) {
                $exitCode = 1;
                foreach ($output as $line) {
                    if (empty($line)) {
                        continue;
                    }
                    $errmsg[] = sprintf(" - %s \n", str_replace(array($tmpFilename, ' line ', ', col '), array($filename, '', ':'), $line));
                }
            }
        break;
    }

    unlink($tmpFilename);
}

exec('rm -rf ' . $TMP);

$passTips = array(
    'code is poetry',
    'done is better than perfect',
    'go big or go home',
    'code wins arguments',
    'move fast and break things',
    'the foolish wait',
    'fortune love bold',
    'proceed and be bold',
    'keep it simple, stupid',
    'talk is cheap, show me the code',
    'stay focused and keep shipping',
);

if ($exitCode) {
    echo implode('', $errmsg);
    echo "[\033[01;31mFAIL\033[0m] fix and try again." . "\n";
} else {
    if (count($fileList)) { // if have something to commit, show the pass tip
        shuffle($passTips);
        echo "[\033[00;32mPASS\033[0m] \033[00;36m" . ($passTips[0]) . "\033[0m\n";
    }
}

exit($exitCode);
