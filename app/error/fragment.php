<article class="request-error request-error--fragment" role="alert">
  <strong>uh oh</strong>
  <p><?= esc_inner($message) ?></p>
  <?php if(DEVELOPER_MODE): ?><a href="/logs">View logs &rarr;</a><?php endif ?>
</article>
