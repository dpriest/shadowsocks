<?php
function sys_echo($context) {
    $workerId = isset($_SERVER["WORKER_ID"]) ? $_SERVER["WORKER_ID"] : "";
    $dataStr = date("Y-m-d H:i:s", time());
    echo "[$dataStr #$workerId] $context\n";
}
