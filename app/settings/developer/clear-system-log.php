<?php

  \store\clear_system_logs();
  \store\put_audit_log('system_logs', '*', "Truncated system log.", 'user', operation: 'delete');

  stay_on_page();
