<?php // usage: $title = 'Login'; $page = 'login'; include __DIR__.'/head.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> · FinalNote</title>
  <link rel="icon" href="data:,">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#4f46e5">
  <link rel="apple-touch-icon" href="/assets/images/icon-192.png">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-page="<?= htmlspecialchars($page) ?>">