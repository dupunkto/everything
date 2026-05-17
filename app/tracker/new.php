<?php
if(allset($_POST, ['start_date', 'start_time', 'end_date', 'end_time'])) {
  $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
  $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

  \core\new_timing($_POST['description'], $starts_at, $ends_at)
    or fail("Could not save timing from $starts_at to $ends_at with description '" . $_POST['description'] . "'.");

  include __DIR__ . "/listing.php"; exit;
}

?>
<form id="tracker-form" x-post="/tracker/new" x-target="#tracker-listing">
  <textarea name="description" placeholder="What have you been up to?" autofocus></textarea>
  <div class="col">
    <label>
      Start
      <input name="start_date" type="date" value="<?= local_date("Y-m-d") ?>" />
      <input name="start_time" type="time" step="1" />
    </label>
    <label>
      End
      <input name="end_date" type="date" value="<?= local_date("Y-m-d") ?>" />
      <input name="end_time" type="time" step="1" />
    </label>
  </div>
  <button type="button" id="tracker-record"">Sorry, the tracker could not be loaded.</button>
  <button type="submit" id="tracker-submit">Save</button>

  <script type="module">
    const button = document.querySelector("#tracker-record");

    // Auto-save whatever was typed into the description field in localStorage.
    document.querySelector("[name=description]").addEventListener("input", (e) => {
      localStorage.setItem("description", e.target.value);
    });

    document.querySelector("#tracker-form").addEventListener("input", (e) => {
      const inputs = Array.from(e.target.form.querySelectorAll("input"));

      if(inputs.every((input) => input.value)) {
        document.querySelector("#tracker-record").style.display = "none";
        document.querySelector("#tracker-submit").style.display = "block";
      } else {
        document.querySelector("#tracker-record").style.display = "block";
        document.querySelector("#tracker-submit").style.display = "none";
      }
    });

    function mountTimer() {
      const description = localStorage.getItem("description");
      const start_timing = localStorage.getItem("start_timing");

      const format_date = (dt) => {
        const year = dt.getFullYear();
        const month = String(dt.getMonth() + 1).padStart(2, "0");
        const day = String(dt.getDate()).padStart(2, "0");

        return `${year}-${month}-${day}`;
      };

      const format_time = (dt) => dt.toTimeString().slice(0, 8);

      // Restore any autosaved value for description from localStorage.
      document.querySelector("[name=description]").value = description;

      if(start_timing) {
        button.innerText = "⏸";

        const then = new Date(start_timing);

        document.querySelector("[name=start_date]").value = format_date(then);
        document.querySelector("[name=start_time]").value = format_time(then);
        document.querySelector("#tracker-submit").style.display = "none";

        button.addEventListener("click", () => {
          button.innerText = "▶";

          const now = new Date();

          document.querySelector("[name=end_date]").value = format_date(now);
          document.querySelector("[name=end_time]").value = format_time(now);

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

        const today = new Date();

        document.querySelector("[name=start_date]").value = format_date(today);
        document.querySelector("[name=start_time]").value = "";
        document.querySelector("[name=end_date]").value = format_date(today);
        document.querySelector("[name=end_time]").value = "";
        document.querySelector("#tracker-record").style.display = "block";
        document.querySelector("#tracker-submit").style.display = "none";

        button.addEventListener("click", () => {
          button.innerText = "⏸";
          localStorage.setItem("start_timing", new Date().toISOString());
          mountTimer(); // Remount to attach the onclick handler for stopping the tracker.
        }, { once: true })
      }
    }

    // When the page has finished loading, mount the tracker component.
    mountTimer();
  </script>
</form>
