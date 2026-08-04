<?php

  \store\clear_system_logs();
  \store\put_audit_log('system_log', '*', "Truncated system log.", 'user', operation: 'delete');

  stay_on_page();
