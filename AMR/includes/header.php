<?php
$pageTitle = $pageTitle ?? 'Aling Mameng Resort';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | Aling Mameng Resort</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
 <div class="container">
  <a class="navbar-brand fw-bold" href="index.php"><span class="brand-mark">AM</span> Aling Mameng Resort</a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
    <li class="nav-item"><a class="nav-link" href="index.php#rooms">Rooms</a></li>
    <li class="nav-item"><a class="nav-link" href="index.php#facilities">Facilities</a></li>
    <li class="nav-item"><a class="nav-link" href="index.php#contact">Contact</a></li>
    <?php if (logged_in()): ?>
      <li class="nav-item"><a class="nav-link" href="<?= is_admin() ? 'admin/dashboard.php' : 'my-bookings.php' ?>"><?= is_admin() ? 'Admin Panel' : 'My Bookings' ?></a></li>
      <li class="nav-item"><a class="btn btn-outline-dark btn-sm px-3" href="logout.php">Logout</a></li>
    <?php else: ?>
      <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
      <li class="nav-item"><a class="btn btn-dark btn-sm px-3" href="register.php">Create Account</a></li>
    <?php endif; ?>
   </ul>
  </div>
 </div>
</nav>
<main>
