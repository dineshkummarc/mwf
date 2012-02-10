<?php

/**
 * 
 * @author trott
 * @date 20120210
 * 
 * extract_php_tokens.php 
 * 
 * Usage: extract_php_tokens.php -i INPUT_FILE -o OUTPUT_FILE
 * 
 * Reads source JS with mingled PHP, removes PHP, minifies with YUI compressor,
 *     and restores the PHP.
 * 
 * The token substitution algorithm will work with vars.src.php but is not
 *     generalized. Re-using it for other files is not recommended.
 */
$opts = getopt('i:o:');

if (isset($opts['i'])) {
    if (!$code = file_get_contents($opts['i']))
        die('Could not read input file ' . $opts['i']);
} else {
    // No input file specified on command-line so read from stdin.
    $code = '';
    $stdin = fopen('php://stdin', 'r');
    while ($input = fgets($stdin))
        $code .= $input;
}

$tokens = token_get_all($code);

// replace each block of PHP code with a string hash
$minifiable = '';

reset($tokens);
$hash_array = array();
$code_chunk_array = array();
while (list(, $token) = each($tokens)) {
    if (is_array($token)) {
        list($index, $code, $line) = $token;
        if ($index == T_OPEN_TAG) {
            $php_code_chunk = $code;
            while (list(, $token) = each($tokens)) {
                if (is_array($token)) {
                    list($index, $code, $code) = $token;
                    $php_code_chunk .= $code;
                    if ($index == T_CLOSE_TAG)
                        break;
                } else {
                    $php_code_chunk .= $token;
                }
            }
            $hash = md5($php_code_chunk);
            // Exclamation point tells YUI Compressor to preserve comment.
            $cipher = '/*!' . $hash . '*/function(){}';
            $code_chunk_array[] = $php_code_chunk;
            $hash_array[] = $cipher;
            $minifiable .= $cipher;
        } else {
            $minifiable .= $code;
        }
    } else {
        $minifiable .= $token;
    }
}
$minifiable_file = tempnam(sys_get_temp_dir(), "MWFMIN_");
file_put_contents($minifiable_file, $minifiable);

// Now that all the PHP chunks have been replaced with MD5 strings, we can 
//    minify the JS code.
$minify_command = 'java -jar ' . dirname(__FILE__) . '/yuicompressor-2.4.7.jar --type js ' . $minifiable_file;
exec($minify_command, $yui_compressor_output);
unlink($minifiable_file);

$minified = implode("", $yui_compressor_output);

// Now that the code is minified, put the PHP back where the tokens were.
//   Again, this is not generalized.  It will work for vars.src.php, though.
//if (substr($minified, 0, strlen($hash_array[0])+1 ) == $hash_array[0].';') {
//    $minified = str_replace($hash_array[0].';', $code_chunk_array[0], $minified );
//} 
$minified = str_replace($hash_array, $code_chunk_array, $minified);

// Now put the minified code where it belongs
if (isset($opts['o'])) {
    if (!file_put_contents($opts['o'], $minified))
        die('Could not write output file ' . $opts['o']);
} else {
    // No output file specified on command-line so write to stdout
    echo $minified;
}


