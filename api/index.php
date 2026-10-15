<?php

// Vercel uses a read-only deployment filesystem. Prepare Laravel's runtime
// paths before Laravel or Composer are loaded.
require __DIR__ . '/../bootstrap/vercel.php';

// Keep Laravel's standard HTTP lifecycle and exception handling.
require __DIR__ . '/../public/index.php';
