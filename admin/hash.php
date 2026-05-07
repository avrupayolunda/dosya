<?php
$password = '414145';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>
