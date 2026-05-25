<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/forward-core.php';

forwardApiRequest('http://217.29.139.44:555/mobile_app', 'manifest.php');
