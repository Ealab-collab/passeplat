<!doctype html>

<?php
// Retrieve the wsid from $_GET while protecting it against XSS attacks.
$wsid = htmlspecialchars($_GET['wsid'] ?? '');
?>

<html lang="en">
<head>
    <meta charset="utf-8"/>
    <link rel="icon" href="./favicon.ico"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <meta name="theme-color" content="#000000"/>
    <meta name="description" content="Web site created using create-react-app"/>
    <link rel="apple-touch-icon" href="./logo192.png"/>
    <link rel="manifest" href="./manifest.json"/>
    <title>React App</title>
    <script defer="defer" src="./static/js/main.b595f7a1.js"></script>
    <link href="./static/css/main.17a033b8.css" rel="stylesheet">
</head>
<body>
<noscript>You need to enable JavaScript to run this app.</noscript>

<html-builder data-url="/forms/navbar.php?wsid=<?php echo $wsid; ?>&activeTab=edit"
              data-method="POST">
</html-builder>

<html-builder data-url="http://localhost/forms/webserviceEdit.php?wsid=<?php echo $wsid; ?>"
              data-method="POST">
</html-builder>
</body>
</html>
