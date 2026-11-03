<?php
$visibility = $data['visibility'];
$modern = ($data['template'] ?? 'standard') === 'modern';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
body{font-family:DejaVu Sans,Arial,sans-serif;color:#17302d;font-size:10pt;line-height:1.45;margin:34px}
h1{font-size:25pt;margin:0 0 4px;color:#0b806a}
h2{font-size:12pt;text-transform:uppercase;letter-spacing:1px;color:#0b806a;border-bottom:1px solid #dce8df;padding-bottom:5px;margin:20px 0 8px}
.modern h1{color:#2548a2}.modern h2{color:#2548a2}
.meta{color:#64736f;font-size:9pt}.summary{margin-top:14px;padding:12px;background:#f2f7f3}
.item{margin:8px 0}.item strong{font-size:10.5pt}.sub{color:#63736e;font-size:9pt}.biodata-header{display:table;width:100%;margin-bottom:12px}.biodata-photo-area{display:table-cell;vertical-align:middle;text-align:center;width:2in;height:2in;border:1px solid #dce5df;border-radius:4px}.biodata-photo{display:inline-block;vertical-align:middle;max-width:2in;max-height:2in;width:auto;height:auto}.biodata-header>div:not(.biodata-photo-area){display:table-cell;vertical-align:top;padding-left:18px}.biodata-header .meta,.biodata-header p{margin:5px 0}
</style>
</head>
<body class="<?= $modern ? 'modern' : 'standard' ?>">
<?php require __DIR__ . '/biodata_content.php'; ?>
</body>
</html>
