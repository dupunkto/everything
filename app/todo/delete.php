<?php

  if(!isset($_GET['id'])) fail("Task is missing.", status: 400);
  \store\delete_task($_GET["id"]) or fail("Could not delete task.");
  include "listing.php";
