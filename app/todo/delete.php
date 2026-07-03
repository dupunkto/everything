<?php

if(allset($_POST, ["id"])) {
  \store\delete_task($_POST["id"]) or fail("Could not delete task.");
  include "listing.php"; exit;
}
