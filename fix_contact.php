<?php

$updated = DB::connection()->affectingStatement(
    "UPDATE agencies SET contact_info = REPLACE(contact_info, '\\r\\n', E'\\r\\n') WHERE contact_info LIKE '%\\r\\n%'"
);
echo "Rows updated: $updated\n";
$sample = DB::table('agencies')->where('slug', 'dmw')->value('contact_info');
echo "DMW contact_info:\n--start--\n$sample\n--end--\n";
echo 'Contains CR: '.(str_contains($sample, "\r") ? 'yes' : 'no').' | LF: '.(str_contains($sample, "\n") ? 'yes' : 'no').' | literal \\r\\n: '.(str_contains($sample, '\r\n') ? 'yes' : 'no')."\n";
