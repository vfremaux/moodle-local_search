<?php

/**
 * print and exit (for debugging)
 * @param str a variable to explore
 * @return void
 */
function search_pexit($str = '') {
    if (is_array($str) or is_object($str)) {
        print_r($str);
    } else if ($str) {
        echo $str."<br/>";
    }
    exit(0);
}
