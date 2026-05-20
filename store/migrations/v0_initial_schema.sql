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
  FOREIGN KEY (`parent_id`) REFERENCES `tags` (`id`) ON DELETE SET NULL,
  UNIQUE (`label`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `handle` text NOT NULL,
  `domain` text NOT NULL,
  `display_name` text NOT NULL,
  `first_name` text NOT NULL,
  `middle_name` text NOT NULL,
  `infix` text NOT NULL,
  `last_name` text NOT NULL,
  `birth_day` text NOT NULL,
  `discord_handle` text NOT NULL,
  `instagram_handle` text NOT NULL,
  `snapchat_handle` text NOT NULL,
  `matrix_handle` text NOT NULL,
  `linkedin_handle` text NOT NULL,
  UNIQUE (`handle`, `domain`),
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

CREATE TABLE IF NOT EXISTS `contact_orgs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `function` text,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text NOT NULL,
  `url` text NOT NULL,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  UNIQUE (`contact_id`, `url`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text NOT NULL,
  `email` text NOT NULL,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  UNIQUE (`contact_id`, `email`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_phone_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text NOT NULL,
  `phone_number` text NOT NULL,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  UNIQUE (`contact_id`, `phone_number`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `label` text NOT NULL,
  `address_id` int(11) NOT NULL,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE CASCADE,
  UNIQUE (`contact_id`, `address_id`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `note` (
  `id` text NOT NULL, -- humid
  `title` text NOT NULL,
  `content` text,
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
  `date` datetime NOT NULL,
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
  `date` datetime NOT NULL,
  `status` text NOT NULL, -- str<dream|backlog|bought|nvm>
  `comment` text,
  FOREIGN KEY (`wish_id`) REFERENCES `wishes` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `wish_urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wish_id` text NOT NULL,
  `url` text NOT NULL,
  `price` int(11),
  FOREIGN KEY (`wish_id`) REFERENCES `wishes` (`id`) ON DELETE CASCADE,
  UNIQUE (`wish_id`, `url`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `habits` (
  `id` text NOT NULL, -- humid
  `title` text NOT NULL,
  `every` text NOT NULL, -- int|cron
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

CREATE TABLE IF NOT EXISTS `calendar` (
  `id` text NOT NULL, -- humid
  `title` text NOT NULL,
  `subtitle` text,
  `color` text NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `calendar_subscription` (
  `id` text NOT NULL, -- humid
  `title` text NOT NULL,
  `subtitle` text,
  `url` text NOT NULL,
  `color` text NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `appointments` (
  `id` text NOT NULL, -- humid
  `calendar_id` text,
  `subscription_id` text,
  `address_id` int(11),
  `recurrence` text, -- int|cron
  `all_day` boolean NOT NULL DEFAULT false,
  `title` text NOT NULL,
  `content` text,
  `meeting` text,
  `color` text NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  FOREIGN KEY (`calendar_id`) REFERENCES `calendar` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subscription_id`) REFERENCES `calendar_subscription` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
  CHECK ((`calendar_id` IS NULL) <> (`subscription_id` IS NULL)),
  CHECK (`ends_at` >= `starts_at`),
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `calendars_tags` (
  `id` text NOT NULL, -- humid
  `calendar_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`calendar_id`) REFERENCES `calendar` (`id`) ON DELETE CASCADE,
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
  `ssl_mode` text NOT NULL, -- plain|tls|ssl
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `documents` (
  `id` text NOT NULL, -- humid
  `filename` text NOT NULL,
  `cdn_url` text NOT NULL,
  `source_url` text,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `documents_tags` (
  `id` text NOT NULL, -- humid
  `document_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  UNIQUE (`document_id`, `tag_id`),
  PRIMARY KEY (`id`)
);
