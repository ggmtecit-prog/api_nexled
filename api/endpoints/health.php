<?php

$health = getApiHealthSnapshot();

http_response_code($health["ok"] ? 200 : 503);
echo json_encode($health);
