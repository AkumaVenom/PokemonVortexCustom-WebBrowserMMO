<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
try { $mysql_connection = pv_db(); } catch (Throwable $e) { if(!headers_sent()) http_response_code(503); exit; }
