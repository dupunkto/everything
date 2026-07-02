<?php
  if(!isset($_GET['id'])) fail("Appointment id is missing.", status: 400);
  $appt = \store\get_appointment($_GET['id']) or fail("Appointment not found.", status: 404);

  $tz = new DateTimeZone(getenv("TIMEZONE") ?: "Europe/Amsterdam");
  $start = (new DateTimeImmutable($appt['starts_at']))->setTimezone($tz);
  $end = (new DateTimeImmutable($appt['ends_at']))->setTimezone($tz);
  $same_day = $start->format("Y-m-d") == $end->format("Y-m-d");

  $owner_title = $appt['calendar_title'] ?? $appt['subscription_title'] ?? null;
  $owner_subtitle = $appt['calendar_subtitle'] ?? $appt['subscription_subtitle'] ?? null;
?>
<article class="appt-detail">
  <header>
    <h3><?= esc_inner($appt['title']) ?></h3>
    <p class="when">
      <?php if($appt['all_day']): ?>
        <?= $start->format("D, M j") ?>
        <?php if(!$same_day): ?> &ndash; <?= $end->format("D, M j") ?><?php endif ?>
        &middot; all day
      <?php else: ?>
        <?= $start->format("D, M j") ?>
        &middot;
        <?= $start->format("H:i") ?>&ndash;<?= $same_day ? $end->format("H:i") : $end->format("D, M j H:i") ?>
      <?php endif ?>
    </p>
  </header>

  <?php if($owner_title): ?>
    <p class="owner">
      <?= esc_inner($owner_title) ?>
      <?php if($owner_subtitle): ?><span class="muted">&middot; <?= esc_inner($owner_subtitle) ?></span><?php endif ?>
      <?php if(!empty($appt['subscription_id'])): ?><span class="muted">&middot; subscription</span><?php endif ?>
    </p>
  <?php endif ?>

  <?php if($appt['location']): ?>
    <p class="location"><?= esc_inner($appt['location']) ?></p>
  <?php endif ?>

  <?php if($appt['meeting']): ?>
    <p class="meeting"><a href="<?= esc_attr($appt['meeting']) ?>" target="_blank" rel="noopener">Join meeting</a></p>
  <?php endif ?>

  <?php if($appt['content']): ?>
    <div class="content"><?= nl2br(esc_inner($appt['content'])) ?></div>
  <?php endif ?>

  <div class="appt-detail-actions">
    <button x-get="/calendar/edit?id=<?= esc_attr($appt['id']) ?>" x-target="#calendar-popup" x-replace="innerHTML">Edit</button>
  </div>
</article>
