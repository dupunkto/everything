<?php

  \store\clear_note_apple_ids();
  \store\clear_note_tombstones();
  \store\put_audit_log('notes', '*', "Cleared Apple UUIDs and tombstones from notes.", 'user');

  stay_on_page();
