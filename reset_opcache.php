<?php
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "OPcache reset successfully.";
    } else {
        echo "OPcache reset failed.";
    }
} else {
    echo "OPcache is not enabled.";
}
?>