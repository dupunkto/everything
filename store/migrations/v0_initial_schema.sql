CREATE TABLE IF NOT EXISTS `migrations` (
  `version` int(11) NOT NULL,
  `executed_at` datetime NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`version`)
);

CREATE TABLE IF NOT EXISTS `config` (
  `property` text NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`property`)
);

CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11),
  `label` text NOT NULL,
  `color` text NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  FOREIGN KEY (`parent_id`) REFERENCES `tags` (`id`) ON DELETE SET NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `display_name` text,
  `first_name` text NOT NULL,
  `middle_name` text,
  `infix` text,
  `last_name` text,
  `birth_day` int(2),
  `birth_month` int(2),
  `birth_year` int(4),
  `note` text,
  CHECK ((`birth_day` IS NULL) = (`birth_month` IS NULL)),
  CHECK (`birth_year` IS NULL OR (`birth_day` IS NOT NULL AND `birth_month` IS NOT NULL)),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `organisations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `display_name` text NOT NULL,
  `legal_name` text,
  `registration_number` text,
  `vat_number` text,
  `note` text,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contacts_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  UNIQUE (`contact_id`, `tag_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `orgs_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`org_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  UNIQUE (`org_id`, `tag_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `function` text,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_socials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `handle` text NOT NULL,
  `type` text NOT NULL, -- str<instagram|discord|snapchat|linkedin|matrix|pinterest
                        -- twitter|youtube|facebook|activitypub|bsky>
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `org_socials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `type` text NOT NULL, -- instagram|discord|snapchat|linkedin|pinterest|youtube|facebook
  `handle` text NOT NULL,
  FOREIGN KEY (`org_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text,
  `url` text NOT NULL,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  UNIQUE (`contact_id`, `url`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `org_urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `label` text,
  `url` text NOT NULL,
  FOREIGN KEY (`org_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  UNIQUE (`org_id`, `url`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text,
  `email` text NOT NULL,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  UNIQUE (`contact_id`, `email`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `org_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `label` text,
  `email` text NOT NULL,
  FOREIGN KEY (`org_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  UNIQUE (`org_id`, `email`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_phone_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text,
  `phone_number` text NOT NULL,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  UNIQUE (`contact_id`, `phone_number`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `org_phone_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `label` text,
  `phone_number` text NOT NULL,
  FOREIGN KEY (`org_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  UNIQUE (`org_id`, `phone_number`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` text,
  `street_name` text NOT NULL,
  `street_number` text NOT NULL,
  `postal_code` text NOT NULL,
  `city` text NOT NULL,
  `province` text NOT NULL,
  `country` text NOT NULL,
  `timezone` text NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text,
  `address_id` int(11) NOT NULL,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE CASCADE,
  UNIQUE (`contact_id`, `address_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `org_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `label` text,
  `address_id` int(11) NOT NULL,
  FOREIGN KEY (`org_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE CASCADE,
  UNIQUE (`org_id`, `address_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `notes` (
  `id` text NOT NULL, -- humid
  `title` text NOT NULL,
  `content` text,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `notes_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `note_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  UNIQUE (`note_id`, `tag_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` text NOT NULL, -- humid
  `title` text NOT NULL,
  `content` text,
  `urgent` boolean NOT NULL,
  `recurrence` text, -- int|cron
  `open_date` datetime NOT NULL DEFAULT current_timestamp,
  `due_date` datetime,
  `due_all_day` boolean NOT NULL DEFAULT false,
  `expiration_date` datetime,
  -- `status` is a virtual field, derived from task_log
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `tasks_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  UNIQUE (`task_id`, `tag_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `task_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` text NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp,
  `status` text NOT NULL, -- str<todo|backlog|blocked|done|nvm>
  `comment` text,
  FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `wishes` (
  `id` text NOT NULL, -- humid
  `title` text NOT NULL,
  `content` text,
  -- this name was chosen to stay consistent with the tasks schema
  `urgent` boolean NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `wishes_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wish_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`wish_id`) REFERENCES `wishes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  UNIQUE (`wish_id`, `tag_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `wish_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wish_id` text NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp,
  `status` text NOT NULL, -- str<dream|bought|nvm>
  `comment` text,
  FOREIGN KEY (`wish_id`) REFERENCES `wishes` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `wish_urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wish_id` text NOT NULL,
  `url` text NOT NULL,
  `price` decimal(10,2),
  FOREIGN KEY (`wish_id`) REFERENCES `wishes` (`id`) ON DELETE CASCADE,
  UNIQUE (`wish_id`, `url`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `habits` (
  `id` text NOT NULL, -- humid
  `title` text NOT NULL,
  `every` text NOT NULL, -- int|cron
  `start_date` date NOT NULL DEFAULT current_date,
  `color` text NOT NULL,
  `icon` text NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `habit_log` (
  `id` int(11) NOT NULL,
  `habit_id` text NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp,
  FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE,
  UNIQUE (`habit_id`, `date`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `calendars` (
  `id` text NOT NULL, -- humid
  `title` text NOT NULL,
  `subtitle` text,
  `color` text NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` text NOT NULL, -- humid
  `title` text NOT NULL,
  `subtitle` text,
  `url` text NOT NULL,
  `color` text NOT NULL,
  `filter` text,
  `order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
);

CREATE VIEW IF NOT EXISTS `sources` AS
  SELECT 'calendar' AS `type`, `id`, `title`, `subtitle`, `color`, `order` FROM `calendars`
  UNION ALL
  SELECT 'subscription' AS `type`, `id`, `title`, `subtitle`, `color`, `order` FROM `subscriptions`;

CREATE TABLE IF NOT EXISTS `appointments` (
  `id` text NOT NULL, -- humid|external
  `calendar_id` text,
  `subscription_id` text,
  `address_id` int(11),
  `location` text,
  `recurrence` text, -- int|cron
  `recurrence_until` datetime,
  `recurrence_count` int(11),
  `going` boolean NOT NULL DEFAULT true,
  `all_day` boolean NOT NULL DEFAULT false,
  `urgent` boolean NOT NULL DEFAULT false,
  `title` text NOT NULL,
  `content` text,
  `meeting` text,
  `travel_before` int(11) NOT NULL DEFAULT 0, -- minutes
  `travel_after` int(11) NOT NULL DEFAULT 0, -- minutes
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  FOREIGN KEY (`calendar_id`) REFERENCES `calendars` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
  CHECK ((`calendar_id` IS NULL) <> (`subscription_id` IS NULL)),
  CHECK (`ends_at` >= `starts_at`),
  CHECK (`recurrence_until` IS NULL OR `recurrence_count` IS NULL),
  CHECK (`travel_before` >= 0 AND `travel_after` >= 0),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `calendars_tags` (
  `id` text NOT NULL, -- humid
  `calendar_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`calendar_id`) REFERENCES `calendars` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  UNIQUE (`calendar_id`, `tag_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `appointments_tags` (
  `id` text NOT NULL, -- humid
  `appointment_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  UNIQUE (`appointment_id`, `tag_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `timings` (
  `id` text NOT NULL, -- humid
  `description` text NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `task_id` text,
  FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  CHECK (`ends_at` >= `starts_at`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `timings_tags` (
  `id` text NOT NULL, -- humid
  `timing_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`timing_id`) REFERENCES `timings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  UNIQUE (`timing_id`, `tag_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `imap_connections` (
  `id` int(11) NOT NULL,
  `email` text NOT NULL,
  `name` text NOT NULL,
  `username` text NOT NULL,
  `password` text,
  `hostname` text NOT NULL,
  `port` int(11) NOT NULL,
  `ssl_mode` text NOT NULL, -- str<plain|tls|ssl>
  PRIMARY KEY (`id`)
);
