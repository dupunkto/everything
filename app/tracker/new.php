<?php
// Tracker form.

if(allset($_POST, ['start_date', 'start_time', 'end_date', 'end_time'])) {
  $starts_at = cast_datetime($_POST['start_date'], $_POST['start_time']);
  $ends_at = cast_datetime($_POST['end_date'], $_POST['end_time']);

  \core\new_timing($_POST['description'], $starts_at, $ends_at)
    or fail("Could not save timing from $starts_at to $ends_at with description \"$description\".");

  include __DIR__ . "/listing.php"; exit;
}

?>
<form id="tracker-form" x-post="/tracker/new" x-target="#tracker-listing">
  <textarea name="description" placeholder="What have you been up to?"></textarea>
  <label>
    Start
    <input class="start-date" name="start_date" type="date" value="<?php echo date("Y-m-d"); ?>" />
    <input class="start-time" name="start_time" type="time" step="1" />
  </label>
  <label>
    End
    <input name="end_date" type="date" value="<?php echo date("Y-m-d"); ?>" />
    <input name="end_time" type="time" step="1" />
  </label>
  <button type="button" id="tracker-button">Sorry, the tracker could not be loaded.</button>
  <button type="submit">Save</button>

  <script type="module">
    const button = document.querySelector("#tracker-button");

    // Auto-save whatever was typed into the description field in localStorage.
    document.querySelector("[name=description]").addEventListener("input", (e) => {
      localStorage.setItem("description", e.target.value);
    });

    function mountTimer() {
      const description = localStorage.getItem("description");
      const start_timing = localStorage.getItem("start_timing");

      // Restore any autosaved value for description from localStorage.
      document.querySelector("[name=description]").value = description;

      if(start_timing) {
        button.innerText = "⏸";

        // This was needed because assigning input.valueAsDate = new Date();
        // would crash in wonderful ways. Ah yes, the joys of JS programming.
        const format_time = (dt) => dt.toTimeString().slice(0, 8);

        const then = new Date(start_timing);

        document.querySelector("[name=start_date]").valueAsDate = then;
        document.querySelector("[name=start_time]").value = format_time(then);

        button.addEventListener("click", () => {
          button.innerText = "▶";

          const now = new Date();

          document.querySelector("[name=end_date]").valueAsDate = now;
          document.querySelector("[name=end_time]").value = format_time(then);

          // Remove start timings and clear the description on next run.
          localStorage.removeItem("description");
          localStorage.removeItem("start_timing");

          // Trigger the form submit.
          document.querySelector("#tracker-form").requestSubmit();
          mountTimer(); // Remount to reset component state.
        }, { once: true });
      }
      else {
        button.innerText = "▶";

        document.querySelector("[name=start_date]").value = "";
        document.querySelector("[name=start_time]").value = "";
        document.querySelector("[name=end_date]").value = "";
        document.querySelector("[name=end_time]").value = "";

        button.addEventListener("click", () => {
          button.innerText = "⏸";
          localStorage.setItem("start_timing", new Date());
          mountTimer(); // Remount to attach the onclick handler for stopping the tracker.
        }, { once: true })
      }
    }

    // When the page has finished loading, mount the tracker component.
    mountTimer();
  </script>
</form>