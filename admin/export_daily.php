<?php
// Simplest possible CSV test
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Test_File.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Column 1', 'Column 2', 'Column 3']);
fputcsv($output, ['It', 'Actually', 'Worked']);
fclose($output);
exit();
?>