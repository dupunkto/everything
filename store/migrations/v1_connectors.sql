CREATE TABLE IF NOT EXISTS connectors (
  id int(11) NOT NULL AUTO_INCREMENT,
  name text NOT NULL,
  token varchar(64) NOT NULL,
  UNIQUE (token),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS connector_apps (
  connector_id int(11) NOT NULL,
  app varchar(16) NOT NULL,
  FOREIGN KEY (connector_id) REFERENCES connectors (id) ON DELETE CASCADE,
  PRIMARY KEY (connector_id, app)
);
