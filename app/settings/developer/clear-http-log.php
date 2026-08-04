<?php

  \store\clear_http_logs();
  \store\put_audit_log('http_logs', '*', "Truncated HTTP log.", 'user', operation: 'delete');

  stay_on_page();
