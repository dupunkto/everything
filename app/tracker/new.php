<?php
  // Handle the $_POST here, if exists:)
?>
<form x-post="/tracker/new" x-replace="outerHTML">
  <textarea name="description" placeholder="What have you been up to?"></textarea>
  <label>Start <input name="starts_at" type="datetime-local" /></label>
  <label>End <input name="ends_at" type="datetime-local" /></label>
  <button type="submit">Save</button>
</form>