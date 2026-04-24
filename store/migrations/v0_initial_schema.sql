-- TODO(robin): foreign keys, ON DELETE, XOR constraints, UNIQUE constraints

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
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contacts_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_orgs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `function` text,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text NOT NULL,
  `url` text NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text NOT NULL,
  `email` text NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_phone_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text NOT NULL,
  `phone_number` text NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `contact_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `label` text NOT NULL,
  `address_id` int(11) NOT NULL,
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
  `initial_date` datetime NOT NULL DEFAULT current_timestamp,
  `due_date` datetime,
  `expiration_date` datetime,
  -- `status` is a virtual field, derived from task_log
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `tasks_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `task_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` text NOT NULL,
  `date` datetime NOT NULL,
  `status` text NOT NULL, -- str<todo|backlog|blocked|done|nvm>
  `comment` text,
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
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `wish_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wish_id` text NOT NULL,
  `date` datetime NOT NULL,
  `status` text NOT NULL, -- str<dream|backlog|bought|nvm>
  `comment` text,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `wish_urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wish_id` text NOT NULL,
  `url` text NOT NULL,
  `price` int(11),
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
  `address_id` text NOT NULL,
  `recurrence` text, -- int|cron
  `all_day` boolean NOT NULL DEFAULT false,
  `title` text NOT NULL,
  `content` text,
  `meeting` text,
  `color` text NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `calendars_tags` (
  `id` text NOT NULL, -- humid
  `calendar_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `appointments_tags` (
  `id` text NOT NULL, -- humid
  `calendar_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `timings` (
  `id` text NOT NULL, -- humid
  `task_id` text NOT NULL,
  `description` text NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `timings_tags` (
  `id` text NOT NULL, -- humid
  `timing_id` text NOT NULL,
  `tag_id` int(11) NOT NULL,
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
  PRIMARY KEY (`id`)
);