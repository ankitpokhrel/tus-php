<?php

define('DS', DIRECTORY_SEPARATOR);

\Carbon\Carbon::setTestNow('2017-12-08');
\TusPhp\Config::set(require __DIR__ . '/Fixtures/config.php');
