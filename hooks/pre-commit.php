#!/usr/bin/env php
<?php
/**
 * @description pre-commit check
 * @require php 5.3.2 or higher
 * only check the staged file content for syntax errors
 */

$REDIRECT = ' 2> /dev/null';
$PHP = 'php';
$JSHINT = 'jshint';
$JSHINTRC = '.jshintrc';
$CSSLINT= 'csslint';
$PHPLINT = 'bin/phplint';
$PHPCS = 'vendor/bin/phpcs';
$EXIT_CODE = 0;
$FILE_LIST = array();

// 获取远程仓库名称
function getRemoteRepoName() {
    exec('git remote show origin', $output, $return);
    if ($return) return false;
    preg_match('/git@github\.com:(.*)$/', $output[1], $matches);
    return $matches ? $matches[1] : false;
}

// 获得参考commit
function getAgainst() {
    global $REDIRECT;
    exec('git rev-parse --verify HEAD ' . $REDIRECT, $output, $return);
    return $return ?
        // Initial commit: diff against an empty tree object
        '4b825dc642cb6eb9a060e54bf8d69288fbee4904' :
        'HEAD';
}

$repoName = getRemoteRepoName();
$against = getAgainst();

exec('git diff-index --cached --full-index ' . $against, $FILE_LIST);

$errmsg = array();
foreach ($FILE_LIST as $fileAttrs) {
    $fileAttrs = preg_replace('/\s+/i', ' ', $fileAttrs);
    $attrs = explode(' ', $fileAttrs);
    $sha = $attrs[3];
    $status = strtoupper($attrs[4]);
    $filename = $attrs[5];

    // skip deleted files
    if ($status == 'D') {
        continue;
    }

    // skip 3rdparty library directory
    // if (preg_match('/vendor\/.*\.js$/i', $filename)) {
    //     continue;
    // }

    if (!preg_match('/\.(php|js|css)$/i', $filename, $match)) {
        continue;
    }

    $ext = strtolower($match[1]);
    $output = array();
    if ($ext == 'php') {
        exec('git cat-file -p '.$sha, $out);
        $out = implode("\n", $out);
        exec('git cat-file -p '.$sha.' | ' . $PHP . ' -l ' . $REDIRECT, $output, $return);
        if ($return) {
            $EXIT_CODE = 1;
            array_shift($output);
            array_pop($output);
            //array_pop($output);
            foreach ($output as $line) {
                $errmsg[] = sprintf("-%s:%s \n", $filename, $line);
            }
        }
        if(strstr($filename,'template')!==false){
            // template代码生成临时文件名中增加template关键字
            $tmpfilename = tempnam('/tmp', '.githooks_template_');
        }
        else{
            $tmpfilename = tempnam('/tmp', '.githooks_');
        }

        exec("git cat-file -p $sha > $tmpfilename");
        if($tmpfilename===false){
            $EXIT_CODE = 1;
            $errmsg[] = "create temp file failed";
            break;
        }
        $output = array();
        $return = 0;

        // php code lint
        exec( $PHPLINT . ' ' . $tmpfilename.  $REDIRECT, $output, $return);
        if (count($output) > 0) {
            $EXIT_CODE = 1;
            foreach ($output as $line) {
                $errmsg[] = sprintf("%s \n", str_replace($tmpfilename, $filename, $line));
            }
        }

        // php code sniffer
        $output = array();
        $return = 0;
        exec( $PHPCS . ' --standard=MT --report=emacs '. $tmpfilename . $REDIRECT, $output, $return);
        if (count($output) > 0) {
            $EXIT_CODE = 1;
            foreach ($output as $line) {
                $errmsg[] = sprintf("%s \n", str_replace($tmpfilename, $filename, $line));
            }
        }
        unlink($tmpfilename);

    // jshint
    // 目前jshint不支持从stdin读取，暂使用临时文件解决
    } elseif ($ext == 'js') {
        $stagedFile = tempnam('/tmp', 'git-hook-');
        if ($stagedFile === false) {
            $EXIT_CODE = 1;
            $errmsg[] = "create temp file failed";
            break;
        } else {
            // jshint必须指名后缀才检查
            unlink($stagedFile);
            $stagedFile .= '.js';
            exec("git cat-file -p ${sha} > ${stagedFile};".
                    "${JSHINT} --config ${JSHINTRC} ${stagedFile}", $output, $return);
            foreach ($output as $key => $line) {
                $output[$key] = str_replace($stagedFile. ':', '', $line);
            }
            unlink($stagedFile);
        }

        if ($return) {
            $EXIT_CODE = 1;
            foreach ($output as $line) {
                if (empty($line)) continue;
                $errmsg[] = sprintf("-%s:%s \n", $filename, $line);
            }
        }

    // csslint
    } elseif ($ext == 'css') {
        exec('git cat-file -p '.$sha.' | ' . $CSSLINT. ' - ' . $REDIRECT, $output, $return);
        if ($return) {
            $EXIT_CODE = 1;
            foreach ($output as $line) {
                $errmsg[] = sprintf("-%s:%s \n", $filename, $line);
            }
        }
    }
}

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

if ($EXIT_CODE) {
    echo implode('', $errmsg);
    echo "[\033[01;31mFAIL\033[0m] fix and try again." . "\n";
} else {
    if (count($FILE_LIST)) { // if have something to commit, show the pass tip
        shuffle($passTips);
        echo "[\033[00;32mPASS\033[0m] \033[00;36m". ($passTips[0]) . "\033[0m\n";
    }
}

exit($EXIT_CODE);
