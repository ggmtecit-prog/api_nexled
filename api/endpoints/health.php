<?php

$health = getApiHealthSnapshot();

respondJson($health, $health["ok"] ? 200 : 503);
