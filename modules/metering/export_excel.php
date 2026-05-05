<?php
include $_SERVER['DOCUMENT_ROOT'].'/wowasco-system/api/db.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=meters_export.xls");
header("Pragma: no-cache");
header("Expires: 0");

$sql = "SELECT * FROM meters";
$result = $conn->query($sql);

echo "Serial\tModel\tName\tID\tPhone\tType\tStatus\n";

while($row = $result->fetch_assoc()){
    echo $row['serial_number']."\t".
         $row['model']."\t".
         $row['customer_name']."\t".
         $row['national_id']."\t".
         $row['customer_phone']."\t".
         $row['customer_type']."\t".
         ($row['status'] ?? 'Active')."\n";
}
exit;
?>