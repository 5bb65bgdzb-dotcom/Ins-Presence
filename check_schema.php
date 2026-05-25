<?php
$mysqli = new mysqli('127.0.0.1','root','','inspresence');
if ($mysqli->connect_error) {
    echo 'CONNECTERROR:' . $mysqli->connect_error;
    exit(1);
}
$tables = ['agents', 'presences', 'utilisateurs'];
foreach ($tables as $table) {
    echo "TABLE: $table\n";
    $result = $mysqli->query("SHOW COLUMNS FROM {$table}");
    if (!$result) {
        echo "ERROR: {$mysqli->error}\n";
        continue;
    }
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . ' | ' . $row['Key'] . ' | ' . $row['Default'] . "\n";
    }
    echo "\n";
}
?>
