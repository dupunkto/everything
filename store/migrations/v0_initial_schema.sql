CREATE TABLE IF NOT EXISTS migrations (
  version int(11) NOT NULL,
  executed_at datetime NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (version)
);

CREATE TABLE IF NOT EXISTS audit_log (
  id int(11) NOT NULL AUTO_INCREMENT,
  changed_at datetime NOT NULL DEFAULT current_timestamp,
  table_name text NOT NULL,
  record_id text NOT NULL,
  message text NOT NULL,
  author text NOT NULL, -- user|system|syncer|caldav|carddav|agent
  operation text NOT NULL DEFAULT 'update',
  PRIMARY KEY (id),
  CHECK (operation IN ('insert', 'update', 'delete'))
);

CREATE TABLE IF NOT EXISTS system_logs (
  id int(11) NOT NULL AUTO_INCREMENT,
  changed_at datetime NOT NULL DEFAULT current_timestamp,
  level text NOT NULL,
  message text NOT NULL,
  context text,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS http_logs (
  id int(11) NOT NULL AUTO_INCREMENT,
  changed_at datetime NOT NULL DEFAULT current_timestamp,
  method text NOT NULL,
  uri text NOT NULL,
  status int(11) NOT NULL,
  authenticated boolean NOT NULL,
  remote_addr text,
  user_agent text,
  referer text,
  content_type text,
  request_bytes int(11),
  response_bytes int(11),
  duration_ms int(11) NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS config (
  property text NOT NULL,
  value text NOT NULL,
  PRIMARY KEY (property)
);

CREATE TABLE IF NOT EXISTS tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  parent_id int(11),
  label text NOT NULL,
  color text NOT NULL,
  position int(11) NOT NULL DEFAULT 0,
  FOREIGN KEY (parent_id) REFERENCES tags (id) ON DELETE SET NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS humids (
  id text NOT NULL,
  type text NOT NULL,
  CHECK (type IN ('note', 'todo', 'wish', 'appointment', 'timing', 'bookmark', 'contact', 'organisation', 'address', 'habit', 'calendar', 'subscription', 'share', 'alarm')),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS contacts (
  id text NOT NULL, -- humid
  display_name text,
  first_name text NOT NULL,
  middle_name text,
  legal_infix text,
  legal_name text,
  family_infix text,
  family_name text,
  name_order text NOT NULL DEFAULT 'family_legal',
  nickname text,
  pronouns text,
  birth_day int(2),
  birth_month int(2),
  birth_year int(4),
  anniversary_day int(2),
  anniversary_month int(2),
  anniversary_year int(4),
  timezone text,
  note text,
  CHECK (name_order IN ('legal_family', 'family_legal')),
  CHECK ((birth_day IS NULL) = (birth_month IS NULL)),
  CHECK (birth_year IS NULL OR (birth_day IS NOT NULL AND birth_month IS NOT NULL)),
  CHECK ((anniversary_day IS NULL) = (anniversary_month IS NULL)),
  CHECK (anniversary_year IS NULL OR (anniversary_day IS NOT NULL AND anniversary_month IS NOT NULL)),
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS organisations (
  id text NOT NULL, -- humid
  display_name text NOT NULL,
  legal_name text,
  registration_number text,
  vat_number text,
  timezone text,
  note text,
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS profile_pictures (
  id int(11) NOT NULL AUTO_INCREMENT,
  contact_id text,
  org_id text,
  mime_type text NOT NULL,
  content mediumblob NOT NULL,
  content_hash text NOT NULL,
  FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
  FOREIGN KEY (org_id) REFERENCES organisations (id) ON DELETE CASCADE,
  CHECK ((contact_id IS NULL) != (org_id IS NULL)),
  UNIQUE (contact_id),
  UNIQUE (org_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS contacts_tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  contact_id text NOT NULL,
  tag_id int(11) NOT NULL,
  FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  UNIQUE (contact_id, tag_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS orgs_tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  org_id text NOT NULL,
  tag_id int(11) NOT NULL,
  FOREIGN KEY (org_id) REFERENCES organisations (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  UNIQUE (org_id, tag_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS contact_roles (
  id int(11) NOT NULL AUTO_INCREMENT,
  contact_id text NOT NULL,
  org_id text NOT NULL,
  role text,
  main boolean NOT NULL DEFAULT false,
  FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
  FOREIGN KEY (org_id) REFERENCES organisations (id) ON DELETE CASCADE,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS contact_socials (
  id int(11) NOT NULL AUTO_INCREMENT,
  contact_id text NOT NULL,
  handle text NOT NULL,
  type text NOT NULL, -- str<instagram|discord|snapchat|linkedin|matrix|pinterest
                        -- twitter|youtube|facebook|activitypub|bsky>
  FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS org_socials (
  id int(11) NOT NULL AUTO_INCREMENT,
  org_id text NOT NULL,
  type text NOT NULL, -- instagram|discord|snapchat|linkedin|pinterest|youtube|facebook
  handle text NOT NULL,
  FOREIGN KEY (org_id) REFERENCES organisations (id) ON DELETE CASCADE,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS contact_urls (
  id int(11) NOT NULL AUTO_INCREMENT,
  contact_id text NOT NULL,
  label text,
  url text NOT NULL,
  FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
  UNIQUE (contact_id, url),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS org_urls (
  id int(11) NOT NULL AUTO_INCREMENT,
  org_id text NOT NULL,
  label text,
  url text NOT NULL,
  FOREIGN KEY (org_id) REFERENCES organisations (id) ON DELETE CASCADE,
  UNIQUE (org_id, url),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS contact_emails (
  id int(11) NOT NULL AUTO_INCREMENT,
  contact_id text NOT NULL,
  label text,
  email text NOT NULL,
  FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
  UNIQUE (contact_id, email),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS org_emails (
  id int(11) NOT NULL AUTO_INCREMENT,
  org_id text NOT NULL,
  label text,
  email text NOT NULL,
  FOREIGN KEY (org_id) REFERENCES organisations (id) ON DELETE CASCADE,
  UNIQUE (org_id, email),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS contact_phone_numbers (
  id int(11) NOT NULL AUTO_INCREMENT,
  contact_id text NOT NULL,
  label text,
  phone_number text NOT NULL,
  FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
  UNIQUE (contact_id, phone_number),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS org_phone_numbers (
  id int(11) NOT NULL AUTO_INCREMENT,
  org_id text NOT NULL,
  label text,
  phone_number text NOT NULL,
  FOREIGN KEY (org_id) REFERENCES organisations (id) ON DELETE CASCADE,
  UNIQUE (org_id, phone_number),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS addresses (
  id text NOT NULL, -- humid
  label text,
  street_address text NOT NULL,
  postal_code text,
  city text, -- or locality for international addresses
  province text,
  country text,
  CHECK (country IN ('AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW')),
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS contact_addresses (
  id int(11) NOT NULL AUTO_INCREMENT,
  contact_id text NOT NULL,
  label text,
  address_id text NOT NULL,
  FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
  FOREIGN KEY (address_id) REFERENCES addresses (id) ON DELETE CASCADE,
  UNIQUE (contact_id, address_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS org_addresses (
  id int(11) NOT NULL AUTO_INCREMENT,
  org_id text NOT NULL,
  label text,
  address_id text NOT NULL,
  FOREIGN KEY (org_id) REFERENCES organisations (id) ON DELETE CASCADE,
  FOREIGN KEY (address_id) REFERENCES addresses (id) ON DELETE CASCADE,
  UNIQUE (org_id, address_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS notes (
  id text NOT NULL, -- humid
  title text,
  content text,
  written_at datetime NOT NULL,
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS notes_tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  note_id text NOT NULL,
  tag_id int(11) NOT NULL,
  FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  UNIQUE (note_id, tag_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS tasks (
  id text NOT NULL, -- humid
  title text NOT NULL,
  content text,
  urgent boolean NOT NULL,
  recurrence text, -- RFC 5545 RRULE
  open_at datetime NOT NULL DEFAULT current_timestamp,
  due_at datetime,
  due_all_day boolean NOT NULL DEFAULT false,
  expire_at datetime,
  -- status is a virtual field, derived from task_log
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS tasks_tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  task_id text NOT NULL,
  tag_id int(11) NOT NULL,
  FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  UNIQUE (task_id, tag_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS task_log (
  id int(11) NOT NULL AUTO_INCREMENT,
  task_id text NOT NULL,
  changed_at datetime NOT NULL DEFAULT current_timestamp,
  status text NOT NULL, -- str<todo|wip|backlog|blocked|done|nvm>
  comment text,
  FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS wishes (
  id text NOT NULL, -- humid
  title text NOT NULL,
  content text,
  added_at datetime NOT NULL,
  -- this name was chosen to stay consistent with the tasks schema
  urgent boolean NOT NULL,
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS wishes_tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  wish_id text NOT NULL,
  tag_id int(11) NOT NULL,
  FOREIGN KEY (wish_id) REFERENCES wishes (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  UNIQUE (wish_id, tag_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS wish_log (
  id int(11) NOT NULL AUTO_INCREMENT,
  wish_id text NOT NULL,
  changed_at datetime NOT NULL DEFAULT current_timestamp,
  status text NOT NULL, -- str<dream|bought|nvm>
  comment text,
  FOREIGN KEY (wish_id) REFERENCES wishes (id) ON DELETE CASCADE,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS wish_urls (
  id int(11) NOT NULL AUTO_INCREMENT,
  wish_id text NOT NULL,
  url text NOT NULL,
  price decimal(10,2),
  FOREIGN KEY (wish_id) REFERENCES wishes (id) ON DELETE CASCADE,
  UNIQUE (wish_id, url),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS habits (
  id text NOT NULL, -- humid
  title text NOT NULL,
  every text NOT NULL, -- int|cron
  start_date date NOT NULL DEFAULT current_date,
  color text NOT NULL,
  icon text NOT NULL,
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS habit_log (
  id int(11) NOT NULL,
  habit_id text NOT NULL,
  changed_at datetime NOT NULL DEFAULT current_timestamp,
  FOREIGN KEY (habit_id) REFERENCES habits (id) ON DELETE CASCADE,
  UNIQUE (habit_id, changed_at),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS calendars (
  id text NOT NULL, -- humid
  title text NOT NULL,
  subtitle text,
  color text NOT NULL,
  position int(11) NOT NULL DEFAULT 0,
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS subscriptions (
  id text NOT NULL, -- humid
  title text NOT NULL,
  subtitle text,
  url text NOT NULL,
  color text NOT NULL,
  filter text,
  history boolean NOT NULL DEFAULT true,
  deduplicate boolean NOT NULL DEFAULT false,
  position int(11) NOT NULL DEFAULT 0,
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS shares (
  id text NOT NULL, -- humid
  name text NOT NULL,
  token text NOT NULL,
  birthdays boolean NOT NULL DEFAULT false,
  deadlines boolean NOT NULL DEFAULT false,
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id),
  UNIQUE (token)
);

CREATE TABLE IF NOT EXISTS share_sources (
  id int(11) NOT NULL AUTO_INCREMENT,
  share_id text NOT NULL,
  calendar_id text,
  subscription_id text,
  mode text NOT NULL,
  FOREIGN KEY (share_id) REFERENCES shares (id) ON DELETE CASCADE,
  FOREIGN KEY (calendar_id) REFERENCES calendars (id) ON DELETE CASCADE,
  FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE CASCADE,
  UNIQUE (share_id, calendar_id),
  UNIQUE (share_id, subscription_id),
  CHECK ((calendar_id IS NULL) <> (subscription_id IS NULL)),
  CHECK (mode IN ('full', 'redacted')),
  PRIMARY KEY (id)
);

CREATE VIEW IF NOT EXISTS sources AS
  SELECT 'calendar' AS type, id, title, subtitle, color, position FROM calendars
  UNION ALL
  SELECT 'subscription' AS type, id, title, subtitle, color, position FROM subscriptions;

CREATE TABLE IF NOT EXISTS appointments (
  id text NOT NULL, -- humid|external
  calendar_id text,
  subscription_id text,
  humid text NOT NULL,
  address_id text,
  location text,
  recurrence text, -- RFC 5545 RRULE value
  going boolean NOT NULL DEFAULT true,
  all_day boolean NOT NULL DEFAULT false,
  urgent boolean NOT NULL DEFAULT false,
  title text NOT NULL,
  content text,
  meeting text,
  travel_before int(11) NOT NULL DEFAULT 0, -- minutes
  travel_after int(11) NOT NULL DEFAULT 0, -- minutes
  starts_at datetime NOT NULL,
  ends_at datetime NOT NULL,
  FOREIGN KEY (humid) REFERENCES humids (id),
  FOREIGN KEY (calendar_id) REFERENCES calendars (id) ON DELETE CASCADE,
  FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE CASCADE,
  FOREIGN KEY (address_id) REFERENCES addresses (id) ON DELETE SET NULL,
  CHECK ((calendar_id IS NULL) <> (subscription_id IS NULL)),
  CHECK (ends_at >= starts_at),
  CHECK (travel_before >= 0 AND travel_after >= 0),
  UNIQUE (humid),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS calendars_tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  calendar_id text NOT NULL,
  tag_id int(11) NOT NULL,
  FOREIGN KEY (calendar_id) REFERENCES calendars (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  UNIQUE (calendar_id, tag_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS appointments_tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  appointment_id text NOT NULL,
  tag_id int(11) NOT NULL,
  FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  UNIQUE (appointment_id, tag_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS alarms (
  id text NOT NULL, -- humid
  appointment_id text,
  task_id text,
  wish_id text,
  trigger_at datetime,
  trigger_offset int(11), -- seconds
  relative_to text, -- start|end
  description text,
  FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE,
  FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
  FOREIGN KEY (wish_id) REFERENCES wishes (id) ON DELETE CASCADE,
  CHECK (
    (appointment_id IS NOT NULL AND task_id IS NULL AND wish_id IS NULL)
    OR
    (appointment_id IS NULL AND task_id IS NOT NULL AND wish_id IS NULL)
    OR
    (appointment_id IS NULL AND task_id IS NULL AND wish_id IS NOT NULL)
  ),
  CHECK ((trigger_at IS NULL) <> (trigger_offset IS NULL)),
  FOREIGN KEY (id) REFERENCES humids (id),
  CHECK (
    (trigger_offset IS NULL AND relative_to IS NULL)
    OR
    (trigger_offset IS NOT NULL AND relative_to IN ('start', 'end'))
  ),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS properties (
  id int(11) NOT NULL AUTO_INCREMENT,
  appointment_id text,
  task_id text,
  wish_id text,
  alarm_id text,
  contact_id text,
  org_id text,
  tag_id int(11),
  group_name text, -- vCard property group (item1.TEL / item1.X-ABLabel)
  name text NOT NULL,
  parameters text NOT NULL, -- JSON
  value text NOT NULL,
  position int(11) NOT NULL DEFAULT 0,
  FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE,
  FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
  FOREIGN KEY (wish_id) REFERENCES wishes (id) ON DELETE CASCADE,
  FOREIGN KEY (alarm_id) REFERENCES alarms (id) ON DELETE CASCADE,
  FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
  FOREIGN KEY (org_id) REFERENCES organisations (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  CHECK (
    (CASE WHEN appointment_id IS NOT NULL THEN 1 ELSE 0 END
      + CASE WHEN task_id IS NOT NULL THEN 1 ELSE 0 END
      + CASE WHEN wish_id IS NOT NULL THEN 1 ELSE 0 END
      + CASE WHEN alarm_id IS NOT NULL THEN 1 ELSE 0 END
      + CASE WHEN contact_id IS NOT NULL THEN 1 ELSE 0 END
      + CASE WHEN org_id IS NOT NULL THEN 1 ELSE 0 END
      + CASE WHEN tag_id IS NOT NULL THEN 1 ELSE 0 END) = 1
  ),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS caldav_revision (
  id int(11) NOT NULL,
  revision int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS caldav_resources (
  entity_type text NOT NULL, -- appointment|task|wish|travel_before|travel_after
  entity_id text NOT NULL,
  uid text NOT NULL,
  href text NOT NULL,
  collection text,
  revision int(11) NOT NULL DEFAULT 0,
  touched_at datetime NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (entity_type, entity_id),
  UNIQUE (uid),
  UNIQUE (collection, href)
);

CREATE TABLE IF NOT EXISTS caldav_changes (
  revision int(11) NOT NULL,
  collection text NOT NULL,
  href text NOT NULL,
  operation text NOT NULL, -- upsert|delete
  changed_at datetime NOT NULL DEFAULT current_timestamp,
  CHECK (operation IN ('upsert', 'delete')),
  PRIMARY KEY (revision, collection, href)
);

CREATE TABLE IF NOT EXISTS carddav_revision (
  id int(11) NOT NULL,
  revision int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS carddav_resources (
  entity_type text NOT NULL, -- contact|organisation|tag
  entity_id text NOT NULL,
  uid text NOT NULL,
  href text NOT NULL,
  collection text,
  revision int(11) NOT NULL DEFAULT 0,
  fingerprint text,
  touched_at datetime NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (entity_type, entity_id),
  UNIQUE (uid),
  UNIQUE (collection, href)
);

CREATE TABLE IF NOT EXISTS carddav_changes (
  revision int(11) NOT NULL,
  collection text NOT NULL,
  href text NOT NULL,
  operation text NOT NULL, -- upsert|delete
  changed_at datetime NOT NULL DEFAULT current_timestamp,
  CHECK (operation IN ('upsert', 'delete')),
  PRIMARY KEY (revision, collection, href)
);

CREATE TABLE IF NOT EXISTS timings (
  id text NOT NULL, -- humid
  description text NOT NULL,
  starts_at datetime NOT NULL,
  ends_at datetime NOT NULL,
  task_id text,
  FOREIGN KEY (id) REFERENCES humids (id),
  FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE SET NULL,
  CHECK (ends_at >= starts_at),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS timings_tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  timing_id text NOT NULL,
  tag_id int(11) NOT NULL,
  FOREIGN KEY (timing_id) REFERENCES timings (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  UNIQUE (timing_id, tag_id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS quotas (
  tag_id int(11) NOT NULL,
  period text NOT NULL, -- str<week|month>
  minutes int(11) NOT NULL,
  start_date date NOT NULL,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  CHECK (period IN ('week', 'month')),
  CHECK (minutes > 0),
  PRIMARY KEY (tag_id)
);

CREATE TABLE IF NOT EXISTS imap_credentials (
  id int(11) NOT NULL,
  email text NOT NULL,
  name text NOT NULL,
  username text NOT NULL,
  password text,
  hostname text NOT NULL,
  port int(11) NOT NULL,
  ssl_mode text NOT NULL, -- str<plain|tls|ssl>
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS bookmarks (
  id text NOT NULL, -- humid
  label text,
  url text NOT NULL,
  note text,
  favicon text,
  saved_at datetime NOT NULL,
  FOREIGN KEY (id) REFERENCES humids (id),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS bookmarks_tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  bookmark_id text NOT NULL,
  tag_id int(11) NOT NULL,
  FOREIGN KEY (bookmark_id) REFERENCES bookmarks (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
  UNIQUE (bookmark_id, tag_id),
  PRIMARY KEY (id)
);

CREATE INDEX appointments_window ON appointments (starts_at, ends_at);
CREATE INDEX timings_window ON timings (starts_at, ends_at);

CREATE INDEX appointments_lookup ON appointments (subscription_id);
CREATE INDEX task_log_lookup ON task_log (task_id, changed_at);

CREATE INDEX audit_log_lookup ON audit_log (table_name, record_id, changed_at);
CREATE INDEX caldav_changes_lookup ON caldav_changes (collection, revision);
CREATE INDEX caldav_changes_href_lookup ON caldav_changes (collection, href, revision);
CREATE INDEX carddav_changes_lookup ON carddav_changes (collection, revision);
CREATE INDEX carddav_changes_href_lookup ON carddav_changes (collection, href, revision);
CREATE INDEX http_logs_lookup ON http_logs (authenticated, changed_at);
CREATE INDEX system_logs_lookup ON system_logs (changed_at);
